<?php

namespace App\Services\Pagos;

use App\Models\Afiliado;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Servicio;
use App\Services\CuotaMensualService;
use App\Support\TextoPresentacion;
use Carbon\Carbon;

/**
 * Meses que se pueden cobrar de un servicio de un lote, del más antiguo al más reciente:
 * primero las cuotas con saldo (vencidas o por vencer) y después el mes en curso y los
 * adelantos, hasta 12 meses después del mes actual. Cobrar siempre en este orden evita
 * que queden meses viejos sin pagar.
 *
 * Antes de calcular emite las cuotas de los meses cerrados que falten, para que la
 * pantalla, la validación y el cobro vean los mismos meses.
 */
class MesesPorCobrarService
{
    public const MESES_ADELANTO = 12;

    public const ESTADO_VENCIDO = 'vencido';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ACTUAL = 'actual';

    public const ESTADO_ADELANTO = 'adelanto';

    public const ESTADO_PAGADO = 'pagado';

    /** Cuotas ya pagadas que se muestran en verde (historial reciente en la grilla de cobro). */
    public const MESES_PAGADOS_VISIBLES = 6;

    public function __construct(private readonly CuotaMensualService $cuotas) {}

    /**
     * @return list<array{periodo: string, etiqueta: string, corto: string, monto: float, cuota_id: int|null, estado: string}>
     */
    public function paraServicio(LoteAfiliado $lote, Servicio $servicio): array
    {
        if ($lote->estado === Afiliado::ESTADO_BAJA || $lote->fecha_afiliacion === null) {
            return [];
        }

        $this->cuotas->emitirLote($lote);

        $meses = Cuota::query()
            ->where('lote_afiliado_id', $lote->id)
            ->where('servicio_id', $servicio->id)
            ->where('saldo', '>', 0)
            ->orderBy('periodo')
            ->get()
            ->map(fn (Cuota $cuota): array => $this->mes(
                $cuota->periodo,
                (float) $cuota->saldo,
                $cuota->id,
                $cuota->vencida() ? self::ESTADO_VENCIDO : self::ESTADO_PENDIENTE,
            ))
            ->all();

        $vinculo = $lote->servicios()->where('servicios.id', $servicio->id)->first();
        $tarifa = $vinculo ? $this->cuotas->tarifaMensual($lote, $servicio->id) : 0.0;

        if (! $vinculo || $tarifa <= 0) {
            return $meses;
        }

        $mesActual = now()->startOfMonth();
        $inicio = $mesActual->copy();

        $ultima = Cuota::query()
            ->where('lote_afiliado_id', $lote->id)
            ->where('servicio_id', $servicio->id)
            ->max('periodo');

        if ($ultima) {
            $inicio = $this->primerDia($ultima)->addMonth();
        }

        foreach ([$vinculo->pivot?->fecha_inicio, $lote->fecha_afiliacion] as $desde) {
            if ($desde) {
                $desde = Carbon::parse($desde)->startOfMonth();
                if ($desde->gt($inicio)) {
                    $inicio = $desde;
                }
            }
        }

        $tope = $mesActual->copy()->addMonths(self::MESES_ADELANTO);
        if ($vinculo->pivot?->fecha_fin) {
            $fin = Carbon::parse($vinculo->pivot->fecha_fin)->startOfMonth();
            if ($fin->lt($tope)) {
                $tope = $fin;
            }
        }

        $adelantado = $this->adelantadoPorPeriodo($lote, $servicio, $inicio->format('Y-m'));
        $actual = $mesActual->format('Y-m');

        for ($cursor = $inicio->copy(); $cursor->lte($tope); $cursor->addMonth()) {
            $periodo = $cursor->format('Y-m');
            $resta = round($tarifa - ($adelantado[$periodo] ?? 0.0), 2);

            if ($resta <= 0.001) {
                continue;
            }

            $estado = match (true) {
                $periodo < $actual => self::ESTADO_PENDIENTE,
                $periodo === $actual => self::ESTADO_ACTUAL,
                default => self::ESTADO_ADELANTO,
            };

            $meses[] = $this->mes($periodo, $resta, null, $estado);
        }

        return $meses;
    }

