<?php

namespace Database\Factories;

use App\Models\Trabajo;
use App\Models\Otb;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trabajo>
 */
class TrabajoFactory extends Factory
{
    protected $model = Trabajo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'otb_id' => Otb::factory(),
            'tipo' => 'Limpieza',
            'descripcion' => 'Limpieza',
            'fecha_inicio' => now()->toDateString(),
            'monto_multa' => 20,
        ];
    }
}
