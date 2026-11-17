<?php

use App\Models\Afiliado;
use App\Models\PagoDetalle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Precio oficial Isinuta (documento de mercado):
 *  - Agua: Bs 3.500 al contado / Bs 3.800 a plazo.
 *  - Alcantarillado: Bs 2.000 al contado / Bs 2.500 a plazo.
 *
 * El catálogo tenía alcantarillado a plazo en Bs 2.000 (igual al contado).
 */
return new class extends Migration
{
    public function up(): void
    {
        $plazo = PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO_PLAZO;
        $contado = PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO;

        if (Schema::hasTable('tipos_afiliacion')) {
            DB::table('tipos_afiliacion')
                ->where('codigo', 'alcantarillado')
                ->where(function ($q) use ($contado): void {
                    $q->whereNull('monto_plazo')
                        ->orWhere('monto_plazo', 0)
                        ->orWhere('monto_plazo', $contado);
                })
                ->update(['monto_plazo' => $plazo]);
        }

        if (Schema::hasTable('servicios') && Schema::hasColumn('servicios', 'costo_instalacion_plazos')) {
            DB::table('servicios')
                ->where('tipo', 'alcantarillado')
                ->where(function ($q) use ($contado): void {
                    $q->where('costo_instalacion_plazos', 0)
                        ->orWhere('costo_instalacion_plazos', $contado);
                })
                ->update(['costo_instalacion_plazos' => $plazo]);
        }

        // Lotes a plazo con el precio viejo intacto (aún no abonaron): alinear al oficial.
        if (Schema::hasTable('lote_afiliados')) {
            $lotes = DB::table('lote_afiliados')
                ->where('forma_pago_afiliacion', Afiliado::FORMA_PLAZO)
                ->where('monto_afiliacion_alcantarillado', $contado)
                ->where('saldo_afiliacion_alcantarillado', $contado)
                ->get(['id', 'monto_afiliacion', 'saldo_afiliacion']);

            foreach ($lotes as $lote) {
                $delta = $plazo - $contado;
                DB::table('lote_afiliados')->where('id', $lote->id)->update([
                    'monto_afiliacion_alcantarillado' => $plazo,
                    'saldo_afiliacion_alcantarillado' => $plazo,
                    'monto_afiliacion' => round((float) $lote->monto_afiliacion + $delta, 2),
                    'saldo_afiliacion' => round((float) $lote->saldo_afiliacion + $delta, 2),
                ]);
            }
        }
    }

    public function down(): void
    {
        $plazo = PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO_PLAZO;
        $contado = PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO;

        if (Schema::hasTable('tipos_afiliacion')) {
            DB::table('tipos_afiliacion')
                ->where('codigo', 'alcantarillado')
                ->where('monto_plazo', $plazo)
                ->update(['monto_plazo' => $contado]);
        }

        if (Schema::hasTable('servicios') && Schema::hasColumn('servicios', 'costo_instalacion_plazos')) {
            DB::table('servicios')
                ->where('tipo', 'alcantarillado')
                ->where('costo_instalacion_plazos', $plazo)
                ->update(['costo_instalacion_plazos' => $contado]);
        }
    }
};
