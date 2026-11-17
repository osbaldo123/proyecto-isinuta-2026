<?php

namespace App\Services;

use App\Models\Afiliado;
use App\Models\Asistencia;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Trabajo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrabajoListaService
{
    /**
     * Una fila por lote de la OTB. El mismo afiliado se repite si tiene varios lotes,
     * para marcar presente/falta de forma independiente.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function filas(Trabajo $trabajo): Collection
    {
        $asistencias = $trabajo->asistencias()->get()->keyBy('lote_afiliado_id');

        return $this->lotesDeLaOtb($trabajo)
            ->map(function (LoteAfiliado $lote) use ($asistencias): array {
                $asistencia = $asistencias->get($lote->id);
                $afiliado = $lote->afiliado;

                return [
                    'lote_afiliado_id' => $lote->id,
                    'afiliado_id' => $afiliado->id,
                    'nombre' => $afiliado->nombre_completo,
                    'ci' => $afiliado->ci,
                    'presente' => (bool) ($asistencia?->presente ?? false),
                    'guardado' => $asistencia !== null,
                    'numero_lote' => $lote->numero_lote,
                    'otb' => $lote->otb?->nombre,
                    'categoria' => $lote->categoria?->nombre,
                    'servicios' => $lote->serviciosActivos
                        ->map(fn ($servicio): string => (string) $servicio->nombre)
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy([
                ['nombre', 'asc'],
                ['numero_lote', 'asc'],
            ], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @param  list<array{lote_afiliado_id: int, presente: bool}>  $marcas
     * @return array{presentes: int, ausentes: int, multas: int}
     */
    public function guardar(Trabajo $trabajo, array $marcas, ?User $usuario): array
    {
        $filas = $this->filas($trabajo);
        $esperados = $filas->pluck('lote_afiliado_id')->map(fn ($id): int => (int) $id)->sort()->values();
        $enviados = collect($marcas)->pluck('lote_afiliado_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values();

        if ($enviados->all() !== $esperados->all()) {
            throw ValidationException::withMessages([
                'asistencias' => 'La lista no coincide con los lotes de esta OTB. Actualice la página.',
            ]);
        }

        $porLote = collect($marcas)->keyBy(fn (array $marca): int => (int) $marca['lote_afiliado_id']);
        $lotes = $this->lotesDeLaOtb($trabajo)->keyBy('id');
        $presentes = 0;
        $ausentes = 0;
        $multasCreadas = 0;

        DB::transaction(function () use ($trabajo, $porLote, $lotes, $usuario, &$presentes, &$ausentes, &$multasCreadas): void {
            foreach ($lotes as $loteId => $lote) {
                $presente = (bool) ($porLote->get((int) $loteId)['presente'] ?? false);

                Asistencia::query()->updateOrCreate(
                    [
                        'trabajo_id' => $trabajo->id,
                        'lote_afiliado_id' => (int) $loteId,
                    ],
                    [
                        'afiliado_id' => $lote->afiliado_id,
                        'presente' => $presente,
                    ],
                );

                if ($presente) {
                    $presentes++;
                    $this->anularMultaPendienteDelLote($trabajo, (int) $loteId);

                    continue;
                }

                $ausentes++;
                $multasCreadas += $this->generarMultaDelLote($trabajo, $lote, $usuario);
            }
        });

        return [
            'presentes' => $presentes,
            'ausentes' => $ausentes,
            'multas' => $multasCreadas,
        ];
    }

    public function refrescarMultasPendientes(Trabajo $trabajo): void
    {
        $trabajo->loadMissing('otb');

        $trabajo->multas()
            ->where('estado', Multa::ESTADO_PENDIENTE)
            ->with('lote')
            ->get()
            ->each(function (Multa $multa) use ($trabajo): void {
                if (! $multa->lote) {
                    return;
                }

                $montoNuevo = round((float) $trabajo->monto_multa, 2);
                $sinCobrar = abs((float) $multa->saldo - (float) $multa->monto) < 0.001;

                $multa->update([
                    'fecha_generacion' => $trabajo->fecha_inicio->toDateString(),
                    'monto' => $montoNuevo,
                    'saldo' => $sinCobrar ? $montoNuevo : $multa->saldo,
                    'descripcion' => $this->descripcionMulta($trabajo, $multa->lote),
                ]);
            });
    }

    /** Presentes o faltas contados por lote. */
    public function contarLotesMarcados(Trabajo $trabajo, bool $presente): int
    {
        return Asistencia::query()
            ->where('trabajo_id', $trabajo->id)
            ->where('presente', $presente)
            ->whereIn('lote_afiliado_id', $this->lotesDeLaOtb($trabajo)->pluck('id'))
            ->count();
    }

    /**
     * @return Collection<int, LoteAfiliado>
     */
    private function lotesDeLaOtb(Trabajo $trabajo): Collection
    {
        return LoteAfiliado::query()
            ->with([
                'afiliado:id,nombres,apellidos,ci,estado',
                'otb:id,nombre',
                'categoria:id,nombre',
                'serviciosActivos:id,nombre,tipo',
            ])
            ->where('otb_id', $trabajo->otb_id)
            ->where('estado', '!=', Afiliado::ESTADO_BAJA)
            ->whereHas('afiliado', fn ($query) => $query->where('estado', '!=', Afiliado::ESTADO_BAJA))
            ->orderByRaw('CAST(numero_lote AS UNSIGNED), numero_lote')
            ->get();
    }

    private function anularMultaPendienteDelLote(Trabajo $trabajo, int $loteId): void
    {
        $trabajo->multas()
            ->where('lote_afiliado_id', $loteId)
            ->where('estado', Multa::ESTADO_PENDIENTE)
            ->update(['estado' => Multa::ESTADO_ANULADA, 'saldo' => 0]);
    }

    private function generarMultaDelLote(Trabajo $trabajo, LoteAfiliado $lote, ?User $usuario): int
    {
        $trabajo->loadMissing('otb');

        $existente = $trabajo->multas()
            ->where('lote_afiliado_id', $lote->id)
            ->latest('id')
            ->first();

        if ($existente?->estado === Multa::ESTADO_PAGADA) {
            return 0;
        }

        $datos = [
            'afiliado_id' => $lote->afiliado_id,
            'lote_afiliado_id' => $lote->id,
            'trabajo_id' => $trabajo->id,
            'fecha_generacion' => $trabajo->fecha_inicio->toDateString(),
            'motivo' => Multa::MOTIVO_FALTA_TRABAJO,
            'descripcion' => $this->descripcionMulta($trabajo, $lote),
            'monto' => $trabajo->monto_multa,
            'saldo' => $trabajo->monto_multa,
            'estado' => Multa::ESTADO_PENDIENTE,
            'generada_por' => $usuario?->id,
        ];

        if ($existente) {
            $existente->update($datos);

            return 0;
        }

        Multa::query()->create($datos);

        return 1;
    }

    public function descripcionMulta(Trabajo $trabajo, LoteAfiliado $lote): string
    {
        $otb = $trabajo->otb?->nombre ?? 'OTB';
        $tipo = trim((string) $trabajo->tipo);
        $texto = trim("Inasistencia a {$tipo}. OTB {$otb}. Lote {$lote->numero_lote}. {$trabajo->descripcion}");

        return mb_substr($texto, 0, 255);
    }
}
