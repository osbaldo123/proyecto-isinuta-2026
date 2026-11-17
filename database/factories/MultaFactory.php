<?php

namespace Database\Factories;

use App\Models\Afiliado;
use App\Models\Multa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Multa>
 */
class MultaFactory extends Factory
{
    protected $model = Multa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'afiliado_id' => Afiliado::factory(),
            'fecha_generacion' => now()->toDateString(),
            'motivo' => Multa::MOTIVO_MORA_TRIMESTRAL,
            'descripcion' => 'Mora por atraso en pagos',
            'monto' => fake()->randomFloat(2, 30, 150),
            'estado' => Multa::ESTADO_PENDIENTE,
            'generada_por' => null,
        ];
    }
}
