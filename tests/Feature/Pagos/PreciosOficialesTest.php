<?php

/*
 * Precios oficiales de la Asociación:
 *  - Agua: Domiciliar Bs 8, Comercial Bs 17.
 *  - Alcantarillado: Domiciliar Bs 15, Comercial Bs 20.
 *  - Afiliación agua: contado Bs 3.500, plazo Bs 3.800.
 *  - Afiliación alcantarillado: contado Bs 2.000, plazo Bs 2.500.
 *  - Multa por mora: Bs 50 a los 3 meses; Bs 150 al año.
 */

use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Models\Servicio;
use App\Models\TipoAfiliacion;
use App\Models\Otb;
use Database\Seeders\ConfiguracionSeeder;

it('P1 las categorías tienen las tarifas mensuales oficiales', function (): void {
    $dom = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();
    $com = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->firstOrFail();

    expect([(float) $dom->tarifa_agua, (float) $dom->tarifa_alcantarillado])->toBe([8.0, 15.0])
        ->and([(float) $com->tarifa_agua, (float) $com->tarifa_alcantarillado])->toBe([17.0, 20.0]);
});

it('P2 los tipos de afiliación tienen los montos oficiales al contado y a plazo', function (): void {
    $agua = TipoAfiliacion::query()->where('codigo', TipoAfiliacion::CODIGO_AGUA)->firstOrFail();
    $alc = TipoAfiliacion::query()->where('codigo', TipoAfiliacion::CODIGO_ALCANTARILLADO)->firstOrFail();

    expect([$agua->montoContado(), $agua->montoPlazo()])->toBe([
        PagoDetalle::MONTO_AFILIACION_AGUA,
        PagoDetalle::MONTO_AFILIACION_AGUA_PLAZO,
    ])
        ->and([$alc->montoContado(), $alc->montoPlazo()])->toBe([
            PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO,
            PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO_PLAZO,
        ]);
});

it('P3 la multa trimestral es de Bs 50 con 3 meses sin pagar y el corte es a los 12 meses vencidos', function (): void {
    $this->seed(ConfiguracionSeeder::class);

    expect((float) Configuracion::get(Configuracion::MULTA_TRIMESTRAL))->toBe(50.0)
        ->and((int) Configuracion::get(Configuracion::MESES_MULTA_TRIMESTRAL))->toBe(3)
        ->and((int) Configuracion::get(Configuracion::MESES_CORTE_SERVICIO))->toBe(12)
        ->and(Configuracion::get('multa_anual'))->toBeNull(); // ya no hay multa anual aparte
});

it('P4 la migración de corrección deja el alcantarillado comercial en Bs 20 y ajusta solo cuotas no pagadas', function (): void {
    $com = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->firstOrFail();
    $com->update(['tarifa_alcantarillado' => 30]); // estado anterior a la corrección
    $alc = Servicio::factory()->create(['nombre' => 'Alcantarillado', 'tipo' => 'alcantarillado', 'tarifa_mensual' => 30, 'activo' => true]);

    $afiliado = Afiliado::factory()->create();
    $afiliado->lotes()->delete();
    $lote = $afiliado->lotes()->create([
        'numero_lote' => '6001', 'otb_id' => Otb::factory()->create()->id, 'fecha_afiliacion' => now()->subMonths(4)->startOfMonth(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO, 'monto_afiliacion' => 0, 'saldo_afiliacion' => 0,
        'estado' => Afiliado::ESTADO_ACTIVO, 'categoria_id' => $com->id,
    ]);
    $lote->servicios()->attach($alc->id, ['tarifa_acordada' => 30, 'fecha_inicio' => now()->subMonths(4)->startOfMonth()]);

    $cuota = fn (string $periodo, float $saldo, string $estado) => Cuota::query()->create([
        'lote_afiliado_id' => $lote->id, 'servicio_id' => $alc->id, 'periodo' => $periodo, 'gestion' => (int) substr($periodo, 0, 4),
        'monto_original' => 30, 'saldo' => $saldo, 'fecha_vencimiento' => now()->toDateString(), 'estado' => $estado,
    ]);
    $pendiente = $cuota(now()->subMonths(4)->format('Y-m'), 30, Cuota::ESTADO_PENDIENTE);
    $parcial = $cuota(now()->subMonths(3)->format('Y-m'), 15, Cuota::ESTADO_PENDIENTE);   // ya pagó Bs 15
    $pagada = $cuota(now()->subMonths(2)->format('Y-m'), 0, Cuota::ESTADO_PAGADA);       // pagó Bs 30

    (require base_path('database/migrations/2026_10_26_120000_corregir_tarifa_alcantarillado_comercial.php'))->up();

    expect((float) $com->fresh()->tarifa_alcantarillado)->toBe(20.0)
        ->and((float) $lote->servicios()->first()->pivot->tarifa_acordada)->toBe(20.0)
        ->and([(float) $pendiente->fresh()->monto_original, (float) $pendiente->fresh()->saldo])->toBe([20.0, 20.0])
        ->and([(float) $parcial->fresh()->saldo, $parcial->fresh()->estado])->toBe([5.0, Cuota::ESTADO_PENDIENTE])
        ->and((float) $pagada->fresh()->monto_original)->toBe(30.0); // ya cobrada: decide la directiva
});
