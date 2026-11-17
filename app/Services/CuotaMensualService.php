<?php

namespace App\Services;

use App\Models\Afiliado;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Servicio;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CuotaMensualService
{
    /**
     * Día del mes siguiente hasta el cual la cuota está «en cobro» (días 1 y 2).
     * Desde el día 3, si no pagó, queda pendiente (deuda del mes de servicio).
     * Tras 3 meses pendientes: el día 1 del mes siguiente se aplica la multa Bs 50 (luego 100 / 150).
     */
    public const DIA_LIMITE_PAGO_MES_SIGUIENTE = 2;

    /** Fecha límite de pago del periodo (día 2 del mes siguiente). */
    public static function fechaVencimientoDelPeriodo(string $periodo): string
    {
        return Carbon::createFromFormat('Y-m-d', $periodo.'-01')
            ->addMonth()
            ->day(self::DIA_LIMITE_PAGO_MES_SIGUIENTE)
            ->toDateString();
    }

    /**
     * Emite las cuotas de los meses ya cerrados. El mes en curso no se genera.
     * Un periodo ya emitido no se vuelve a crear.
     */
    public function emitirLote(LoteAfiliado $lote): int
    {
        if ($lote->estado === Afiliado::ESTADO_BAJA || $lote->fecha_afiliacion === null) {
            return 0;
        }

        $lote->loadMissing(['categoria']);
        // Todos los servicios del lote (también los que ya terminaron): sus meses activos deben cobrarse.
        $servicios = $lote->servicios()->where('servicios.activo', true)->get();
        $ultimoCerrado = now()->startOfMonth()->subMonth();
        $inicioLote = Carbon::parse($lote->fecha_afiliacion)->startOfMonth();

        if ($inicioLote->gt($ultimoCerrado) || $servicios->isEmpty()) {
            return 0;
        }

        $existentes = Cuota::query()
            ->where('lote_afiliado_id', $lote->id)
            ->get(['servicio_id', 'periodo'])
            ->mapWithKeys(fn (Cuota $cuota): array => [$cuota->servicio_id.'|'.$cuota->periodo => true]);

        $creadas = 0;

        foreach ($servicios as $servicio) {
            $inicio = $inicioLote->copy();
            if ($servicio->pivot?->fecha_inicio) {
                $inicioServicio = Carbon::parse($servicio->pivot->fecha_inicio)->startOfMonth();
                if ($inicioServicio->gt($inicio)) {
                    $inicio = $inicioServicio;
                }
            }

            $fin = $ultimoCerrado->copy();
            if ($servicio->pivot?->fecha_fin) {
                $finServicio = Carbon::parse($servicio->pivot->fecha_fin)->startOfMonth();
                if ($finServicio->lt($fin)) {
                    $fin = $finServicio;
                }
            }

            $cursor = $inicio->copy();
            while ($cursor->lte($fin)) {
                $periodo = $cursor->format('Y-m');
                $clave = $servicio->id.'|'.$periodo;

                if (! isset($existentes[$clave])) {
                    $monto = $this->montoCongelado($lote, $servicio);
                    if ($monto > 0) {
                        $pagado = $this->montoYaCobrado($lote->id, $servicio, $periodo);
                        $saldo = round(max(0, $monto - $pagado), 2);

                        Cuota::query()->create([
                            'lote_afiliado_id' => $lote->id,
                            'servicio_id' => $servicio->id,
                            'periodo' => $periodo,
                            'gestion' => (int) $cursor->year,
                            'monto_original' => $monto,
                            'saldo' => $saldo,
                            'fecha_vencimiento' => self::fechaVencimientoDelPeriodo($periodo),
                            'estado' => $saldo <= 0 ? Cuota::ESTADO_PAGADA : Cuota::ESTADO_PENDIENTE,
                        ]);
                        $existentes[$clave] = true;
                        $creadas++;
                    }
                }

                $cursor->addMonth();
            }
        }

        return $creadas;
    }

    public function emitirPendientes(): int
    {
        $creadas = 0;

        LoteAfiliado::query()
            ->with(['serviciosActivos', 'categoria'])
            ->where('estado', '!=', Afiliado::ESTADO_BAJA)
            ->orderBy('id')
            ->chunkById(80, function ($lotes) use (&$creadas): void {
                foreach ($lotes as $lote) {
                    $creadas += $this->emitirLote($lote);
                }
            });

        return $creadas;
    }

    /**
     * Meses adeudados: periodos con saldo pendiente y ya vencidos, de cualquier gestión.
     *
     * @param  Collection<int, Cuota>  $cuotas
     * @return list<string> Periodos 'Y-m' del más antiguo al más reciente
     */
    public function periodosVencidos(Collection $cuotas): array
    {
        $hoy = now()->toDateString();

        return $cuotas
            ->filter(fn (Cuota $cuota): bool => (float) $cuota->saldo > 0
                && $cuota->fecha_vencimiento->toDateString() < $hoy)
            ->pluck('periodo')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Elimina cuotas que quedaron ANTES del inicio de cobro (fecha de afiliación o inicio del
     * servicio) tras una corrección, solo si nunca recibieron pagos. Devuelve cuántas se eliminaron.
     */
    public function depurarCuotasFueraDeVigencia(LoteAfiliado $lote): int
    {
        $lote->load('servicios');
        $inicioLote = $lote->fecha_afiliacion
            ? Carbon::parse($lote->fecha_afiliacion)->startOfMonth()->format('Y-m')
            : null;

        $candidatas = Cuota::query()
            ->where('lote_afiliado_id', $lote->id)
            ->whereColumn('saldo', 'monto_original')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('pago_detalles')
                ->whereColumn('pago_detalles.cuota_id', 'cuotas.id'))
            ->get();

        $eliminadas = 0;

        foreach ($candidatas as $cuota) {
            $servicio = $lote->servicios->firstWhere('id', $cuota->servicio_id);

            if (! $servicio) {
                continue; // servicio retirado: su deuda histórica se conserva
            }

            $inicioServicio = $servicio->pivot?->fecha_inicio
                ? Carbon::parse($servicio->pivot->fecha_inicio)->startOfMonth()->format('Y-m')
                : $inicioLote;
            $inicio = max((string) $inicioLote, (string) $inicioServicio);

            if ($cuota->periodo < $inicio) {
                $cuota->delete();
                $eliminadas++;
            }
        }

        return $eliminadas;
    }

    /**
     * Mensualidad vigente del servicio para el lote (la misma que se usará al emitir la cuota).
     */
    public function tarifaMensual(LoteAfiliado $lote, int $servicioId): float
    {
        $servicio = $lote->servicios()->where('servicios.id', $servicioId)->first();

        return $servicio ? $this->montoCongelado($lote, $servicio) : 0.0;
    }

    private function montoCongelado(LoteAfiliado $lote, Servicio $servicio): float
    {
        $acordada = $servicio->pivot->tarifa_acordada ?? null;
        if ($acordada !== null && (float) $acordada > 0) {
            return round((float) $acordada, 2);
        }

        if ($lote->categoria) {
            $monto = $servicio->esAlcantarillado()
                ? (float) $lote->categoria->tarifa_alcantarillado
                : (float) $lote->categoria->tarifa_agua;

            if ($monto > 0) {
                return round($monto, 2);
            }
        }

        return round((float) $servicio->tarifa_mensual, 2);
    }

    private function montoYaCobrado(int $loteId, Servicio $servicio, string $periodo): float
    {
        $conceptos = $servicio->esAlcantarillado()
            ? [PagoDetalle::CONCEPTO_PAGO_ALCANTARILLADO]
            : [PagoDetalle::CONCEPTO_CONSUMO_AGUA, PagoDetalle::CONCEPTO_SERVICIO_MENSUAL];

        return round((float) PagoDetalle::query()
            ->where('periodo', $periodo)
            ->whereIn('concepto', $conceptos)
            ->where(function ($query) use ($servicio): void {
                $query->where('servicio_id', $servicio->id)->orWhereNull('servicio_id');
            })
            ->whereHas('pago', function ($query) use ($loteId): void {
                $query->where('lote_afiliado_id', $loteId)
                    ->where('estado', Pago::ESTADO_EMITIDO);
            })
            ->sum('monto'), 2);
    }
}
