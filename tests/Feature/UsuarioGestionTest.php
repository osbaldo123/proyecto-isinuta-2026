<?php

use App\Models\Afiliado;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
});

function userForUsuarioGestion(string $roleName, bool $isSystemAccount = false): User
{
    $user = User::factory()->create();
    $user->forceFill([
        'role' => $roleName,
        'is_system_account' => $isSystemAccount,
        'is_active' => true,
    ])->save();
    $role = Role::query()->where('nombre', $roleName)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

test('secretaria cannot access user management', function (): void {
    $secretaria = userForUsuarioGestion(Role::SECRETARIA, isSystemAccount: true);

    $this->actingAs($secretaria)->get(route('usuarios.index'))->assertForbidden();
});

test('presidente can access user management', function (): void {
    $presidente = userForUsuarioGestion(Role::PRESIDENTE);

    $this->actingAs($presidente)->get(route('usuarios.index'))->assertOk();
});

test('presidente does not auto-create users when listing user management', function (): void {
    $presidente = userForUsuarioGestion(Role::PRESIDENTE);
    $afiliado = Afiliado::factory()->create([
        'ci' => '12345670',
    ]);

    $this->actingAs($presidente)
        ->get(route('usuarios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('usuarios.data', function ($usuarios) use ($presidente) {
                $emails = collect($usuarios)->pluck('email');

                return $emails->contains($presidente->email)
                    && $emails->count() === 1;
            }));

    expect(User::query()->where('afiliado_id', $afiliado->id)->exists())->toBeFalse();
});

test('presidente can create a consulta user', function (): void {
    $presidente = userForUsuarioGestion(Role::PRESIDENTE);

    $this->actingAs($presidente)->post(route('usuarios.store'), [
        'nombres' => 'Usuario Consulta',
        'apellidos' => 'Nuevo',
        'email' => 'nuevo-consulta@example.com',
        'ci' => '87654321',
        'telefono_codigo' => '+591',
        'telefono' => '71234567',
        'rol' => Role::CONSULTA_PAGOS_MULTAS,
        'estado' => 'Activo',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('usuarios.index'));

    $user = User::where('email', 'nuevo-consulta@example.com')->firstOrFail();

    expect($user->is_active)->toBeTrue()
        ->and($user->hasRole(Role::CONSULTA_PAGOS_MULTAS))->toBeTrue()
        ->and($user->is_system_account)->toBeFalse();
});

test('secretaria cannot create users', function (): void {
    $secretaria = userForUsuarioGestion(Role::SECRETARIA, isSystemAccount: true);

    $this->actingAs($secretaria)->post(route('usuarios.store'), [
        'nombres' => 'Usuario Consulta',
        'apellidos' => 'Nuevo',
        'email' => 'nuevo-consulta@example.com',
        'ci' => '87654321',
        'telefono_codigo' => '+591',
        'telefono' => '71234567',
        'rol' => Role::CONSULTA_PAGOS_MULTAS,
        'estado' => 'Activo',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertForbidden();
});

test('presidente cannot assign roles other than consulta', function (): void {
    $presidente = userForUsuarioGestion(Role::PRESIDENTE);

    $this->actingAs($presidente)->post(route('usuarios.store'), [
        'nombres' => 'Intento',
        'apellidos' => 'Admin',
        'email' => 'intento-admin@example.com',
        'ci' => '12345678',
        'telefono_codigo' => '+591',
        'telefono' => '71234567',
        'rol' => Role::ADMIN,
        'estado' => 'Activo',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('rol');
});

test('secretaria and presidente accounts are listed in user management', function (): void {
    $presidente = userForUsuarioGestion(Role::PRESIDENTE);
    $secretaria = userForUsuarioGestion(Role::SECRETARIA, isSystemAccount: true);
    $consulta = userForUsuarioGestion(Role::CONSULTA_PAGOS_MULTAS);

    $this->actingAs($presidente)
        ->get(route('usuarios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('usuarios/Index')
            ->has('usuarios')
            ->where('usuarios.data', function ($usuarios) use ($secretaria, $consulta, $presidente) {
                $emails = collect($usuarios)->pluck('email');

                return $emails->contains($consulta->email)
                    && $emails->contains($secretaria->email)
                    && $emails->contains($presidente->email);
            }));
});

test('presidente can edit secretaria name but cannot delete the account', function (): void {
    $presidente = userForUsuarioGestion(Role::PRESIDENTE);
    $secretaria = userForUsuarioGestion(Role::SECRETARIA, isSystemAccount: true);
    $secretaria->forceFill([
        'ci' => '20000001',
        'telefono_codigo' => '+591',
        'telefono' => '71111111',
    ])->save();

    $this->actingAs($presidente)
        ->put(route('usuarios.update', $secretaria->id), [
            'nombres' => 'Maria Elena',
            'apellidos' => 'Quispe Rojas',
            'email' => $secretaria->email,
            'ci' => '20000001',
            'telefono_codigo' => '+591',
            'telefono' => '71111111',
            'rol' => Role::SECRETARIA,
            'estado' => 'Activo',
        ])
        ->assertRedirect(route('usuarios.index'));

    expect($secretaria->fresh()->name)->toBe('Maria Elena Quispe Rojas');

    $this->actingAs($presidente)
        ->delete(route('usuarios.destroy', $secretaria->id))
        ->assertForbidden();

    expect(User::query()->whereKey($secretaria->id)->exists())->toBeTrue();
});

test('consulta user cannot access user management', function (): void {
    $consulta = userForUsuarioGestion(Role::CONSULTA_PAGOS_MULTAS);

    $this->actingAs($consulta)->get(route('usuarios.index'))->assertForbidden();
});
