<?php

namespace App\Services\Pagos;

use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Servicio;
use App\Services\CuotaMensualService;
use App\Services\ReciboPropositoService;
use App\Support\MultaTexto;
use App\Support\TextoPresentacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Estado de cuenta de un lote: mes por mes de cada servicio en una gestión, los recibos
 * (los anulados se muestran pero no suman) y las multas.
 */
class EstadoCuentaService
{
    public const MES_PAGADO = 'pagado';

    public const MES_ADELANTADO = 'adelantado';

    public const MES_VENCIDO = 'vencido';

    public const MES_PENDIENTE = 'pendiente';

    public const MES_POR_VENIR = 'por_venir';

    public const MES_SIN_SERVICIO = 'sin_servicio';

    public function __construct(
        private readonly CuotaMensualService $cuotas,
        private readonly MesesPorCobrarService $mesesPorCobrar,
        private readonly ReciboPropositoService $reciboProposito,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function paraLote(LoteAfiliado $lote, ?int $gestion = null): array
    {
        $this->cuotas->emitirLote($lote);
        $lote->loadMissing(['afiliado', 'otb', 'categoria']);

        $anioActual = (int) now()->year;
        $desde = $lote->fecha_afiliacion ? (int) Carbon::parse($lote->fecha_afiliacion)->year : $anioActual;
        $gestiones = range(min($desde, $anioActual), $anioActual + 1);
        $gestion = in_array($gestion, $gestiones, true) ? $gestion : $anioActual;

        $cuotas = Cuota::query()->where('lote_afiliado_id', $lote->id)->get();

        return [
            'gestion' => $gestion,
            'gestiones' => $gestiones,
            'resumen' => $this->resumen($lote, $cuotas),
            'servicios' => $this->meses($lote, $gestion, $cuotas),
            'recibos' => $this->recibos($lote),
            'multas' => $this->multas($lote),
        ];
    }

    /**
     * @param  Collection<int, Cuota>  $cuotas
     * @return array<string, mixed>
     */
    private function resumen(LoteAfiliado $lote, Collection $cuotas): array
    {
        $vencidas = $cuotas->filter(fn (Cuota $c): bool => $c->vencida());
        $pendientes = round((float) $cuotas->sum(fn (Cuota $c): float => (float) $c->saldo), 2);
        $multas = round((float) Multa::query()
            ->where('lote_afiliado_id', $lote->id)
            ->where('estado', Multa::ESTADO_PENDIENTE)
            ->get()
            ->sum(fn (Multa $m): float => (float) ($m->saldo ?? $m->monto)), 2);
        $afiliacion = round((float) $lote->saldo_afiliacion, 2);

        return [
            'cuotas_pendientes' => $pendientes,
            'cuotas_vencidas' => round((float) $vencidas->sum(fn (Cuota $c): float => (float) $c->saldo), 2),
            'meses_vencidos' => $vencidas->pluck('periodo')->unique()->count(),
            'rango_vencido' => $vencidas->isEmpty()
                ? null
                : TextoPresentacion::rangoDePeriodos($vencidas->pluck('periodo')->unique()->sort()->values()->all()),
            'multas_pendientes' => $multas,
            'saldo_afiliacion' => $afiliacion,
            'forma_pago_afiliacion' => $lote->forma_pago_afiliacion,
            'total' => round($pendientes + $multas + $afiliacion, 2),
            'al' => now()->format('d/m/Y'),
        ];
    }

    /**
     * @param  Collection<int, Cuota>  $cuotas
     * @return list<array<string, mixed>>
     */
    private function meses(LoteAfiliado $lote, int $gestion, Collection $cuotas): array
    {
        $servicios = $lote->servicios()
            ->where('servicios.activo', true)
            ->whereIn('servicios.tipo', ['agua', 'alcantarillado'])
            ->get()
            ->sortBy(fn (Servicio $s): int => $s->esAgua() ? 0 : 1)
            ->values();

        $porClave = $cuotas->keyBy(fn (Cuota $c): string => $c->servicio_id.'|'.$c->periodo);
        $recibosPorCuota = $this->recibosPorCuota($cuotas->pluck('id')->all());
        $actual = now()->format('Y-m');
        $tope = now()->startOfMonth()->addMonths(MesesPorCobrarService::MESES_ADELANTO)->format('Y-m');
        $venceMesActual = Carbon::parse(CuotaMensualService::fechaVencimientoDelPeriodo(now()->format('Y-m')))
            ->format('d/m/Y');

        return $servicios->map(function (Servicio $servicio) use ($lote, $gestion, $porClave, $recibosPorCuota, $actual, $tope, $venceMesActual): array {
            $inicio = Carbon::parse($servicio->pivot?->fecha_inicio ?? $lote->fecha_afiliacion ?? now())->format('Y-m');
            $inicioLote = $lote->fecha_afiliacion ? Carbon::parse($lote->fecha_afiliacion)->format('Y-m') : $inicio;
            $inicio = max($inicio, $inicioLote);
            $fin = $servicio->pivot?->fecha_fin ? Carbon::parse($servicio->pivot->fecha_fin)->format('Y-m') : null;
            $porCobrar = collect($this->mesesPorCobrar->paraServicio($lote, $servicio))->keyBy('periodo');
            $recibosAdelanto = $this->recibosDeAdelantos($lote, $servicio, $gestion);

            $celdas = [];
            for ($mes = 1; $mes <= 12; $mes++) {
                $periodo = sprintf('%04d-%02d', $gestion, $mes);
                $cuota = $porClave->get($servicio->id.'|'.$periodo);
                $recibos = array_values(array_unique([
                    ...($cuota ? ($recibosPorCuota[$cuota->id] ?? []) : []),
                    ...($recibosAdelanto[$periodo] ?? []),
                ]));
                $porPagar = $porCobrar->get($periodo);

                // Un mes ya emitido manda su cuota; uno sin emitir: en curso, adelantable o ya adelantado.
                [$estado, $detalle, $monto] = match (true) {
                    $periodo < $inicio || ($fin !== null && $periodo > $fin) => [self::MES_SIN_SERVICIO, 'Sin servicio', null],
                    $cuota !== null && (float) $cuota->saldo <= 0.001 => [self::MES_PAGADO, implode(', ', $recibos) ?: 'Pagado', (float) $cuota->monto_original],
                    $cuota !== null && $cuota->vencida() => [self::MES_VENCIDO, 'Pendiente desde el '.$cuota->fecha_vencimiento->copy()->addDay()->format('d/m/Y'), (float) $cuota->saldo],
                    $cuota !== null => [self::MES_PENDIENTE, 'En cobro hasta el '.$cuota->fecha_vencimiento->format('d/m/Y'), (float) $cuota->saldo],
                    $periodo < $actual => [self::MES_SIN_SERVICIO, 'Sin cuota', null],
                    $porPagar !== null && $periodo === $actual => [self::MES_PENDIENTE, 'Mes en curso · en cobro hasta el '.$venceMesActual, (float) $porPagar['monto']],
                    $porPagar !== null => [self::MES_POR_VENIR, 'Se puede adelantar', (float) $porPagar['monto']],
                    $periodo <= $tope => [self::MES_ADELANTADO, implode(', ', $recibos) ?: 'Pagado por adelantado', null],
                    default => [self::MES_POR_VENIR, 'Todavía no se puede adelantar', null],
                };

                $celdas[] = [
                    'periodo' => $periodo,
                    'mes' => TextoPresentacion::mesCorto($periodo),
                    'nombre' => TextoPresentacion::nombreMes($periodo),
                    'estado' => $estado,
                    'detalle' => $detalle,
                    'monto' => $monto !== null ? round($monto, 2) : null,
                ];
            }

            return [
                'servicio_id' => $servicio->id,
                'nombre' => $servicio->esAgua() ? 'Agua potable' : 'Alcantarillado',
                'tarifa' => round($this->mesesPorCobrar->tarifa($lote, $servicio), 2),
                'meses' => $celdas,
            ];
        })->all();
    }

    /**
     * Números de recibo emitido que pagaron cada cuota.
     *
     * @param  list<int>  $cuotaIds
     * @return array<int, list<string>>
     */
    private function recibosPorCuota(array $cuotaIds): array
    {
        if ($cuotaIds === []) {
            return [];
        }

        return PagoDetalle::query()
            ->whereIn('cuota_id', $cuotaIds)
            ->whereHas('pago', fn ($q) => $q->where('estado', Pago::ESTADO_EMITIDO))
            ->with('pago:id,numero_recibo')
            ->get(['id', 'pago_id', 'cuota_id'])
            ->groupBy('cuota_id')
            ->map(fn (Collection $filas): array => $filas->pluck('pago.numero_recibo')->filter()->unique()->values()->all())
            ->all();
    }

    /**
     * Recibos emitidos que adelantaron meses de la gestión (líneas sin cuota), por periodo.
     *
     * @return array<string, list<string>>
     */
    private function recibosDeAdelantos(LoteAfiliado $lote, Servicio $servicio, int $gestion): array
    {
        $conceptos = $servicio->esAlcantarillado()
            ? [PagoDetalle::CONCEPTO_PAGO_ALCANTARILLADO]
            : [PagoDetalle::CONCEPTO_CONSUMO_AGUA, PagoDetalle::CONCEPTO_SERVICIO_MENSUAL];

        return PagoDetalle::query()
            ->whereNull('cuota_id')
            ->whereIn('concepto', $conceptos)
            ->where('periodo', 'like', $gestion.'-%')
            ->where(fn ($q) => $q->where('servicio_id', $servicio->id)->orWhereNull('servicio_id'))
            ->whereHas('pago', fn ($q) => $q
                ->where('lote_afiliado_id', $lote->id)
                ->where('estado', Pago::ESTADO_EMITIDO))
            ->with('pago:id,numero_recibo')
            ->get(['id', 'pago_id', 'periodo'])
            ->groupBy('periodo')
            ->map(fn (Collection $filas): array => $filas->pluck('pago.numero_recibo')->filter()->unique()->values()->all())
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recibos(LoteAfiliado $lote): array
    {
        return Pago::query()
            ->with(['detalles.servicio', 'anuladoPor:id,name'])
            ->where('lote_afiliado_id', $lote->id)
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (Pago $pago): array {
                $cuenta = $pago->proposito
                    ? ['key' => (string) $pago->proposito, 'label' => (string) ($pago->proposito_label ?: $pago->proposito), 'tipo' => (string) ($pago->tipo_recibo ?: ReciboPropositoService::TIPO_CAJA)]
                    : $this->reciboProposito->clasificarPagoDesdeDetalles($pago->detalles);
                $fecha = $pago->fecha_pago->format('d/m/Y');
                $lineas = TextoPresentacion::agruparLineasRecibo($pago->detalles->map(fn (PagoDetalle $d): array => [
                    'texto' => $this->reciboProposito->textoConcepto($d, $cuenta, $fecha),
                    'monto' => (float) $d->monto,
                    'periodo' => $d->periodo,
                ])->all());

                return [
                    'id' => $pago->id,
                    'numero_recibo' => $pago->numero_recibo,
                    'fecha' => $fecha,
                    'tipo_recibo' => $cuenta['tipo'],
                    'concepto' => implode(' · ', array_map(fn (array $l): string => (string) $l['texto'], $lineas)),
                    'monto_total' => (float) $pago->monto_total,
                    'estado' => $pago->estado,
                    'motivo_anulacion' => $pago->motivo_anulacion,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function multas(LoteAfiliado $lote): array
    {
        return Multa::query()
            ->with('trabajo.otb')
            ->where('lote_afiliado_id', $lote->id)
            ->whereIn('estado', [Multa::ESTADO_PENDIENTE, Multa::ESTADO_PAGADA])
            ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END")
            ->orderByDesc('fecha_generacion')
            ->limit(50)
            ->get()
            ->map(fn (Multa $multa): array => [
                'id' => $multa->id,
                'titulo' => MultaTexto::titulo($multa),
                'detalle' => MultaTexto::detalle($multa),
                'monto' => (float) ($multa->estado === Multa::ESTADO_PENDIENTE ? ($multa->saldo ?? $multa->monto) : $multa->monto),
                'estado' => $multa->estado,
            ])
            ->all();
    }
}
