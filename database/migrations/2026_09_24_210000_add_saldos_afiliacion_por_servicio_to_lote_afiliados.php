<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lote_afiliados', function (Blueprint $table) {
            if (! Schema::hasColumn('lote_afiliados', 'saldo_afiliacion_agua')) {
                $table->decimal('saldo_afiliacion_agua', 10, 2)->default(0)->after('monto_afiliacion_alcantarillado');
            }
            if (! Schema::hasColumn('lote_afiliados', 'saldo_afiliacion_alcantarillado')) {
                $table->decimal('saldo_afiliacion_alcantarillado', 10, 2)->default(0)->after('saldo_afiliacion_agua');
            }
        });

        // Reparto proporcional del saldo único existente (mismo criterio que la ficha Show).
        DB::table('lote_afiliados')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $fila) {
                $montoAgua = (float) ($fila->monto_afiliacion_agua ?? 0);
                $montoAlc = (float) ($fila->monto_afiliacion_alcantarillado ?? 0);
                $total = round($montoAgua + $montoAlc, 2);
                $saldo = min($total, max(0, (float) ($fila->saldo_afiliacion ?? 0)));

                if ($total <= 0 || $saldo <= 0) {
                    $saldoAgua = 0.0;
                    $saldoAlc = 0.0;
                } elseif ($montoAgua > 0 && $montoAlc <= 0) {
                    $saldoAgua = $saldo;
                    $saldoAlc = 0.0;
                } elseif ($montoAlc > 0 && $montoAgua <= 0) {
                    $saldoAgua = 0.0;
                    $saldoAlc = $saldo;
                } else {
                    $pagado = round($total - $saldo, 2);
                    $ratio = $total > 0 ? $pagado / $total : 0;
                    $abonoAgua = round($montoAgua * $ratio, 2);
                    $abonoAlc = round($pagado - $abonoAgua, 2);
                    $saldoAgua = round($montoAgua - $abonoAgua, 2);
                    $saldoAlc = round($montoAlc - $abonoAlc, 2);
                }

                DB::table('lote_afiliados')->where('id', $fila->id)->update([
                    'saldo_afiliacion_agua' => $saldoAgua,
                    'saldo_afiliacion_alcantarillado' => $saldoAlc,
                    'saldo_afiliacion' => round($saldoAgua + $saldoAlc, 2),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('lote_afiliados', function (Blueprint $table) {
            if (Schema::hasColumn('lote_afiliados', 'saldo_afiliacion_alcantarillado')) {
                $table->dropColumn('saldo_afiliacion_alcantarillado');
            }
            if (Schema::hasColumn('lote_afiliados', 'saldo_afiliacion_agua')) {
                $table->dropColumn('saldo_afiliacion_agua');
            }
        });
    }
};
