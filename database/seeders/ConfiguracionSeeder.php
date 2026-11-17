<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Configuracion::DEFINICIONES as $clave => $definicion) {
            Configuracion::query()->updateOrCreate(
                ['clave' => $clave],
                [
                    'valor' => $definicion['valor'],
                    'tipo' => $definicion['tipo'],
                    'descripcion' => $definicion['descripcion'],
                ],
            );
        }

        // Eliminar claves antiguas si aún existieran (tarifas/afiliación viven en categorias / tipos_afiliacion;
        // la multa anual ya no existe y sus meses ahora son los del corte del servicio).
        Configuracion::query()->whereIn('clave', [
            'tarifa_agua',
            'tarifa_alcantarillado',
            'afiliacion_contado',
            'afiliacion_plazo',
            'multa_anual',
            'meses_multa_anual',
        ])->delete();
    }
}
