<?php

namespace App\Services;

use App\Models\Afiliado;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeudaAfiliadoService
{
    /**
     * Resumen de deuda para listados y panel.
     *
     * @return array{
     *   total_deuda: float,
     *   total_cuotas_pendientes: float,
     *   total_multas_pendientes: float,
     *   saldo_afiliacion: float,
     *   meses_pendientes: int,
     *   meses_esperados: int,
     *   meses_pagados: int,
     *   tarifa_mensual: float
     * }
     */
    public function calcularDeudaResumen(Afiliado $afiliado): array
    {
        $afiliado->loadMissing(['lotes.serviciosActivos', 'lotes.categoria']);

        $lotes = $this->lotesParaDeuda($afiliado);
        $emisor = app(CuotaMensualService::class);
        foreach ($lotes as $lote) {
            if ($lote->estado === Afiliado::ESTADO_BAJA) {
                continue;
            }
            $emisor->emitirLote($lote);
        }

        $cuotas = $lotes->isEmpty()
            ? collect()
            : Cuota::query()->whereIn('lote_afiliado_id', $lotes->pluck('id'))->get();

        $tarifaMensual = $this->tarifaMensualAfiliado($afiliado);
        $hoy = now()->toDateString();

        if ($cuotas->isNotEmpty()) {
            $mesesEsperados = 0;
            $mesesPagados = 0;
            $mesesPendientes = 0;

            foreach ($lotes as $lote) {
                $porPeriodo = $cuotas->where('lote_afiliado_id', $lote->id)->groupBy('periodo');
                $mesesEsperados = max($mesesEsperados, $porPeriodo->count());
                $pagadosLote = $porPeriodo
                    ->filter(fn ($rows) => (float) $rows->sum(fn (Cuota $cuota): float => (float) $cuota->saldo) <= 0.001)
                    ->count();
                $vencidosLote = $porPeriodo->filter(function ($rows) use ($hoy): bool {
                    return $rows->contains(fn (Cuota $cuota): bool => (float) $cuota->saldo > 0.001
                        && $cuota->fecha_vencimiento->toDateString() < $hoy);
                })->count();
                $mesesPagados = max($mesesPagados, $pagadosLote);
                $mesesPendientes = max($mesesPendientes, $vencidosLote);
            }

            $totalCuotasPendientes = round((float) $cuotas->sum(fn (Cuota $cuota): float => (float) $cuota->saldo), 2);
        } else {
            $mesesEsperados = $this->mesesEsperados($afiliado);
            $mesesPagados = $this->mesesPagadosServicio($afiliado);
            $mesesPendientes = max(0, $mesesEsperados - $mesesPagados);
            $totalCuotasPendientes = round($tarifaMensual * $mesesPendientes, 2);
        }
        $totalMultasPendientes = round((float) $this->multasPendientesDelContexto($afiliado)
            ->get()
            ->sum(fn (Multa $multa): float => (float) ($multa->saldo ?? $multa->monto)), 2);
        $saldoAfiliacion = (float) $afiliado->saldo_afiliacion;

        return [
            'total_deuda' => round($totalCuotasPendientes + $totalMultasPendientes + $saldoAfiliacion, 2),
            'total_cuotas_pendientes' => $totalCuotasPendientes,
            'total_multas_pendientes' => $totalMultasPendientes,
            'saldo_afiliacion' => $saldoAfiliacion,
            'meses_pendientes' => $mesesPendientes,
            'meses_esperados' => $mesesEsperados,
            'meses_pagados' => $mesesPagados,
            'tarifa_mensual' => $tarifaMensual,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function calcular(Afiliado $afiliado): array
    {
        $afiliado->loadMissing(['lotes.otb', 'lotes.serviciosActivos']);
        $resumen = $this->calcularDeudaResumen($afiliado);

        return [
            'afiliado' => [
                'id' => $afiliado->id,
                'nombre_completo' => $afiliado->nombre_completo,
                'identidad' => $afiliado->identidadDocumento(),
                'numero_lote' => $afiliado->numero_lote,
                'codigo_propiedad' => $afiliado->codigoPropiedadDisplay(),
                'ci' => $afiliado->ci,
                'estado' => $afiliado->estado,
                'otb' => $afiliado->otb?->nombre,
            ],
            'tarifa_mensual' => $resumen['tarifa_mensual'],
            'meses_esperados' => $resumen['meses_esperados'],
            'meses_pagados' => $resumen['meses_pagados'],
            'meses_pendientes' => $resumen['meses_pendientes'],
            'total_cuotas_pendientes' => $resumen['total_cuotas_pendientes'],
            'total_multas_pendientes' => $resumen['total_multas_pendientes'],
            'saldo_afiliacion' => $resumen['saldo_afiliacion'],
            'total_deuda' => $resumen['total_deuda'],
            'pagos_recientes' => $this->pagosRecientes($afiliado),
            'multas_pendientes' => $this->multasPendientesDelContexto($afiliado)
                ->with(['trabajo.otb', 'lote.otb'])
                ->latest('fecha_generacion')
                ->get()
                ->map(fn (Multa $multa): array => [
                    'id' => $multa->id,
                    'fecha_generacion' => $multa->fecha_generacion->format('Y-m-d'),
                    'motivo' => $multa->motivo,
                    'descripcion' => $multa->descripcion,
                    'monto' => (float) ($multa->saldo ?? $multa->monto),
                    'concepto' => $this->conceptoCobroMulta($multa),
                    'etiqueta' => $this->etiquetaMulta($multa),
                ])
                ->all(),
        ];
    }

    public function generarMultasAutomaticas(Afiliado $afiliado, ?User $usuario = null): int
    {
        return app(CalculadorMultasService::class)->evaluarYGenerar($afiliado, $usuario);
    }

    public function conceptoCobroMulta(Multa $multa): string
    {
        if ($multa->motivo !== Multa::MOTIVO_FALTA_TRABAJO) {
            return PagoDetalle::CONCEPTO_MULTA;
        }

        $texto = strtr(mb_strtolower(trim(($multa->trabajo?->tipo ?? '').' '.($multa->descripcion ?? ''))), [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $esReunion = str_contains($texto, 'reunion');
        $esAlcantarillado = str_contains($texto, 'alcantarillado');
        $esAgua = str_contains($texto, 'agua');

        if ($esReunion && $esAlcantarillado) {
            return PagoDetalle::CONCEPTO_REUNIONES_ALCANTARILLADO;
        }

        if ($esReunion && $esAgua) {
            return PagoDetalle::CONCEPTO_REUNIONES_AGUA;
        }

        if ($esAlcantarillado) {
            return PagoDetalle::CONCEPTO_PAGO_TRABAJO_ALCANTARILLADO;
        }

        if ($esAgua) {
            return PagoDetalle::CONCEPTO_PAGO_TRABAJO_AGUA;
        }

        return PagoDetalle::CONCEPTO_PAGO_TRABAJO;
    }

    private function etiquetaMulta(Multa $multa): string
    {
        $trabajo = $multa->trabajo;
        $lote = $multa->lote;
        $loteTexto = filled($lote?->numero_lote) ? 'lote '.$lote->numero_lote : '';
        $otb = trim((string) ($trabajo?->otb?->nombre ?? $lote?->otb?->nombre ?? ''));

        if ($trabajo) {
            $tipo = trim((string) $trabajo->tipo);

            return trim(implode(', ', array_filter([
                trim($tipo.($otb !== '' ? " {$otb}" : '')),
                $loteTexto,
            ])));
        }

        return match ($multa->motivo) {
            Multa::MOTIVO_MORA_TRIMESTRAL => 'Mora de 3 meses',
            Multa::MOTIVO_MORA_ANUAL => 'Mora de 1 año',
            Multa::MOTIVO_INDIVIDUAL => filled($multa->descripcion) ? (string) $multa->descripcion : 'Multa individual',
            default => 'Multa',
        };
    }

    private function multasPendientesDelContexto(Afiliado $afiliado): HasMany
    {
        $query = $afiliado->multasPendientes();

        if ($afiliado->relationLoaded('loteContexto')) {
            $lote = $afiliado->getRelation('loteContexto');

            if ($lote instanceof LoteAfiliado) {
                $query->where('lote_afiliado_id', $lote->id);
            }
        }

        return $query;
    }

    /**
     * @return \Illuminate\Support\Collection<int, LoteAfiliado>
     */
    private function lotesParaDeuda(Afiliado $afiliado): \Illuminate\Support\Collection
    {
        if ($afiliado->relationLoaded('loteContexto') && $afiliado->getRelation('loteContexto') instanceof LoteAfiliado) {
            return collect([$afiliado->getRelation('loteContexto')]);
        }

        return $afiliado->lotes;
    }

    private function tarifaMensualAfiliado(Afiliado $afiliado): float
    {
        $lote = $afiliado->loteEnContexto();
        $lote?->loadMissing('serviciosActivos');

        return round((float) ($lote?->serviciosActivos ?? collect())->sum(
            fn ($servicio): float => (float) ($servicio->pivot->tarifa_acordada ?? $servicio->tarifa_mensual)
        ), 2);
    }

    private function mesesEsperados(Afiliado $afiliado): int
    {
        $inicio = Carbon::parse($afiliado->fecha_afiliacion)->startOfMonth();
        $fin = now()->startOfMonth();

        if ($inicio->gt($fin)) {
            return 0;
        }

        return (int) $inicio->diffInMonths($fin) + 1;
    }

    private function mesesPagadosServicio(Afiliado $afiliado): int
    {
        return (int) PagoDetalle::query()
            ->whereIn('concepto', PagoDetalle::conceptosServicioMensual())
            ->whereHas('pago', function ($query) use ($afiliado): void {
                $query->where('afiliado_id', $afiliado->id)
                    ->where('estado', Pago::ESTADO_EMITIDO);
            })
            ->whereNotNull('periodo')
            ->distinct()
            ->count('periodo');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pagosRecientes(Afiliado $afiliado): array
    {
        return Pago::query()
            ->with('detalles')
            ->where('afiliado_id', $afiliado->id)
            ->where('estado', Pago::ESTADO_EMITIDO)
            ->latest('fecha_pago')
            ->limit(5)
            ->get()
            ->map(fn (Pago $pago): array => [
                'id' => $pago->id,
                'numero_recibo' => $pago->numero_recibo,
                'fecha_pago' => $pago->fecha_pago->format('d/m/Y'),
                'monto_total' => (float) $pago->monto_total,
                'conceptos' => $pago->detalles
                    ->map(fn (PagoDetalle $detalle): string => trim($detalle->descripcion ?: $detalle->concepto))
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->all();
    }
}
