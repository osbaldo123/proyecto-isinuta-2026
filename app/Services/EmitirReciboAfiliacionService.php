<?php

namespace App\Services;

use App\Models\Afiliado;
use App\Models\LoteAfiliado;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Emite recibos de afiliación (agua / alcantarillado) al registrar un lote,
 * incluyendo categoría y forma de pago (contado / plazo).
 */
class EmitirReciboAfiliacionService
{
    /**
     * @return array<int, Pago>
     */
    public function emitirSiCorresponde(Afiliado $afiliado, LoteAfiliado $lote, ?User $cobrador = null): array
    {
        $lote->loadMissing(['categoria', 'otb']);

        if ($this->yaTieneRecibosAfiliacion($lote)) {
            return [];
        }

        $lineas = $this->lineasCobro($lote);

        if ($lineas === []) {
            return [];
        }

        $forma = $this->labelFormaPago((string) $lote->forma_pago_afiliacion);
        $categoria = $lote->categoria?->nombre
            ?? LoteAfiliado::labelCategoria($lote->categoria_propiedad);

        return DB::transaction(function () use ($afiliado, $lote, $cobrador, $lineas, $forma, $categoria): array {
            $montoTotal = round(collect($lineas)->sum('monto'), 2);

            if ($montoTotal <= 0) {
                return [];
            }

            $pago = Pago::create([
                'numero_recibo' => Pago::generarNumeroRecibo(),
                'tipo_recibo' => Pago::TIPO_CAJA,
                'proposito' => 'afiliacion',
                'proposito_label' => 'Afiliación',
                'afiliado_id' => $afiliado->id,
                'lote_afiliado_id' => $lote->id,
                'fecha_pago' => optional($lote->fecha_afiliacion)?->toDateString() ?? now()->toDateString(),
                'monto_total' => $montoTotal,
                'metodo_pago' => Pago::METODO_EFECTIVO,
                'estado' => Pago::ESTADO_EMITIDO,
                'cobrado_por' => $cobrador?->id,
                'observaciones' => trim(implode(' · ', array_filter([
                    ($categoria !== '' && $categoria !== '—') ? "Categoría: {$categoria}" : null,
                    "Forma de pago: {$forma}",
                ]))),
            ]);

            foreach ($lineas as $detalle) {
                $pago->detalles()->create($detalle);
            }

            return [$pago];
        });
    }

    /**
     * Completa o unifica recibos de afiliación del lote (alta antigua o mal clasificados).
     *
     * @return array<int, Pago>
     */
    public function asegurarRecibosFaltantes(Afiliado $afiliado, LoteAfiliado $lote, ?User $cobrador = null): array
    {
        $lote->loadMissing(['categoria', 'otb']);

        $unificado = $this->unificarRecibosAfiliacionExistentes($lote);

        if ($unificado !== null) {
            return [$unificado];
        }

        return $this->emitirSiCorresponde($afiliado, $lote, $cobrador);
    }

