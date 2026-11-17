<?php

namespace Database\Factories;

use App\Models\Otb;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Otb>
 */
class OtbFactory extends Factory
{
    protected $model = Otb::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'descripcion' => fake()->sentence(),
            'tipo' => 'OTB',
            'activa' => true,
        ];
    }
}
