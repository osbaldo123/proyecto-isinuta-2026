<?php

namespace Database\Factories;

use App\Models\Afiliado;
use App\Models\Pago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_recibo' => Pago::generarNumeroRecibo(),
            'afiliado_id' => Afiliado::factory(),
            'fecha_pago' => now()->toDateString(),
            'monto_total' => fake()->randomFloat(2, 50, 500),
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_EMITIDO,
            'cobrado_por' => null,
            'observaciones' => null,
        ];
    }
}