    /**
     * Si ya hay líneas de afiliación en varios recibos (o con propósito incorrecto),
     * las concentra en un solo recibo «Afiliación».
     */
    private function unificarRecibosAfiliacionExistentes(LoteAfiliado $lote): ?Pago
    {
        $pagos = Pago::query()
            ->with('detalles')
            ->where('lote_afiliado_id', $lote->id)
            ->where('estado', '!=', Pago::ESTADO_ANULADO)
            ->whereHas('detalles', function ($query): void {
                $query->where(function ($q): void {
                    $q->whereIn('concepto', [
                        PagoDetalle::CONCEPTO_AFILIACION,
                        PagoDetalle::CONCEPTO_AFILIACION_AGUA,
                        PagoDetalle::CONCEPTO_AFILIACION_ALCANTARILLADO,
                    ])->orWhere('concepto', 'like', 'afiliacion_%');
                });
            })
            ->orderBy('id')
            ->get();

        if ($pagos->isEmpty()) {
            return null;
        }

        $yaUnico = $pagos->count() === 1
            && $pagos->first()->proposito === 'afiliacion'
            && $pagos->first()->detalles->every(
                fn (PagoDetalle $d): bool => PagoDetalle::esConceptoAfiliacion((string) $d->concepto)
                    || str_starts_with((string) $d->concepto, 'afiliacion_'),
            );

        if ($yaUnico) {
            $pago = $pagos->first();
            $this->limpiarDescripcionesAfiliacion($pago);
            $this->asegurarObservacionesAfiliacion($lote, $pago);

            return $pago->fresh(['detalles']);
        }

        return DB::transaction(function () use ($lote, $pagos): Pago {
            $principal = $pagos->first();
            $forma = $this->labelFormaPago((string) $lote->forma_pago_afiliacion);
            $categoria = $lote->categoria?->nombre
                ?? LoteAfiliado::labelCategoria($lote->categoria_propiedad);

            foreach ($pagos->skip(1) as $extra) {
                foreach ($extra->detalles as $detalle) {
                    if (PagoDetalle::esConceptoAfiliacion((string) $detalle->concepto)
                        || str_starts_with((string) $detalle->concepto, 'afiliacion_')) {
                        $detalle->update(['pago_id' => $principal->id]);
                    }
                }

                $extra->refresh();
                if ($extra->detalles()->count() === 0) {
                    $extra->delete();
                } else {
                    $extra->update([
                        'monto_total' => round((float) $extra->detalles()->sum('monto'), 2),
                    ]);
                }
            }

            $principal->refresh()->load('detalles');

            foreach ($principal->detalles as $detalle) {
                $this->limpiarDescripcionDetalle($detalle);
            }

            $montoTotal = round((float) $principal->detalles()->sum('monto'), 2);

            $principal->update([
                'tipo_recibo' => Pago::TIPO_CAJA,
                'proposito' => 'afiliacion',
                'proposito_label' => 'Afiliación',
                'monto_total' => $montoTotal,
                'observaciones' => trim(implode(' · ', array_filter([
                    ($categoria !== '' && $categoria !== '—') ? "Categoría: {$categoria}" : null,
                    "Forma de pago: {$forma}",
                ]))),
            ]);

            return $principal->fresh(['detalles']);
        });
    }

    private function limpiarDescripcionesAfiliacion(Pago $pago): void
    {
        foreach ($pago->detalles as $detalle) {
            $this->limpiarDescripcionDetalle($detalle);
        }
    }

    private function limpiarDescripcionDetalle(PagoDetalle $detalle): void
    {
        $concepto = (string) $detalle->concepto;
        if (! PagoDetalle::esConceptoAfiliacion($concepto) && ! str_starts_with($concepto, 'afiliacion_')) {
            return;
        }

        $limpia = match ($concepto) {
            PagoDetalle::CONCEPTO_AFILIACION_AGUA => 'Afiliación agua potable',
            PagoDetalle::CONCEPTO_AFILIACION_ALCANTARILLADO => 'Afiliación alcantarillado',
            PagoDetalle::CONCEPTO_AFILIACION => 'Afiliación',
            default => preg_replace('/\s*·\s*Categoría:.*$/u', '', (string) $detalle->descripcion) ?: (string) $detalle->descripcion,
        };

        if ((string) $detalle->descripcion !== $limpia) {
            $detalle->update(['descripcion' => $limpia]);
        }
    }

    private function asegurarObservacionesAfiliacion(LoteAfiliado $lote, Pago $pago): void
    {
        if (filled($pago->observaciones) && str_contains((string) $pago->observaciones, 'Forma de pago:')) {
            return;
        }

        $forma = $this->labelFormaPago((string) $lote->forma_pago_afiliacion);
        $categoria = $lote->categoria?->nombre
            ?? LoteAfiliado::labelCategoria($lote->categoria_propiedad);

        $pago->update([
            'observaciones' => trim(implode(' · ', array_filter([
                ($categoria !== '' && $categoria !== '—') ? "Categoría: {$categoria}" : null,
                "Forma de pago: {$forma}",
            ]))),
        ]);
    }

