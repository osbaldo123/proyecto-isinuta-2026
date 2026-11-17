<?php

namespace App\Services;

use App\Models\Afiliado;
use App\Models\CesionLote;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\User;
use App\Support\TextoPresentacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transferencia (cesión) de un lote a otra persona.
 *
 * Reglas:
 *  - Solo se transfiere un lote que no está dado de baja.
 *  - El lote debe estar sin deuda: cuotas de meses cerrados, multas pendientes y saldo de afiliación en cero.
 *  - El lote es el mismo registro (número, servicios, cuotas e historial); solo cambia su titular.
 *  - El titular anterior conserva sus otros lotes. Si tras ceder se queda sin lotes activos,
 *    pasa a baja (sus recibos históricos se siguen viendo). Si aún tiene otros lotes, sigue activo.
 *  - Los recibos y multas anteriores siguen a nombre de quien los pagó (guardan su propio afiliado_id).
 */
class TransferenciaLoteService
{
    public function __construct(
        private readonly CalculadorMultasService $calculadorMultas,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * Deuda actual del lote. Antes de calcular emite las cuotas de meses cerrados
     * y genera las multas por mora que correspondan (igual que al cobrar).
     *
     * @return array{
     *   cuotas: float,
     *   meses: int,
     *   rango_meses: string|null,
     *   multas: float,
     *   cantidad_multas: int,
     *   afiliacion: float,
     *   total: float,
     *   al_dia: bool
     * }
     */
    public function deudaDelLote(LoteAfiliado $lote, ?User $usuario = null): array
    {
        $this->calculadorMultas->evaluarLote($lote, $usuario);

        $cuotas = Cuota::query()
            ->where('lote_afiliado_id', $lote->id)
            ->where('saldo', '>', 0)
            ->get();

        $multas = Multa::query()
            ->where('lote_afiliado_id', $lote->id)
            ->where('estado', Multa::ESTADO_PENDIENTE)
            ->get();

        $lote->refresh();

        $totalCuotas = round((float) $cuotas->sum(fn (Cuota $c): float => (float) $c->saldo), 2);
        $totalMultas = round((float) $multas->sum(fn (Multa $m): float => (float) ($m->saldo ?? $m->monto)), 2);
        $afiliacion = round(max(
            (float) $lote->saldo_afiliacion,
            (float) $lote->saldo_afiliacion_agua + (float) $lote->saldo_afiliacion_alcantarillado,
        ), 2);
        $periodos = $cuotas->pluck('periodo')->unique()->sort()->values()->all();
        $total = round($totalCuotas + $totalMultas + $afiliacion, 2);

        return [
            'cuotas' => $totalCuotas,
            'meses' => count($periodos),
            'rango_meses' => $periodos === [] ? null : TextoPresentacion::rangoDePeriodos($periodos),
            'multas' => $totalMultas,
            'cantidad_multas' => $multas->count(),
            'afiliacion' => $afiliacion,
            'total' => $total,
            'al_dia' => $total <= 0.001,
        ];
    }

    /**
     * Motivo por el que el lote no se puede transferir ahora, o null si se puede.
     *
     * @param  array{al_dia: bool, total: float}  $deuda
     */
    public function motivoBloqueo(LoteAfiliado $lote, array $deuda): ?string
    {
        if ($lote->estado === Afiliado::ESTADO_BAJA) {
            return 'El lote está dado de baja. Reactívelo antes de transferirlo.';
        }

        if (! $lote->afiliado_id) {
            return 'El lote no tiene un titular asignado.';
        }

        if (! $deuda['al_dia']) {
            return 'El lote tiene deudas pendientes. Para transferirlo deben estar canceladas todas sus deudas.';
        }

        return null;
    }

    /**
     * Transfiere el lote a la persona indicada (existente por CI, o nueva).
     *
     * @param  array{
     *   ci: string,
     *   nombres?: string|null,
     *   apellidos?: string|null,
     *   telefono?: string|null,
     *   fecha_cesion: string,
     *   motivo: string,
     *   documento_respaldo?: string|null
     * }  $datos
     */
    public function transferir(LoteAfiliado $lote, array $datos, ?User $usuario = null): CesionLote
    {
        $cesion = DB::transaction(function () use ($lote, $datos, $usuario): CesionLote {
            /** @var LoteAfiliado $lote */
            $lote = LoteAfiliado::query()->lockForUpdate()->findOrFail($lote->id);
            $anterior = Afiliado::query()->findOrFail($lote->afiliado_id);

            // Se vuelve a revisar aquí: la deuda pudo cambiar desde que se abrió el formulario.
            $bloqueo = $this->motivoBloqueo($lote, $this->deudaDelLote($lote, $usuario));
            if ($bloqueo !== null) {
                throw ValidationException::withMessages(['lote' => $bloqueo]);
            }

            $ci = preg_replace('/\D/', '', (string) $datos['ci']);
            $nuevo = Afiliado::query()->where('ci', $ci)->orderBy('id')->first();

            if ($nuevo && $nuevo->id === $anterior->id) {
                throw ValidationException::withMessages(['ci' => 'Esa persona ya es la titular de este lote.']);
            }

            $telefono = filled($datos['telefono'] ?? null)
                ? preg_replace('/\D/', '', (string) $datos['telefono'])
                : null;

            if (! $nuevo) {
                $nuevo = Afiliado::create([
                    'nombres' => trim((string) $datos['nombres']),
                    'apellidos' => trim((string) $datos['apellidos']),
                    'ci' => $ci,
                    'telefono' => $telefono,
                    'direccion' => $lote->direccion,
                    'estado' => Afiliado::ESTADO_ACTIVO,
                ]);
            } elseif ($nuevo->estado === Afiliado::ESTADO_BAJA) {
                // La persona vuelve a tener un lote activo.
                $nuevo->update(['estado' => Afiliado::ESTADO_ACTIVO]);
            }

            // El teléfono del lote era del titular anterior: se reemplaza por el del nuevo.
            $lote->update([
                'afiliado_id' => $nuevo->id,
                'telefono' => $telefono ?? $nuevo->telefono,
            ]);

            $anterior->refrescarTelefonoDesdeLotes();
            $nuevo->refrescarTelefonoDesdeLotes();

            // Solo un lote y lo cedió → baja. Con más lotes activos sigue como titular.
            $sigueConLotesActivos = LoteAfiliado::query()
                ->where('afiliado_id', $anterior->id)
                ->where('estado', '!=', Afiliado::ESTADO_BAJA)
                ->exists();

            if (! $sigueConLotesActivos && $anterior->estado !== Afiliado::ESTADO_BAJA) {
                $anterior->update(['estado' => Afiliado::ESTADO_BAJA]);
            }

            return CesionLote::create([
                'lote_afiliado_id' => $lote->id,
                'afiliado_anterior_id' => $anterior->id,
                'nombre_anterior' => $anterior->nombre_completo,
                'ci_anterior' => $anterior->ci,
                'afiliado_nuevo_id' => $nuevo->id,
                'nombre_nuevo' => $nuevo->nombre_completo,
                'ci_nuevo' => $nuevo->ci,
                'fecha_cesion' => $datos['fecha_cesion'],
                'motivo' => trim((string) $datos['motivo']),
                'documento_respaldo' => filled($datos['documento_respaldo'] ?? null)
                    ? trim((string) $datos['documento_respaldo'])
                    : null,
                'registrado_por' => $usuario?->id,
            ]);
        });

        $this->activityLogger->log(
            $usuario,
            'Afiliados',
            "Transferencia del lote {$lote->numero_lote}: de {$cesion->nombre_anterior} (CI {$cesion->ci_anterior}) a {$cesion->nombre_nuevo} (CI {$cesion->ci_nuevo})",
        );

        return $cesion;
    }

    /**
     * Historial de titulares del lote para mostrar en la ficha.
     *
     * @return list<array<string, mixed>>
     */
    public function historial(LoteAfiliado $lote): array
    {
        return $lote->cesiones()
            ->with('registradoPor:id,name')
            ->get()
            ->map(fn (CesionLote $cesion): array => [
                'id' => $cesion->id,
                'fecha' => $cesion->fecha_cesion?->format('d/m/Y'),
                'anterior' => ['nombre' => $cesion->nombre_anterior, 'ci' => $cesion->ci_anterior],
                'nuevo' => ['nombre' => $cesion->nombre_nuevo, 'ci' => $cesion->ci_nuevo],
                'motivo' => $cesion->motivo,
                'documento' => $cesion->documento_respaldo,
                'registrado_por' => $cesion->registradoPor?->name,
            ])
            ->values()
            ->all();
    }
}
