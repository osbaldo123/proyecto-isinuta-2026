<?php

use App\Models\Afiliado;
use App\Models\Categoria;
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
    // Estas pruebas verifican la función de usuarios de consulta, que está desactivada por defecto.
    config(['sistema.usuarios_afiliados' => true]);
});

function secretariaParaAfiliados(): User
{
    $user = User::factory()->create([
        'role' => Role::SECRETARIA,
        'is_system_account' => true,
    ]);
    $role = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();
    $user->roles()->sync([$role->id]);

    test()->actingAs($user->fresh());

    return $user;
}

it('crea un usuario de consulta al registrar afiliado sin correo', function (): void {
    secretariaParaAfiliados();
    $otb = Otb::factory()->create();

    $domiciliaria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    $payload = [
        'nombres' => 'Juan',
        'apellidos' => 'Pérez López',
        'ci' => '12345678',
        'telefono' => '71234567',
        'direccion' => 'Calle 1',
        'otb_id' => $otb->id,
        'numero_lote' => '12',
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 3500,
        'saldo_afiliacion' => 0,
        'categoria_id' => $domiciliaria->id,
        'estado' => Afiliado::ESTADO_ACTIVO,
        'observaciones' => null,
        'servicios' => [],
    ];

    $this->post(route('afiliados.store'), $payload)->assertRedirect();

    $afiliado = Afiliado::query()->where('ci', '12345678')->first();
    expect($afiliado)->not->toBeNull()
        ->and($afiliado->ci)->toBe('12345678')
        ->and($afiliado->lotes()->where('numero_lote', '12')->exists())->toBeTrue();

    $usuario = User::query()->where('afiliado_id', $afiliado->id)->first();
    expect($usuario)->not->toBeNull()
        ->and($usuario->ci)->toBe('12345678')
        ->and($usuario->hasRole(Role::CONSULTA_PAGOS_MULTAS))->toBeTrue()
        ->and($usuario->email)->toContain('@isinuta.test');

    Mail::assertNothingSent();
});

it('no crea usuario de consulta cuando la función está desactivada', function (): void {
    config(['sistema.usuarios_afiliados' => false]);
    secretariaParaAfiliados();
    $otb = Otb::factory()->create();

    $domiciliaria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    $this->post(route('afiliados.store'), [
        'nombres' => 'Ana',
        'apellidos' => 'Rojas Vargas',
        'ci' => '23456789',
        'telefono' => '72345678',
        'direccion' => 'Calle 2',
        'otb_id' => $otb->id,
        'numero_lote' => '13',
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 3500,
        'saldo_afiliacion' => 0,
        'categoria_id' => $domiciliaria->id,
        'estado' => Afiliado::ESTADO_ACTIVO,
        'observaciones' => null,
        'servicios' => [],
    ])->assertRedirect();

    $afiliado = Afiliado::query()->where('ci', '23456789')->first();
    expect($afiliado)->not->toBeNull()
        ->and(User::query()->where('afiliado_id', $afiliado->id)->exists())->toBeFalse();
});

it('no permite ingresar a un afiliado mientras la función está desactivada', function (): void {
    config(['sistema.usuarios_afiliados' => false]);
    $afiliado = Afiliado::factory()->create(['ci' => '45678901']);
    User::factory()->create([
        'ci' => '45678901',
        'afiliado_id' => $afiliado->id,
        'password' => 'password',
        'role' => Role::CONSULTA_PAGOS_MULTAS,
    ]);

    $this->post(route('login.store'), [
        'email' => '45678901',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

it('oculta en Usuarios las cuentas de afiliados mientras la función está desactivada', function (): void {
    config(['sistema.usuarios_afiliados' => false]);
    $presidente = User::factory()->create();
    $presidente->forceFill(['role' => Role::PRESIDENTE, 'is_active' => true])->save();
    $presidente->roles()->sync([Role::query()->where('nombre', Role::PRESIDENTE)->firstOrFail()->id]);

    $afiliado = Afiliado::factory()->create(['ci' => '34567890']);
    User::factory()->create([
        'afiliado_id' => $afiliado->id,
        'ci' => '34567890',
        'email' => 'afiliado.34567890@isinuta.test',
        'role' => Role::CONSULTA_PAGOS_MULTAS,
    ]);

    $this->actingAs($presidente->fresh())
        ->get(route('usuarios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('usuarios.data', fn ($usuarios) => collect($usuarios)->pluck('email')->contains($presidente->email)
                && ! collect($usuarios)->pluck('email')->contains('afiliado.34567890@isinuta.test')));
});

it('sincroniza los datos del usuario al actualizar el afiliado', function (): void {
    secretariaParaAfiliados();

    $afiliado = Afiliado::factory()->create([
        'ci' => '87654321',
        'nombres' => 'María',
        'apellidos' => 'García',
    ]);

    $usuario = User::factory()->create([
        'afiliado_id' => $afiliado->id,
        'ci' => '87654321',
        'email' => 'afiliado.87654321@isinuta.test',
        'role' => Role::CONSULTA_PAGOS_MULTAS,
        'name' => 'María García',
        'email_verified_at' => now(),
    ]);
    $usuario->assignRole(Role::CONSULTA_PAGOS_MULTAS);

    $lote = $afiliado->lotes()->first();

    $domiciliaria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    $this->put(route('afiliados.update', $lote->id), [
        'nombres' => 'María Elena',
        'apellidos' => 'García Suárez',
        'ci' => '87654321',
        'telefono' => '79876543',
        'direccion' => null,
        'otb_id' => $lote->otb_id,
        'numero_lote' => $lote->numero_lote ?? '1',
        'fecha_afiliacion' => $lote->fecha_afiliacion->toDateString(),
        'forma_pago_afiliacion' => $lote->forma_pago_afiliacion,
        'monto_afiliacion' => (float) $lote->monto_afiliacion,
        'saldo_afiliacion' => (float) $lote->saldo_afiliacion,
        'categoria_id' => $lote->categoria_id ?? $domiciliaria->id,
        'estado' => Afiliado::ESTADO_ACTIVO,
        'observaciones' => null,
        'servicios' => [],
    ])->assertRedirect(route('afiliados.show', $lote->id));

    $usuario->refresh();
    expect($usuario->name)->toBe('María Elena García Suárez')
        ->and($usuario->ci)->toBe('87654321')
        ->and($usuario->hasVerifiedEmail())->toBeTrue();
});
