<?php

use App\Models\ActivityLog;
use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use App\Models\Otb;
use App\Services\ActivityLogger;
use Database\Seeders\CategoriasSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $this->seed(CategoriasSeeder::class);
});

function adminOperativo(): User
{
    $user = User::factory()->create(['role' => Role::ADMIN]);
    $role = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

test('el historial guarda la acción aunque no haya pantalla', function (): void {
    $admin = adminOperativo();

    app(ActivityLogger::class)->log($admin, 'Afiliados', 'Alta de afiliado de prueba');

    expect(ActivityLog::query()->count())->toBe(1)
        ->and(ActivityLog::query()->first()->modulo)->toBe('Afiliados');
});

test('genera numeros de recibo unicos bajo transaccion', function (): void {
    $afiliado = Afiliado::factory()->create();

    DB::transaction(function () use ($afiliado): void {
        Pago::create([
            'numero_recibo' => Pago::generarNumeroRecibo(),
            'afiliado_id' => $afiliado->id,
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 100,
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);

        Pago::create([
            'numero_recibo' => Pago::generarNumeroRecibo(),
            'afiliado_id' => $afiliado->id,
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 50,
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);
    });

    $recibos = Pago::query()->pluck('numero_recibo');

    expect($recibos->unique()->count())->toBe(2);
});

test('no permite dos filas de afiliado con el mismo ci', function (): void {
    Afiliado::factory()->conLote(['numero_lote' => '1'])->create([
        'ci' => '12345678',
    ]);

    expect(fn () => Afiliado::factory()->conLote(['numero_lote' => '2'])->create([
        'ci' => '12345678',
    ]))->toThrow(\Illuminate\Database\QueryException::class);

    expect(Afiliado::query()->where('ci', '12345678')->count())->toBe(1);
});

test('permite mismo ci en la misma otb con distinto numero de lote', function (): void {
    $otb = Otb::factory()->create();

    $persona = Afiliado::factory()->conLote([
        'otb_id' => $otb->id,
        'numero_lote' => '10',
    ])->create([
        'ci' => '11223344',
        'telefono' => '70000001',
    ]);

    $persona->sincronizarLotePrincipal([
        'otb_id' => $otb->id,
        'numero_lote' => '11',
    ]);

    expect(Afiliado::query()->where('ci', '11223344')->count())->toBe(1)
        ->and($persona->lotes()->count())->toBe(2);
});

test('rechaza duplicado de ci y numero de lote aunque la otb sea distinta', function (): void {
    $admin = adminOperativo();
    $zonaA = Otb::factory()->create();
    $zonaB = Otb::factory()->create();

    Afiliado::factory()->conLote([
        'otb_id' => $zonaA->id,
        'numero_lote' => '5',
    ])->create([
        'ci' => '55667788',
        'nombres' => 'Pedro',
        'apellidos' => 'Rojas',
    ]);

    $this->actingAs($admin)->post(route('afiliados.store'), [
        'nombres' => 'Pedro',
        'apellidos' => 'Rojas',
        'ci' => '55667788',
        'telefono' => '71112233',
        'direccion' => 'Calle 2',
        'otb_id' => $zonaB->id,
        'numero_lote' => '5',
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 3500,
        'saldo_afiliacion' => 0,
        'categoria_id' => Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->value('id'),
        'estado' => Afiliado::ESTADO_ACTIVO,
        'servicios' => [],
    ])->assertSessionHasErrors('numero_lote');
});

test('sugiere el siguiente numero de lote libre sin repetir globalmente', function (): void {
    $admin = adminOperativo();
    $zonaA = Otb::factory()->create(['activa' => true]);
    $zonaB = Otb::factory()->create(['activa' => true]);

    Afiliado::factory()->conLote([
        'otb_id' => $zonaA->id,
        'numero_lote' => '1',
    ])->create(['ci' => '44556677']);

    $this->actingAs($admin)
        ->getJson(route('afiliados.siguiente-lote', ['ci' => '99887766', 'otb_id' => $zonaB->id]))
        ->assertOk()
        ->assertJson(['siguiente_lote' => '2']);
});

test('precarga el siguiente numero de lote libre al abrir el registro', function (): void {
    $admin = adminOperativo();
    $otb = Otb::factory()->create(['activa' => true]);

    $persona = Afiliado::factory()->conLote([
        'otb_id' => $otb->id,
        'numero_lote' => '1',
    ])->create(['ci' => '12121212']);

    Afiliado::factory()->conLote([
        'otb_id' => $otb->id,
        'numero_lote' => '2',
    ])->create(['ci' => '34343434']);

    $this->actingAs($admin)
        ->get(route('afiliados.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('afiliados/Create')
            ->where('numero_lote_sugerido', '3'));

    $this->actingAs($admin)
        ->get(route('afiliados.create', ['desde' => $persona->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('numero_lote_sugerido', '3')
            ->where('prefijo_nuevo_lote.numero_lote', '3'));
});

test('asigna el siguiente numero de lote libre si el registro llega sin numero', function (): void {
    $admin = adminOperativo();
    $otb = Otb::factory()->create(['activa' => true]);

    Afiliado::factory()->conLote([
        'otb_id' => $otb->id,
        'numero_lote' => '1',
    ])->create(['ci' => '56565656']);

    $this->actingAs($admin)->post(route('afiliados.store'), [
        'nombres' => 'Ana',
        'apellidos' => 'Quispe',
        'ci' => '78787878',
        'telefono' => '71112233',
        'direccion' => 'Calle Nueva',
        'otb_id' => $otb->id,
        'numero_lote' => '',
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 0,
        'saldo_afiliacion' => 0,
        'categoria_id' => Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->value('id'),
        'estado' => Afiliado::ESTADO_ACTIVO,
        'servicios' => [],
    ])->assertSessionHasNoErrors();

    $lote = Afiliado::query()->where('ci', '78787878')->firstOrFail()->lotes()->first();

    expect($lote?->numero_lote)->toBe('2');
});

test('sugiere el siguiente numero de lote para un ci existente', function (): void {
    $admin = adminOperativo();

    $persona = Afiliado::factory()->conLote(['numero_lote' => '1'])->create([
        'ci' => '33445566',
    ]);
    $persona->sincronizarLotePrincipal(['numero_lote' => '2']);

    $this->actingAs($admin)
        ->getJson(route('afiliados.siguiente-lote', ['ci' => '33445566']))
        ->assertOk()
        ->assertJson([
            'existe' => true,
            'siguiente_lote' => '3',
            'propiedades' => 2,
        ]);
});

test('rechaza lote duplicado por otb direccion y numero', function (): void {
    $admin = adminOperativo();
    $otb = Otb::factory()->create(['activa' => true]);

    Afiliado::factory()->conLote([
        'otb_id' => $otb->id,
        'numero_lote' => '7',
        'direccion' => 'Av. Principal 100',
    ])->create([
        'ci' => '99887766',
        'nombres' => 'Luis',
        'apellidos' => 'Mamani',
    ]);

    $this->actingAs($admin)->post(route('afiliados.store'), [
        'nombres' => 'Carla',
        'apellidos' => 'Quispe',
        'ci' => '88776655',
        'telefono' => '70001122',
        'direccion' => 'Av. Principal 100',
        'otb_id' => $otb->id,
        'numero_lote' => '7',
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 3500,
        'saldo_afiliacion' => 0,
        'categoria_id' => Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->value('id'),
        'estado' => Afiliado::ESTADO_ACTIVO,
        'servicios' => [],
    ])->assertSessionHasErrors('direccion');
});

test('rechaza numero de lote duplicado al registrar aunque la otb sea distinta', function (): void {
    $admin = adminOperativo();
    $zonaA = Otb::factory()->create(['activa' => true]);
    $zonaB = Otb::factory()->create(['activa' => true]);

    Afiliado::factory()->conLote([
        'otb_id' => $zonaA->id,
        'numero_lote' => '1',
        'direccion' => 'Calle A',
    ])->create([
        'ci' => '90909090',
        'nombres' => 'Rosa',
        'apellidos' => 'Vargas',
    ]);

    $this->actingAs($admin)->post(route('afiliados.store'), [
        'nombres' => 'Mario',
        'apellidos' => 'Flores',
        'ci' => '80808080',
        'telefono' => '70001122',
        'direccion' => 'Calle B',
        'otb_id' => $zonaB->id,
        'numero_lote' => '1',
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 3500,
        'saldo_afiliacion' => 0,
        'categoria_id' => Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->value('id'),
        'estado' => Afiliado::ESTADO_ACTIVO,
        'servicios' => [],
    ])->assertSessionHasErrors('numero_lote');
});

test('rechaza cambiar a numero de lote ya usado al editar', function (): void {
    $admin = adminOperativo();
    $zonaA = Otb::factory()->create(['activa' => true]);
    $zonaB = Otb::factory()->create(['activa' => true]);
    $domiciliaria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    Afiliado::factory()->conLote([
        'otb_id' => $zonaA->id,
        'numero_lote' => '1',
    ])->create([
        'ci' => '51515151',
        'nombres' => 'Elena',
        'apellidos' => 'Rios',
    ]);

    $afiliado = Afiliado::factory()->conLote([
        'otb_id' => $zonaB->id,
        'numero_lote' => '2',
    ])->create([
        'ci' => '61616161',
        'nombres' => 'Pedro',
        'apellidos' => 'Soto',
    ]);

    $lote = $afiliado->lotes()->firstOrFail();

    $this->actingAs($admin)->put(route('afiliados.update', $lote->id), [
        'nombres' => 'Pedro',
        'apellidos' => 'Soto',
        'ci' => '61616161',
        'telefono' => '71234567',
        'direccion' => null,
        'otb_id' => $zonaB->id,
        'numero_lote' => '1',
        'fecha_afiliacion' => $lote->fecha_afiliacion->toDateString(),
        'forma_pago_afiliacion' => $lote->forma_pago_afiliacion,
        'monto_afiliacion' => (float) $lote->monto_afiliacion,
        'saldo_afiliacion' => (float) $lote->saldo_afiliacion,
        'categoria_id' => $lote->categoria_id ?? $domiciliaria->id,
        'estado' => Afiliado::ESTADO_ACTIVO,
        'observaciones' => null,
        'servicios' => [],
    ])->assertSessionHasErrors('numero_lote');
});

test('permite editar afiliado sin cambiar su numero de lote en la otb', function (): void {
    $admin = adminOperativo();
    $otb = Otb::factory()->create(['activa' => true]);
    $domiciliaria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    $afiliado = Afiliado::factory()->conLote([
        'otb_id' => $otb->id,
        'numero_lote' => '1',
        'direccion' => null,
    ])->create([
        'ci' => '20202020',
        'nombres' => 'Juan',
        'apellidos' => 'Perez',
    ]);

    $lote = $afiliado->lotes()->firstOrFail();

    $this->actingAs($admin)->put(route('afiliados.update', $lote->id), [
        'nombres' => 'Juan Carlos',
        'apellidos' => 'Perez',
        'ci' => '20202020',
        'telefono' => '71234567',
        'direccion' => null,
        'otb_id' => $otb->id,
        'numero_lote' => '1',
        'fecha_afiliacion' => $lote->fecha_afiliacion->toDateString(),
        'forma_pago_afiliacion' => $lote->forma_pago_afiliacion,
        'monto_afiliacion' => (float) $lote->monto_afiliacion,
        'saldo_afiliacion' => (float) $lote->saldo_afiliacion,
        'categoria_id' => $lote->categoria_id ?? $domiciliaria->id,
        'estado' => Afiliado::ESTADO_ACTIVO,
        'observaciones' => null,
        'servicios' => [],
    ])->assertRedirect(route('afiliados.show', $lote->id));

    expect($afiliado->fresh()->nombres)->toBe('Juan Carlos');
});

test('permite editar afiliado conservando otb lote y direccion propios', function (): void {
    $admin = adminOperativo();
    $otb = Otb::factory()->create(['activa' => true]);
    $domiciliaria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    $afiliado = Afiliado::factory()->conLote([
        'otb_id' => $otb->id,
        'numero_lote' => '1',
        'direccion' => 'Calle Los Pinos 45',
    ])->create([
        'ci' => '40404040',
        'nombres' => 'Javier',
        'apellidos' => 'Torres',
    ]);

    $lote = $afiliado->lotes()->firstOrFail();

    $this->actingAs($admin)->put(route('afiliados.update', $lote->id), [
        'nombres' => 'Javier Carlos',
        'apellidos' => 'Torres',
        'ci' => '40404040',
        'telefono' => '71234567',
        'direccion' => 'Calle Los Pinos 45',
        'otb_id' => $otb->id,
        'numero_lote' => '1',
        'fecha_afiliacion' => $lote->fecha_afiliacion->toDateString(),
        'forma_pago_afiliacion' => $lote->forma_pago_afiliacion,
        'monto_afiliacion' => (float) $lote->monto_afiliacion,
        'saldo_afiliacion' => (float) $lote->saldo_afiliacion,
        'categoria_id' => $lote->categoria_id ?? $domiciliaria->id,
        'estado' => Afiliado::ESTADO_ACTIVO,
        'observaciones' => null,
        'servicios' => [],
    ])->assertRedirect(route('afiliados.show', $lote->id));

    expect($afiliado->fresh()->nombres)->toBe('Javier Carlos');
});
