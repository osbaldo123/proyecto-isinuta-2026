<?php

use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Servicio;
use Illuminate\Database\Migrations\Migration;

/**
 * Tras corregir tarifas (agua 8/17, alcantarillado comercial 20), algunas cuotas
 * pendientes quedaron con un "abonado" fantasma (p. ej. Bs 8) aunque no hay
 * recibo emitido. Eso hace que "2 meses × Bs 17" muestre Bs 26 en lugar de 34.
 * Se restaura el saldo completo cuando no existe pago real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Cuota::query()
            ->where('estado', Cuota::ESTADO_PENDIENTE)
            ->whereColumn('saldo', '<', 'monto_original')
            ->where('saldo', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($cuotas): void {
                foreach ($cuotas as $cuota) {
                    if ($this->montoPagadoReal($cuota) > 0.001) {
                        continue;
                    }

                    $cuota->update([
                        'saldo' => round((float) $cuota->monto_original, 2),
                        'estado' => Cuota::ESTADO_PENDIENTE,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Corrección de datos: no se revierte.
    }

    private function montoPagadoReal(Cuota $cuota): float
    {
        $servicio = Servicio::query()->find($cuota->servicio_id);
        $conceptos = $servicio && $servicio->esAlcantarillado()
            ? [PagoDetalle::CONCEPTO_PAGO_ALCANTARILLADO]
            : [PagoDetalle::CONCEPTO_CONSUMO_AGUA, PagoDetalle::CONCEPTO_SERVICIO_MENSUAL];

        return round((float) PagoDetalle::query()
            ->where(function ($query) use ($cuota, $conceptos): void {
                $query->where('cuota_id', $cuota->id)
                    ->orWhere(function ($q) use ($cuota, $conceptos): void {
                        $q->where('periodo', $cuota->periodo)
                            ->whereIn('concepto', $conceptos)
                            ->where(function ($q2) use ($cuota): void {
                                $q2->where('servicio_id', $cuota->servicio_id)->orWhereNull('servicio_id');
                            });
                    });
            })
            ->whereHas('pago', function ($query) use ($cuota): void {
                $query->where('lote_afiliado_id', $cuota->lote_afiliado_id)
                    ->where('estado', Pago::ESTADO_EMITIDO);
            })
            ->sum('monto'), 2);
    }
};
