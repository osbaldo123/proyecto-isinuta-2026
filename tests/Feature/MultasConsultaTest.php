<?php

use App\Models\Afiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
});

function usuarioConRol(string $roleName): User
{
    $user = User::factory()->create(['role' => $roleName]);
    $role = Role::query()->where('nombre', $roleName)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

it('muestra listado completo de multas para el presidente', function (): void {
    $presidente = usuarioConRol(Role::PRESIDENTE);
    Multa::factory()->count(2)->create();

    $this->actingAs($presidente)
        ->get(route('multas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('multas/Index')
            ->where('modo', 'gestion')
            ->has('multas.data', 2));
});

it('muestra mensaje cuando no encuentra afiliado por CI en modo consulta', function (): void {
    $consulta = usuarioConRol(Role::CONSULTA_PAGOS_MULTAS);

    $this->actingAs($consulta)
        ->get(route('multas.index', ['ci' => '99999999']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('modo', 'consulta')
            ->where('afiliado', null)
            ->where('mensaje', 'No se pudo encontrar al afiliado con el CI: 99999999'));
});

it('muestra las multas del afiliado buscado por CI en modo consulta', function (): void {
    $consulta = usuarioConRol(Role::CONSULTA_PAGOS_MULTAS);
    $afiliado = Afiliado::factory()->create([
        'ci' => '11223344',
    ]);
    Multa::factory()->create([
        'afiliado_id' => $afiliado->id,
        'monto' => 75,
        'estado' => Multa::ESTADO_PENDIENTE,
    ]);

    $this->actingAs($consulta)
        ->get(route('multas.index', ['ci' => '11223344']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('modo', 'consulta')
            ->has('afiliado', fn ($a) => $a
                ->where('ci', '11223344')
                ->etc())
            ->has('multas.data', 1));
});

it('muestra solo las multas del afiliado vinculado al iniciar sesión', function (): void {
    $afiliado = Afiliado::factory()->create(['ci' => '55667788']);
    $otro = Afiliado::factory()->create(['ci' => '88776655']);

    Multa::factory()->create(['afiliado_id' => $afiliado->id, 'monto' => 50]);
    Multa::factory()->create(['afiliado_id' => $otro->id, 'monto' => 100]);

    $usuario = User::factory()->create([
        'role' => Role::CONSULTA_PAGOS_MULTAS,
        'afiliado_id' => $afiliado->id,
        'ci' => '55667788',
    ]);
    $usuario->assignRole(Role::CONSULTA_PAGOS_MULTAS);

    $this->actingAs($usuario)
        ->get(route('multas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('modo', 'consulta')
            ->where('consultaVinculada', true)
            ->has('afiliado', fn ($a) => $a->where('id', $afiliado->id)->etc())
            ->has('multas.data', 1)
            ->where('multas.data.0.afiliado.id', $afiliado->id));
});

it('muestra solo las multas que faltan pagar', function (): void {
    $presidente = usuarioConRol(Role::PRESIDENTE);
    $afiliado = Afiliado::factory()->create();

    Multa::factory()->create([
        'afiliado_id' => $afiliado->id,
        'descripcion' => 'Sigue debiendo',
        'monto' => 50,
        'saldo' => 30,
        'estado' => Multa::ESTADO_PENDIENTE,
    ]);
    Multa::factory()->create([
        'afiliado_id' => $afiliado->id,
        'descripcion' => 'Ya pagó',
        'monto' => 150,
        'saldo' => 0,
        'estado' => Multa::ESTADO_PAGADA,
    ]);
    Multa::factory()->create([
        'afiliado_id' => $afiliado->id,
        'descripcion' => 'Anulada',
        'monto' => 20,
        'saldo' => 20,
        'estado' => Multa::ESTADO_ANULADA,
    ]);

    $this->actingAs($presidente)
        ->get(route('multas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('multas.data', 1)
            ->where('multas.data.0.descripcion', 'Sigue debiendo')
            ->where('multas.data.0.monto', 30));
});

it('busca por afiliado y separa la deuda de cada lote', function (): void {
    $presidente = usuarioConRol(Role::PRESIDENTE);
    $afiliado = Afiliado::factory()->create([
        'nombres' => 'Alvina',
        'apellidos' => 'Cespedes',
        'ci' => '99000003',
    ]);
    $loteUno = $afiliado->lotes()->firstOrFail();
    $loteUno->update(['numero_lote' => '4']);
    $loteDos = $loteUno->replicate();
    $loteDos->numero_lote = '12';
    $loteDos->save();

    Multa::factory()->create([
        'afiliado_id' => $afiliado->id,
        'lote_afiliado_id' => $loteUno->id,
        'motivo' => Multa::MOTIVO_MORA_ANUAL,
        'descripcion' => 'Año lote 4',
        'monto' => 150,
        'saldo' => 150,
        'estado' => Multa::ESTADO_PENDIENTE,
    ]);
    Multa::factory()->create([
        'afiliado_id' => $afiliado->id,
        'lote_afiliado_id' => $loteDos->id,
        'motivo' => Multa::MOTIVO_FALTA_TRABAJO,
        'descripcion' => 'Limpieza lote 12',
        'monto' => 20,
        'saldo' => 20,
        'estado' => Multa::ESTADO_PENDIENTE,
    ]);
    Afiliado::factory()->create([
        'nombres' => 'Paula',
        'apellidos' => 'Rojas',
    ]);

    $this->actingAs($presidente)
        ->get(route('multas.index', ['q' => 'Alvina']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('multas.data', 2));

    $this->actingAs($presidente)
        ->get(route('multas.index', ['q' => '12']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('multas.data', 1)
            ->where('multas.data.0.descripcion', 'Limpieza lote 12')
            ->where('multas.data.0.lote.numero', '12')
            ->where('multas.data.0.lote_id', $loteDos->id));

    $this->actingAs($presidente)
        ->get(route('multas.index', ['q' => 'Paula']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('multas.data', 0));
});

it('filtra multas por estado y motivo', function (): void {
    $presidente = usuarioConRol(Role::PRESIDENTE);
    $afiliado = Afiliado::factory()->create();
    $lote = $afiliado->lotes()->firstOrFail();

    Multa::factory()->create([
        'afiliado_id' => $afiliado->id,
        'lote_afiliado_id' => $lote->id,
        'motivo' => Multa::MOTIVO_MORA_TRIMESTRAL,
        'estado' => Multa::ESTADO_PENDIENTE,
        'monto' => 50,
        'saldo' => 50,
        'descripcion' => 'Mora 3 meses',
    ]);
    Multa::factory()->create([
        'afiliado_id' => $afiliado->id,
        'lote_afiliado_id' => $lote->id,
        'motivo' => Multa::MOTIVO_FALTA_TRABAJO,
        'estado' => Multa::ESTADO_PENDIENTE,
        'monto' => 20,
        'saldo' => 20,
        'descripcion' => 'Limpieza',
    ]);
    Multa::factory()->create([
        'afiliado_id' => $afiliado->id,
        'lote_afiliado_id' => $lote->id,
        'motivo' => Multa::MOTIVO_MORA_ANUAL,
        'estado' => Multa::ESTADO_PAGADA,
        'monto' => 150,
        'saldo' => 0,
        'descripcion' => 'Mora anual ya pagada',
    ]);

    $this->actingAs($presidente)
        ->get(route('multas.index', ['motivo' => Multa::MOTIVO_FALTA_TRABAJO]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('multas.data', 1)
            ->where('multas.data.0.descripcion', 'Limpieza')
            ->where('filtros.motivo', Multa::MOTIVO_FALTA_TRABAJO));

    $this->actingAs($presidente)
        ->get(route('multas.index', ['estado' => Multa::ESTADO_PAGADA]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('multas.data', 1)
            ->where('multas.data.0.descripcion', 'Mora anual ya pagada')
            ->where('filtros.estado', Multa::ESTADO_PAGADA));

    $this->actingAs($presidente)
        ->get(route('multas.index', ['estado' => 'todas']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filtros.estado', 'todas')
            ->where('multas.data', fn ($rows) => collect($rows)->count() >= 3));
});

it('abre el detalle de una multa desde el listado', function (): void {
    $presidente = usuarioConRol(Role::PRESIDENTE);
    $multa = Multa::factory()->create([
        'descripcion' => 'Mora anual gestión 2025',
        'monto' => 150,
        'motivo' => Multa::MOTIVO_MORA_ANUAL,
        'estado' => Multa::ESTADO_PENDIENTE,
    ]);

    $this->actingAs($presidente)
        ->get(route('multas.show', $multa->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('multas/Show')
            ->where('multa.id', $multa->id)
            ->where('multa.descripcion', 'Mora anual gestión 2025')
            ->where('multa.monto', 150)
            ->where('multa.motivo', Multa::MOTIVO_MORA_ANUAL)
            ->where('multa.afiliado.nombre_completo', $multa->afiliado->nombre_completo));
});

it('impide a un afiliado vinculado ver la multa de otro afiliado', function (): void {
    $afiliado = Afiliado::factory()->create(['ci' => '55667788']);
    $otro = Afiliado::factory()->create(['ci' => '88776655']);
    $propia = Multa::factory()->create(['afiliado_id' => $afiliado->id, 'descripcion' => 'Multa propia']);
    $ajena = Multa::factory()->create(['afiliado_id' => $otro->id, 'descripcion' => 'Multa ajena']);

    $usuario = User::factory()->create([
        'role' => Role::CONSULTA_PAGOS_MULTAS,
        'afiliado_id' => $afiliado->id,
        'ci' => '55667788',
    ]);
    $usuario->assignRole(Role::CONSULTA_PAGOS_MULTAS);

    $this->actingAs($usuario)->get(route('multas.show', $propia->id))->assertOk();
    $this->actingAs($usuario)->get(route('multas.show', $ajena->id))->assertForbidden();
});

it('permite consultar el detalle de la multa encontrada por CI', function (): void {
    $consulta = usuarioConRol(Role::CONSULTA_PAGOS_MULTAS);
    $afiliado = Afiliado::factory()->create(['ci' => '11223344']);
    $multa = Multa::factory()->create([
        'afiliado_id' => $afiliado->id,
        'descripcion' => 'Multa consultada',
    ]);

    $this->actingAs($consulta)->get(route('multas.show', $multa->id))->assertForbidden();
    $this->actingAs($consulta)
        ->get(route('multas.show', ['id' => $multa->id, 'ci' => '11223344']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('multas/Show')
            ->where('multa.descripcion', 'Multa consultada'));
});

it('muestra listado completo de recibos para el presidente', function (): void {
    $presidente = usuarioConRol(Role::PRESIDENTE);
    Pago::factory()->count(2)->create();

    $this->actingAs($presidente)
        ->get(route('pagos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos/Index')
            ->where('modo', 'gestion')
            ->has('pagos.data', 2));
});

it('filtra pagos por CI en el listado del presidente', function (): void {
    $presidente = usuarioConRol(Role::PRESIDENTE);
    $afiliado = Afiliado::factory()->create([
        'ci' => '33445566',
    ]);
    Pago::factory()->create(['afiliado_id' => $afiliado->id]);

    $this->actingAs($presidente)
        ->get(route('pagos.index', ['q' => '33445566']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos/Index')
            ->where('modo', 'gestion')
            ->has('pagos.data', 1));
});

it('impide a un afiliado vinculado ver el recibo de otro afiliado', function (): void {
    $afiliado = Afiliado::factory()->create(['ci' => '10101010']);
    $otro = Afiliado::factory()->create(['ci' => '20202020']);

    $pagoPropio = Pago::factory()->create(['afiliado_id' => $afiliado->id]);
    $pagoAjeno = Pago::factory()->create(['afiliado_id' => $otro->id]);

    $usuario = User::factory()->create([
        'role' => Role::CONSULTA_PAGOS_MULTAS,
        'afiliado_id' => $afiliado->id,
        'ci' => '10101010',
    ]);
    $usuario->assignRole(Role::CONSULTA_PAGOS_MULTAS);

    $this->actingAs($usuario)->get(route('pagos.show', $pagoPropio->id))->assertOk();
    $this->actingAs($usuario)->get(route('pagos.show', $pagoAjeno->id))->assertForbidden();
});

it('permite al presidente ver cualquier recibo de pago', function (): void {
    $presidente = usuarioConRol(Role::PRESIDENTE);
    $afiliado = Afiliado::factory()->create();
    $pago = Pago::factory()->create(['afiliado_id' => $afiliado->id]);

    $this->actingAs($presidente)->get(route('pagos.show', $pago->id))->assertOk();
});

it('permite autenticarse con CI de ocho dígitos', function (): void {
    // Prueba la función de usuarios de consulta para afiliados, que está desactivada por defecto.
    config(['sistema.usuarios_afiliados' => true]);
    $afiliado = Afiliado::factory()->create(['ci' => '44556677']);
    $usuario = User::factory()->create([
        'ci' => '44556677',
        'afiliado_id' => $afiliado->id,
        'password' => 'password',
        'role' => Role::CONSULTA_PAGOS_MULTAS,
    ]);
    $usuario->assignRole(Role::CONSULTA_PAGOS_MULTAS);

    $this->post(route('login.store'), [
        'email' => '44556677',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($usuario);
});
