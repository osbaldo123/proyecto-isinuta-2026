<?php

use App\Models\Pago;
use App\Models\PagoDetalle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El recibo de mensualidad de alcantarillado es de ingreso (como el agua).
 * Caja queda para multas, afiliación y demás. Corrige recibos ya emitidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Pago::query()
            ->where('tipo_recibo', Pago::TIPO_CAJA)
            ->where(function ($q): void {
                $q->where('proposito', 'servicio_alcantarillado')
                    ->orWhere('proposito', 'like', 'servicio_alcantarillado:%');
            })
            ->update(['tipo_recibo' => Pago::TIPO_INGRESO]);

        // Recibos sin propósito claro pero con detalle de cuota de alcantarillado.
        $ids = DB::table('pago_detalles')
            ->where('concepto', PagoDetalle::CONCEPTO_PAGO_ALCANTARILLADO)
            ->pluck('pago_id')
            ->unique()
            ->all();

        if ($ids === []) {
            return;
        }

        Pago::query()
            ->whereIn('id', $ids)
            ->where('tipo_recibo', Pago::TIPO_CAJA)
            ->where(function ($q): void {
                $q->whereNull('proposito')
                    ->orWhere('proposito', '')
                    ->orWhere('proposito', 'servicio_alcantarillado')
                    ->orWhere('proposito', 'like', 'servicio_alcantarillado:%');
            })
            ->update(['tipo_recibo' => Pago::TIPO_INGRESO]);
    }

    public function down(): void
    {
        Pago::query()
            ->where('tipo_recibo', Pago::TIPO_INGRESO)
            ->where(function ($q): void {
                $q->where('proposito', 'servicio_alcantarillado')
                    ->orWhere('proposito', 'like', 'servicio_alcantarillado:%');
            })
            ->update(['tipo_recibo' => Pago::TIPO_CAJA]);
    }
};