    /**
     * Últimas cuotas ya canceladas (recibo emitido), para mostrarlas en verde y no desmarcarlas.
     *
     * @return list<array{periodo: string, etiqueta: string, corto: string, monto: float, cuota_id: int|null, estado: string}>
     */
    public function pagadosRecientes(LoteAfiliado $lote, Servicio $servicio): array
    {
        if ($lote->estado === Afiliado::ESTADO_BAJA || $lote->fecha_afiliacion === null) {
            return [];
        }

        $this->cuotas->emitirLote($lote);

        $primerPendiente = Cuota::query()
            ->where('lote_afiliado_id', $lote->id)
            ->where('servicio_id', $servicio->id)
            ->where('saldo', '>', 0)
            ->min('periodo');

        $query = Cuota::query()
            ->where('lote_afiliado_id', $lote->id)
            ->where('servicio_id', $servicio->id)
            ->where('saldo', '<=', 0)
            ->orderByDesc('periodo')
            ->limit(self::MESES_PAGADOS_VISIBLES);

        if (is_string($primerPendiente) && $primerPendiente !== '') {
            $query->where('periodo', '<', $primerPendiente);
        } else {
            $query->where('periodo', '<', now()->format('Y-m'));
        }

        return $query->get()
            ->sortBy('periodo')
            ->values()
            ->map(fn (Cuota $cuota): array => $this->mes(
                $cuota->periodo,
                (float) $cuota->monto_original,
                $cuota->id,
                self::ESTADO_PAGADO,
            ))
            ->all();
    }

    /**
     * Mensualidad vigente del servicio para el lote (la que se usa en las cuotas nuevas).
     */
    public function tarifa(LoteAfiliado $lote, Servicio $servicio): float
    {
        return $this->cuotas->tarifaMensual($lote, $servicio->id);
    }

    /**
     * Los primeros $cantidad meses por cobrar, o null si no hay tantos.
     *
     * @return list<array{periodo: string, etiqueta: string, corto: string, monto: float, cuota_id: int|null, estado: string}>|null
     */
    public function primeros(LoteAfiliado $lote, Servicio $servicio, int $cantidad): ?array
    {
        $meses = $this->paraServicio($lote, $servicio);

        return $cantidad >= 1 && $cantidad <= count($meses)
            ? array_slice($meses, 0, $cantidad)
            : null;
    }

    /**
     * Montos ya cobrados por adelantado (sin cuota todavía) desde un periodo, por periodo.
     *
     * @return array<string, float>
     */
    private function adelantadoPorPeriodo(LoteAfiliado $lote, Servicio $servicio, string $desde): array
    {
        $conceptos = $servicio->esAlcantarillado()
            ? [PagoDetalle::CONCEPTO_PAGO_ALCANTARILLADO]
            : [PagoDetalle::CONCEPTO_CONSUMO_AGUA, PagoDetalle::CONCEPTO_SERVICIO_MENSUAL];

        return PagoDetalle::query()
            ->whereNull('cuota_id')
            ->whereIn('concepto', $conceptos)
            ->where('periodo', '>=', $desde)
            ->where(fn ($q) => $q->where('servicio_id', $servicio->id)->orWhereNull('servicio_id'))
            ->whereHas('pago', fn ($q) => $q
                ->where('lote_afiliado_id', $lote->id)
                ->where('estado', Pago::ESTADO_EMITIDO))
            ->get(['periodo', 'monto'])
            ->groupBy('periodo')
            ->map(fn ($filas): float => round((float) $filas->sum('monto'), 2))
            ->all();
    }

    /**
     * @return array{periodo: string, etiqueta: string, corto: string, monto: float, cuota_id: int|null, estado: string}
     */
    private function mes(string $periodo, float $monto, ?int $cuotaId, string $estado): array
    {
        return [
            'periodo' => $periodo,
            'etiqueta' => TextoPresentacion::nombreMes($periodo),
            'corto' => TextoPresentacion::mesCorto($periodo),
            'monto' => round($monto, 2),
            'cuota_id' => $cuotaId,
            'estado' => $estado,
        ];
    }

    private function primerDia(string $periodo): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $periodo.'-01')->startOfDay();
    }
}
