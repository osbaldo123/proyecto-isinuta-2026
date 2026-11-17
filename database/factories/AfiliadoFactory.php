<?php

namespace Database\Factories;

use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\LoteAfiliado;
use App\Models\Otb;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Afiliado>
 */
class AfiliadoFactory extends Factory
{
    protected $model = Afiliado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ci = fake()->unique()->numerify('########');

        return [
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'ci' => $ci,
            'telefono' => fake()->numerify('7#######'),
            'direccion' => fake()->streetAddress(),
            'estado' => Afiliado::ESTADO_ACTIVO,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Afiliado $afiliado): void {
            if ($afiliado->lotes()->exists()) {
                return;
            }

            $categoriaId = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->value('id');

            LoteAfiliado::query()->create([
                'afiliado_id' => $afiliado->id,
                'numero_lote' => $this->numeroLoteUnico(),
                'otb_id' => Otb::factory()->create()->id,
                'direccion' => $afiliado->direccion,
                'telefono' => $afiliado->telefono,
                'fecha_afiliacion' => now()->subMonths(3)->toDateString(),
                'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
                'monto_afiliacion' => 3500,
                'saldo_afiliacion' => 0,
                'estado' => Afiliado::ESTADO_ACTIVO,
                'observaciones' => null,
                'categoria_id' => $categoriaId,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $lote
     */
    public function conLote(array $lote = []): static
    {
        return $this->afterCreating(function (Afiliado $afiliado) use ($lote): void {
            $existente = $afiliado->lotes()->first();

            $payload = [
                'numero_lote' => (string) ($lote['numero_lote'] ?? $existente?->numero_lote ?? $this->numeroLoteUnico()),
                'otb_id' => $lote['otb_id'] ?? $existente?->otb_id ?? Otb::factory()->create()->id,
                'direccion' => $lote['direccion'] ?? $existente?->direccion ?? $afiliado->direccion,
                'telefono' => $lote['telefono'] ?? $existente?->telefono ?? $afiliado->telefono,
                'fecha_afiliacion' => $lote['fecha_afiliacion'] ?? $existente?->fecha_afiliacion?->toDateString() ?? now()->subMonths(3)->toDateString(),
                'forma_pago_afiliacion' => $lote['forma_pago_afiliacion'] ?? $existente?->forma_pago_afiliacion ?? Afiliado::FORMA_CONTADO,
                'monto_afiliacion' => $lote['monto_afiliacion'] ?? $existente?->monto_afiliacion ?? 3500,
                'monto_afiliacion_agua' => $lote['monto_afiliacion_agua'] ?? $existente?->monto_afiliacion_agua ?? 0,
                'monto_afiliacion_alcantarillado' => $lote['monto_afiliacion_alcantarillado'] ?? $existente?->monto_afiliacion_alcantarillado ?? 0,
                'saldo_afiliacion' => $lote['saldo_afiliacion'] ?? $existente?->saldo_afiliacion ?? 0,
                'estado' => $lote['estado'] ?? $existente?->estado ?? Afiliado::ESTADO_ACTIVO,
                'observaciones' => $lote['observaciones'] ?? $existente?->observaciones,
                'categoria_id' => $lote['categoria_id'] ?? $existente?->categoria_id ?? Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->value('id'),
            ];

            if ($existente) {
                $existente->update($payload);
            } else {
                $afiliado->lotes()->create($payload);
            }
        });
    }

    /** Número de lote globalmente único (la BD no permite repetir). */
    private function numeroLoteUnico(): string
    {
        do {
            $numero = (string) fake()->unique()->numberBetween(1000, 999999);
        } while (LoteAfiliado::query()->where('numero_lote', $numero)->exists());

        return $numero;
    }
}
