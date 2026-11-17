<?php

use App\Models\ActivityLog;
use App\Models\Configuracion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $this->seed(ConfiguracionSeeder::class);
});

function secretariaUser(): User
{
    $user = User::factory()->create(['role' => Role::SECRETARIA]);
    $role = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

test('secretaria can view system configuration page', function (): void {
    $admin = secretariaUser();

    $this->actingAs($admin)
        ->get(route('configuracion.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('configuracion/Index')
            ->has('configuraciones', 3));
});

test('secretaria can update system configuration', function (): void {
    $admin = secretariaUser();

    $payload = [
        Configuracion::MULTA_TRIMESTRAL => 60,
        Configuracion::MESES_MULTA_TRIMESTRAL => 4,
        Configuracion::MESES_CORTE_SERVICIO => 10,
    ];

    $this->actingAs($admin)
        ->put(route('configuracion.update'), $payload)
        ->assertRedirect(route('configuracion.index'));

    expect(Configuracion::get(Configuracion::MULTA_TRIMESTRAL))->toBe(60.0)
        ->and(Configuracion::get(Configuracion::MESES_MULTA_TRIMESTRAL))->toBe(4)
        ->and(Configuracion::get(Configuracion::MESES_CORTE_SERVICIO))->toBe(10);
});

test('the trimestral fine months must fit in a year', function (): void {
    $this->actingAs(secretariaUser())
        ->put(route('configuracion.update'), [
            Configuracion::MULTA_TRIMESTRAL => 50,
            Configuracion::MESES_MULTA_TRIMESTRAL => 13,
            Configuracion::MESES_CORTE_SERVICIO => 12,
        ])
        ->assertSessionHasErrors(Configuracion::MESES_MULTA_TRIMESTRAL);

    expect(Configuracion::get(Configuracion::MESES_MULTA_TRIMESTRAL))->toBe(3);
});

test('mora fine configuration is saved as part of the Multas module', function (): void {
    $admin = secretariaUser();

    $this->actingAs($admin)
        ->put(route('configuracion.update'), [
            Configuracion::MULTA_TRIMESTRAL => 50,
            Configuracion::MESES_MULTA_TRIMESTRAL => 3,
            Configuracion::MESES_CORTE_SERVICIO => 12,
        ])
        ->assertRedirect(route('configuracion.index'))
        ->assertSessionHas('success', 'Configuración de multas por mora actualizada correctamente.');

    $registro = ActivityLog::query()->latest('id')->firstOrFail();

    expect($registro->modulo)->toBe('Multas')
        ->and($registro->accion)->toBe('Actualización de la configuración de multas por mora');
});

test('presidente cannot access system configuration', function (): void {
    $presidente = User::factory()->create(['role' => Role::PRESIDENTE]);
    $role = Role::query()->where('nombre', Role::PRESIDENTE)->firstOrFail();
    $presidente->roles()->sync([$role->id]);

    $this->actingAs($presidente->fresh())
        ->get(route('configuracion.index'))
        ->assertForbidden();

    $this->actingAs($presidente->fresh())
        ->put(route('configuracion.update'), [])
        ->assertForbidden();
});
