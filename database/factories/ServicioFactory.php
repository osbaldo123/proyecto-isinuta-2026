<?php

namespace Database\Factories;

use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servicio>
 */
class ServicioFactory extends Factory
{
    protected $model = Servicio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('SRV-####')),
            'nombre' => fake()->unique()->words(3, true),
            'tipo' => fake()->randomElement(['agua', 'alcantarillado', 'inspeccion', 'otro']),
            'tarifa_mensual' => fake()->randomFloat(2, 20, 80),
            'costo_instalacion_contado' => fake()->randomFloat(2, 500, 2500),
            'costo_instalacion_plazos' => fake()->randomFloat(2, 600, 3000),
            'descripcion' => fake()->optional()->sentence(),
            'activo' => true,
        ];
    }
}
