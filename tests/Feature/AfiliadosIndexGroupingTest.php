<?php

use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\LoteAfiliado;
use App\Models\Role;
use App\Models\User;
use App\Models\Otb;
use Database\Seeders\CategoriasSeeder;
use Database\Seeders\PermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $this->seed(CategoriasSeeder::class);
});

it('agrupa en una sola fila al afiliado con varias propiedades del mismo CI', function (): void {
    $user = User::factory()->create([
        'role' => Role::SECRETARIA,
        'is_system_account' => true,
    ]);
    $role = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();
    $user->roles()->sync([$role->id]);

    $otb = Otb::factory()->create(['nombre' => 'Barrio Centro']);
    $otraZona = Otb::factory()->create(['nombre' => 'OTB Norte']);

    $persona = Afiliado::factory()->create([
        'nombres' => 'Javier',
        'apellidos' => 'Mercado Huerta',
        'ci' => '86465254',
        'telefono' => '48646845',
    ]);

    $persona->lotes()->delete();
    $persona->sincronizarLotePrincipal(['numero_lote' => '1', 'otb_id' => $otb->id]);
    $persona->sincronizarLotePrincipal(['numero_lote' => '2', 'otb_id' => $otraZona->id]);
    $persona->sincronizarLotePrincipal(['numero_lote' => '3', 'otb_id' => $otb->id]);

    $this->actingAs($user->fresh())
        ->get(route('afiliados.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('afiliados/Index')
            ->where('afiliados.total', 1)
            ->has('afiliados.data', 1)
            ->where('afiliados.data.0.ci', '86465254')
            ->has('afiliados.data.0.propiedades', 3)
            ->where('afiliados.data.0.propiedades.0.numero_lote', '1')
            ->where('afiliados.data.0.propiedades.0.otb', 'Barrio Centro')
            ->where('afiliados.data.0.propiedades.0.otb_id', $otb->id)
            ->where('afiliados.data.0.propiedades.1.numero_lote', '2')
            ->where('afiliados.data.0.propiedades.1.otb', 'OTB Norte')
            ->where('afiliados.data.0.propiedades.2.numero_lote', '3')
            ->where('afiliados.data.0.propiedades.2.otb', 'Barrio Centro')
        );
});

it('ordena los lotes de menor a mayor', function (): void {
    $user = User::factory()->create([
        'role' => Role::SECRETARIA,
        'is_system_account' => true,
    ]);
    $role = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();
    $user->roles()->sync([$role->id]);

    $otb = Otb::factory()->create();

    $alto = Afiliado::factory()->conLote([
        'otb_id' => $otb->id,
        'numero_lote' => '550',
    ])->create([
        'nombres' => 'Ana',
        'apellidos' => 'Lopez',
        'ci' => '11111111',
    ]);
    $alto->sincronizarLotePrincipal([
        'numero_lote' => '552',
        'otb_id' => $otb->id,
        'telefono' => $alto->telefono,
    ]);

    Afiliado::factory()->conLote([
        'otb_id' => $otb->id,
        'numero_lote' => '2',
    ])->create([
        'nombres' => 'Bruno',
        'apellidos' => 'Perez',
        'ci' => '99999999',
    ]);

    $alto->sincronizarLotePrincipal([
        'numero_lote' => '10',
        'otb_id' => $otb->id,
        'telefono' => $alto->telefono,
    ]);

    $this->actingAs($user->fresh())
        ->get(route('afiliados.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('afiliados.data.0.propiedades.0.numero_lote', '2')
            ->where('afiliados.data.1.propiedades.0.numero_lote', '10')
            ->where('afiliados.data.1.propiedades.1.numero_lote', '550')
            ->where('afiliados.data.1.propiedades.2.numero_lote', '552')
        );
});

it('rechaza alta con mismo nombre pero distinto CI', function (): void {
    $user = User::factory()->create([
        'role' => Role::SECRETARIA,
        'is_system_account' => true,
    ]);
    $role = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();
    $user->roles()->sync([$role->id]);

    $otb = Otb::factory()->create();

    Afiliado::factory()->conLote(['otb_id' => $otb->id, 'numero_lote' => '1'])->create([
        'nombres' => 'Javier',
        'apellidos' => 'Mercado Huerta',
        'ci' => '86465254',
    ]);

    $this->actingAs($user->fresh())->post(route('afiliados.store'), [
        'nombres' => 'Javier',
        'apellidos' => 'Mercado Huerta',
        'ci' => '10078004',
        'telefono' => '70000001',
        'direccion' => 'Lote demo',
        'otb_id' => $otb->id,
        'numero_lote' => '2',
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 3500,
        'saldo_afiliacion' => 0,
        'categoria_id' => Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->value('id'),
        'estado' => Afiliado::ESTADO_ACTIVO,
        'servicios' => [],
    ])->assertSessionHasErrors('nombres');
});

it('reutiliza la misma persona al registrar otra propiedad del mismo ci', function (): void {
    $user = User::factory()->create([
        'role' => Role::SECRETARIA,
        'is_system_account' => true,
    ]);
    $role = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();
    $user->roles()->sync([$role->id]);

    $otb = Otb::factory()->create();

    Afiliado::factory()->conLote(['otb_id' => $otb->id, 'numero_lote' => '1'])->create([
        'nombres' => 'Ana',
        'apellidos' => 'Lopez',
        'ci' => '11223344',
        'telefono' => '71110001',
    ]);

    $response = $this->actingAs($user->fresh())->post(route('afiliados.store'), [
        'nombres' => 'Ana',
        'apellidos' => 'Lopez',
        'ci' => '11223344',
        'telefono' => '71110002',
        'direccion' => 'Calle 2',
        'otb_id' => $otb->id,
        'numero_lote' => '2',
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 3500,
        'saldo_afiliacion' => 0,
        'categoria_id' => Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->value('id'),
        'estado' => Afiliado::ESTADO_ACTIVO,
        'servicios' => [],
    ]);

    $lote = LoteAfiliado::query()->where('numero_lote', '2')->whereHas('afiliado', fn ($q) => $q->where('ci', '11223344'))->first();
    $response->assertRedirect(route('afiliados.show', $lote?->id));

    expect(Afiliado::query()->where('ci', '11223344')->count())->toBe(1)
        ->and(LoteAfiliado::query()->where('afiliado_id', Afiliado::query()->where('ci', '11223344')->value('id'))->count())->toBe(2);
});
