<?php

namespace App\Console\Commands;

use App\Models\Afiliado;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fusiona personas duplicadas (mismo nombre+apellido, CI distinto) en un solo afiliado.
 * Conserva un CI único, reasigna lotes/pagos/multas/trabajos/users y luego elimina el duplicado.
 * No borra lotes ni historial operativo (a diferencia del delete destructivo anterior).
 */
class DeduplicarAfiliadosCommand extends Command
{
    protected $signature = 'afiliados:deduplicar
                            {--dry-run : Simular sin modificar la base}
                            {--force : Ejecutar sin confirmación}';

    protected $description = 'Fusiona afiliados duplicados por nombre+apellido en una sola persona (1 CI, N lotes)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $grupos = Afiliado::query()
            ->select([
                DB::raw('UPPER(TRIM(apellidos)) as apellidos_key'),
                DB::raw('UPPER(TRIM(nombres)) as nombres_key'),
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('apellidos_key', 'nombres_key')
            ->having('total', '>', 1)
            ->orderBy('apellidos_key')
            ->orderBy('nombres_key')
            ->get();

        if ($grupos->isEmpty()) {
            $this->info('No hay afiliados con el mismo nombre y apellido y CI distinto.');

            return self::SUCCESS;
        }

        $plan = [];
        $aEliminar = 0;

        foreach ($grupos as $grupo) {
            $filas = Afiliado::query()
                ->whereRaw('UPPER(TRIM(apellidos)) = ?', [$grupo->apellidos_key])
                ->whereRaw('UPPER(TRIM(nombres)) = ?', [$grupo->nombres_key])
                ->orderBy('id')
                ->get();

            $canonico = $this->elegirCanonico($filas);
            $duplicados = $filas->where('id', '!=', $canonico->id)->values();

            $plan[] = [
                'nombre' => trim($canonico->apellidos.' '.$canonico->nombres),
                'canonico_id' => $canonico->id,
                'canonico_ci' => $canonico->ci,
                'duplicados' => $duplicados->map(fn (Afiliado $a) => [
                    'id' => $a->id,
                    'ci' => $a->ci,
                    'lotes' => $a->lotes()->count(),
                ])->all(),
            ];
            $aEliminar += $duplicados->count();
        }

        $this->table(
            ['Persona', 'Conservar (id/CI)', 'Fusionar (ids/CI)', 'Lotes a mover'],
            collect($plan)->map(function (array $p) {
                $dups = collect($p['duplicados']);

                return [
                    $p['nombre'],
                    "{$p['canonico_id']} / {$p['canonico_ci']}",
                    $dups->map(fn (array $d) => "{$d['id']}/{$d['ci']}")->implode(', '),
                    $dups->sum('lotes'),
                ];
            })->all()
        );

        $this->info("Grupos: {$grupos->count()}. Registros a fusionar (dejarán de ser personas): {$aEliminar}.");

        if ($dryRun) {
            $this->warn('Simulación (--dry-run): no se modificó la base.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Fusionar ahora? Cada persona quedará con un solo CI y sus lotes unidos.', false)) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        $fusionados = 0;

        DB::transaction(function () use ($plan, &$fusionados): void {
            foreach ($plan as $item) {
                $canonico = Afiliado::query()->findOrFail($item['canonico_id']);

                foreach ($item['duplicados'] as $dup) {
                    $duplicado = Afiliado::query()->find($dup['id']);
                    if (! $duplicado) {
                        continue;
                    }

                    $this->fusionarEn($canonico, $duplicado);
                    $fusionados++;
                }

                $this->sincronizarEstadoDesdeLotes($canonico->fresh());
            }
        });

        $this->info("Fusionados: {$fusionados}. Afiliados (personas) restantes: ".Afiliado::query()->count());
        $this->info('Lotes (conexiones): '.DB::table('lote_afiliados')->count());

        $ciDup = DB::table('afiliados')
            ->select('ci', DB::raw('COUNT(*) as c'))
            ->groupBy('ci')
            ->having('c', '>', 1)
            ->count();

        if ($ciDup > 0) {
            $this->error("Atención: quedaron {$ciDup} CI repetidos (no debería ocurrir).");

            return self::FAILURE;
        }

        $this->info('CI únicos: OK. Regla: 1 persona = 1 CI = N lotes.');

        return self::SUCCESS;
    }

    /**
     * Prefiere CI real (no placeholder 99000…) y, en empate, el registro más antiguo.
     *
     * @param  Collection<int, Afiliado>  $filas
     */
    private function elegirCanonico(Collection $filas): Afiliado
    {
        $conCiReal = $filas->first(fn (Afiliado $a): bool => ! $this->esCiSintetico((string) $a->ci));

        return $conCiReal ?? $filas->sortBy('id')->first();
    }

    private function esCiSintetico(string $ci): bool
    {
        return (bool) preg_match('/^99000\d+$/', $ci);
    }

    private function fusionarEn(Afiliado $canonico, Afiliado $duplicado): void
    {
        // Completar datos personales vacíos del canónico.
        $updates = [];
        foreach (['telefono', 'direccion'] as $campo) {
            if (blank($canonico->{$campo}) && filled($duplicado->{$campo})) {
                $updates[$campo] = $duplicado->{$campo};
            }
        }
        if ($updates !== []) {
            $canonico->update($updates);
        }

        // Reasignar lotes (numero_lote ya es único global → no colisionan).
        DB::table('lote_afiliados')
            ->where('afiliado_id', $duplicado->id)
            ->update([
                'afiliado_id' => $canonico->id,
                'fecha_actualizacion' => now(),
            ]);

        foreach (['pagos', 'multas', 'trabajos'] as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'afiliado_id')) {
                DB::table($tabla)
                    ->where('afiliado_id', $duplicado->id)
                    ->update(['afiliado_id' => $canonico->id]);
            }
        }

        if (Schema::hasColumn('users', 'afiliado_id')) {
            $userCanonico = DB::table('users')->where('afiliado_id', $canonico->id)->exists();
            $usersDup = DB::table('users')->where('afiliado_id', $duplicado->id)->get();

            foreach ($usersDup as $user) {
                if ($userCanonico) {
                    // Evitar violar unique afiliado_id: desvincular el user del duplicado.
                    DB::table('users')->where('id', $user->id)->update(['afiliado_id' => null]);
                } else {
                    DB::table('users')->where('id', $user->id)->update(['afiliado_id' => $canonico->id]);
                    $userCanonico = true;
                }
            }
        }

        // Borrar solo la persona duplicada; lotes ya están en el canónico.
        $duplicado->delete();
    }

    private function sincronizarEstadoDesdeLotes(Afiliado $afiliado): void
    {
        $estados = DB::table('lote_afiliados')
            ->where('afiliado_id', $afiliado->id)
            ->pluck('estado');

        if ($estados->isEmpty()) {
            return;
        }

        $nuevo = match (true) {
            $estados->contains(Afiliado::ESTADO_ACTIVO) => Afiliado::ESTADO_ACTIVO,
            $estados->contains(Afiliado::ESTADO_CORTADO) => Afiliado::ESTADO_CORTADO,
            default => Afiliado::ESTADO_BAJA,
        };

        if ($afiliado->estado !== $nuevo) {
            $afiliado->update(['estado' => $nuevo]);
        }
    }
}
