<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lote_afiliados', function (Blueprint $table): void {
            $table->decimal('monto_afiliacion_agua', 10, 2)->default(0)->after('monto_afiliacion');
            $table->decimal('monto_afiliacion_alcantarillado', 10, 2)->default(0)->after('monto_afiliacion_agua');
        });

        // Repartir montos históricos conocidos (3500 agua, 2000 alcantarillado).
        DB::table('lote_afiliados')->orderBy('id')->chunkById(200, function ($lotes): void {
            foreach ($lotes as $lote) {
                $total = (float) $lote->monto_afiliacion;
                $agua = 0.0;
                $alc = 0.0;

                if (abs($total - 5500.0) < 0.01) {
                    $agua = 3500.0;
                    $alc = 2000.0;
                } elseif (abs($total - 3500.0) < 0.01) {
                    $agua = 3500.0;
                } elseif (abs($total - 2000.0) < 0.01) {
                    $alc = 2000.0;
                } elseif ($total > 0) {
                    $agua = $total;
                }

                DB::table('lote_afiliados')->where('id', $lote->id)->update([
                    'monto_afiliacion_agua' => $agua,
                    'monto_afiliacion_alcantarillado' => $alc,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('lote_afiliados', function (Blueprint $table): void {
            $table->dropColumn(['monto_afiliacion_agua', 'monto_afiliacion_alcantarillado']);
        });
    }
};