    private function yaTieneRecibosAfiliacion(LoteAfiliado $lote): bool
    {
        return Pago::query()
            ->where('lote_afiliado_id', $lote->id)
            ->where('estado', '!=', Pago::ESTADO_ANULADO)
            ->whereHas('detalles', function ($query): void {
                $query->where(function ($q): void {
                    $q->whereIn('concepto', [
                        PagoDetalle::CONCEPTO_AFILIACION,
                        PagoDetalle::CONCEPTO_AFILIACION_AGUA,
                        PagoDetalle::CONCEPTO_AFILIACION_ALCANTARILLADO,
                    ])->orWhere('concepto', 'like', 'afiliacion_%');
                });
            })
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lineasCobro(LoteAfiliado $lote): array
    {
        $montoAgua = round((float) ($lote->monto_afiliacion_agua ?? 0), 2);
        $montoAlc = round((float) ($lote->monto_afiliacion_alcantarillado ?? 0), 2);
        $montoTotal = round($montoAgua + $montoAlc, 2);
        $soloTotal = false;

        if ($montoTotal <= 0) {
            $montoTotal = round((float) ($lote->monto_afiliacion ?? 0), 2);
            if ($montoTotal <= 0) {
                return [];
            }
            $soloTotal = true;
            $montoAgua = $montoTotal;
            $montoAlc = 0;
        }

        $forma = (string) $lote->forma_pago_afiliacion;
        $saldo = round((float) ($lote->saldo_afiliacion ?? 0), 2);
        $pagado = $forma === Afiliado::FORMA_CONTADO
            ? $montoTotal
            : max(0, round($montoTotal - $saldo, 2));

        // Contado: recibo por el total cobrado al registrar.
        // Plazo: solo si hubo abono inicial; el resto se registra al cobrar cuotas.
        if ($forma === Afiliado::FORMA_PLAZO && $pagado <= 0) {
            return [];
        }

        $baseAgua = $montoAgua;
        $baseAlc = $montoAlc;

        if ($forma === Afiliado::FORMA_PLAZO && $pagado > 0 && $pagado < $montoTotal) {
            $ratio = $montoTotal > 0 ? $pagado / $montoTotal : 0;
            $baseAgua = round($montoAgua * $ratio, 2);
            $baseAlc = round($pagado - $baseAgua, 2);
        }

        if ($soloTotal) {
            return [[
                'concepto' => PagoDetalle::CONCEPTO_AFILIACION,
                'descripcion' => 'Afiliación',
                'monto' => $pagado > 0 && $pagado < $montoTotal ? $pagado : $montoTotal,
                'servicio_id' => null,
                'multa_id' => null,
                'periodo' => null,
            ]];
        }

        $lineas = [];

        if ($baseAgua > 0) {
            $lineas[] = [
                'concepto' => PagoDetalle::CONCEPTO_AFILIACION_AGUA,
                'descripcion' => 'Afiliación agua potable',
                'monto' => $baseAgua,
                'servicio_id' => null,
                'multa_id' => null,
                'periodo' => null,
            ];
        }

        if ($baseAlc > 0) {
            $lineas[] = [
                'concepto' => PagoDetalle::CONCEPTO_AFILIACION_ALCANTARILLADO,
                'descripcion' => 'Afiliación alcantarillado',
                'monto' => $baseAlc,
                'servicio_id' => null,
                'multa_id' => null,
                'periodo' => null,
            ];
        }

        return $lineas;
    }

    private function labelFormaPago(string $forma): string
    {
        return match ($forma) {
            Afiliado::FORMA_CONTADO => 'Al contado',
            Afiliado::FORMA_PLAZO => 'A plazo',
            default => $forma !== '' ? ucfirst($forma) : 'No indicada',
        };
    }
}
