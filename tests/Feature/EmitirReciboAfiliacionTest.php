<?php

use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\LoteAfiliado;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Role;
use App\Models\User;
use App\Models\Otb;
use Database\Seeders\CategoriasSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(CategoriasSeeder::class);
    $this->seed(ConfiguracionSeeder::class);
    $this->seed(PermissionsSeeder::class);
    Mail::fake();
});

function secretariaCobranza(): User
{
    $user = User::factory()->create([
        'role' => Role::SECRETARIA,
        'is_system_account' => true,
        'is_active' => true,
    ]);
    $role = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

it('emite recibos de afiliación al contado al registrar el afiliado', function (): void {
    $user = secretariaCobranza();
    $otb = Otb::factory()->create();
    $categoria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    $this->actingAs($user)->post(route('afiliados.store'), [
        'nombres' => 'Javier',
        'apellidos' => 'Mercado Huerta',
        'ci' => '86465254',
        'telefono' => '48646845',
        'direccion' => 'Calle 1',
        'otb_id' => $otb->id,
        'numero_lote' => '539',
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion_agua' => 3500,
        'monto_afiliacion_alcantarillado' => 2000,
        'saldo_afiliacion' => 0,
        'categoria_id' => $categoria->id,
        'estado' => Afiliado::ESTADO_ACTIVO,
        'servicios' => [],
    ])->assertRedirect();

    $afiliado = Afiliado::query()->where('ci', '86465254')->first();
    expect($afiliado)->not->toBeNull();

    $lote = $afiliado->lotes()->where('numero_lote', '539')->first();
    expect($lote)->not->toBeNull();

    $pagos = Pago::query()
        ->where('lote_afiliado_id', $lote->id)
        ->where('estado', '!=', Pago::ESTADO_ANULADO)
        ->with('detalles')
        ->get();

    expect($pagos)->toHaveCount(1)
        ->and($pagos->first()->proposito)->toBe('afiliacion')
        ->and($pagos->first()->proposito_label)->toBe('Afiliación');

    $detalles = $pagos->first()->detalles;
    expect($detalles)->toHaveCount(2);

    $conceptos = $detalles->pluck('concepto');
    expect($conceptos)->toContain(PagoDetalle::CONCEPTO_AFILIACION_AGUA)
        ->and($conceptos)->toContain(PagoDetalle::CONCEPTO_AFILIACION_ALCANTARILLADO);

    $descripciones = $detalles->pluck('descripcion')->implode(' | ');
    expect($descripciones)->toContain('Afiliación agua potable')
        ->and($descripciones)->toContain('Afiliación alcantarillado');

    expect((string) $pagos->first()->observaciones)->toContain('Categoría:')
        ->and((string) $pagos->first()->observaciones)->toContain('Al contado');

    expect((float) $pagos->first()->monto_total)->toBe(5500.0);
});

it('no emite recibo de afiliación a plazo sin abono inicial', function (): void {
    $user = secretariaCobranza();
    $otb = Otb::factory()->create();
    $categoria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    $this->actingAs($user)->post(route('afiliados.store'), [
        'nombres' => 'Ana',
        'apellidos' => 'Plazo Sin Abono',
        'ci' => '11112222',
        'otb_id' => $otb->id,
        'numero_lote' => '900',
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_PLAZO,
        'monto_afiliacion_agua' => 3500,
        'monto_afiliacion_alcantarillado' => 2000,
        'saldo_afiliacion' => 5500,
        'categoria_id' => $categoria->id,
        'estado' => Afiliado::ESTADO_ACTIVO,
        'servicios' => [],
    ])->assertRedirect();

    $lote = LoteAfiliado::query()->where('numero_lote', '900')->first();
    expect($lote)->not->toBeNull()
        ->and(Pago::query()->where('lote_afiliado_id', $lote->id)->count())->toBe(0);
});

it('completa recibos faltantes al abrir Recibos del afiliado', function (): void {
    $user = secretariaCobranza();
    $categoria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    $afiliado = Afiliado::factory()->conLote([
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 5500,
        'monto_afiliacion_agua' => 3500,
        'monto_afiliacion_alcantarillado' => 2000,
        'saldo_afiliacion' => 0,
        'categoria_id' => $categoria->id,
    ])->create(['estado' => Afiliado::ESTADO_ACTIVO]);

    $lote = $afiliado->lotes()->first();
    $lote->update([
        'monto_afiliacion' => 5500,
        'monto_afiliacion_agua' => 3500,
        'monto_afiliacion_alcantarillado' => 2000,
        'saldo_afiliacion' => 0,
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'categoria_id' => $categoria->id,
    ]);
    $lote->refresh();

    $this->actingAs($user)
        ->get(route('pagos.index', ['afiliado_id' => $lote->id]))
        ->assertOk();

    $pagos = Pago::query()->where('lote_afiliado_id', $lote->id)->with('detalles')->get();
    expect($pagos)->toHaveCount(1)
        ->and($pagos->first()->proposito_label)->toBe('Afiliación')
        ->and($pagos->first()->detalles)->toHaveCount(2);

    expect((string) $pagos->first()->observaciones)->toContain('Categoría:')
        ->and((string) $pagos->first()->observaciones)->toContain('Al contado');
});

it('unifica recibos de afiliación mal separados al abrir Recibos', function (): void {
    $user = secretariaCobranza();
    $categoria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    $afiliado = Afiliado::factory()->conLote([
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 5500,
        'monto_afiliacion_agua' => 3500,
        'monto_afiliacion_alcantarillado' => 2000,
        'saldo_afiliacion' => 0,
        'categoria_id' => $categoria->id,
    ])->create(['estado' => Afiliado::ESTADO_ACTIVO]);

    $lote = $afiliado->lotes()->first();

    $p1 = Pago::create([
        'numero_recibo' => Pago::generarNumeroRecibo(),
        'tipo_recibo' => Pago::TIPO_INGRESO,
        'proposito' => 'servicio_agua_potable',
        'proposito_label' => 'Servicio Agua Potable',
        'afiliado_id' => $afiliado->id,
        'lote_afiliado_id' => $lote->id,
        'fecha_pago' => now()->toDateString(),
        'monto_total' => 3500,
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'estado' => Pago::ESTADO_EMITIDO,
    ]);
    $p1->detalles()->create([
        'concepto' => PagoDetalle::CONCEPTO_AFILIACION_AGUA,
        'descripcion' => 'Afiliación agua potable · Categoría: domiciliar',
        'monto' => 3500,
    ]);

    $p2 = Pago::create([
        'numero_recibo' => Pago::generarNumeroRecibo(),
        'tipo_recibo' => Pago::TIPO_CAJA,
        'proposito' => 'servicio_alcantarillado',
        'proposito_label' => 'Servicio de Alcantarillado',
        'afiliado_id' => $afiliado->id,
        'lote_afiliado_id' => $lote->id,
        'fecha_pago' => now()->toDateString(),
        'monto_total' => 2000,
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'estado' => Pago::ESTADO_EMITIDO,
    ]);
    $p2->detalles()->create([
        'concepto' => PagoDetalle::CONCEPTO_AFILIACION_ALCANTARILLADO,
        'descripcion' => 'Afiliación alcantarillado',
        'monto' => 2000,
    ]);

    $this->actingAs($user)
        ->get(route('pagos.index', ['afiliado_id' => $lote->id, 'concepto' => 'afiliacion']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos/Index')
            ->has('pagos.data', 1)
            ->where('pagos.data.0.proposito_label', 'Afiliación')
            ->where('pagos.data.0.monto_total', 5500));

    expect(Pago::query()->where('lote_afiliado_id', $lote->id)->where('estado', '!=', Pago::ESTADO_ANULADO)->count())->toBe(1);
});
