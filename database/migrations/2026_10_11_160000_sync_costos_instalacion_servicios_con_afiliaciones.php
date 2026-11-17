<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['agua', 'alcantarillado'] as $codigo) {
            $tipo = DB::table('tipos_afiliacion')->where('codigo', $codigo)->first();
            if (! $tipo) {
                continue;
            }

            DB::table('servicios')
                ->where('tipo', $codigo)
                ->where('costo_instalacion_contado', 0)
                ->where('costo_instalacion_plazos', 0)
                ->update([
                    'costo_instalacion_contado' => $tipo->monto,
                    'costo_instalacion_plazos' => $tipo->monto_plazo,
                ]);
        }
    }

    public function down(): void {}
};
