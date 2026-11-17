<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriasSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            [
                'slug' => Categoria::SLUG_DOMICILIARIA,
                'nombre' => 'domiciliar',
                'descripcion' => 'Para el uso exclusivo de familias en sus hogares.',
                'tarifa_agua' => 8,
                'tarifa_alcantarillado' => 15,
                'activo' => true,
                'orden' => 1,
            ],
            [
                'slug' => Categoria::SLUG_COMERCIAL,
                'nombre' => 'Comercial',
                'descripcion' => 'Para tiendas de barrio, pensiones, talleres o cualquier negocio.',
                'tarifa_agua' => 17,
                'tarifa_alcantarillado' => 20,
                'activo' => true,
                'orden' => 2,
            ],
        ];

        foreach ($categorias as $data) {
            Categoria::query()->updateOrCreate(
                ['slug' => $data['slug']],
                $data,
            );
        }
    }
}
