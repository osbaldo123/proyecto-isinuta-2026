<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $this->seed(ConfiguracionSeeder::class);
});

test('admin can view debt report page', function (): void {
    $user = User::factory()->create(['role' => Role::ADMIN]);
    $role = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();
    $user->roles()->sync([$role->id]);

    $this->actingAs($user->fresh())
        ->get(route('reportes.deudas'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Deudas')
            ->has('reporte.grupos')
            ->has('totalGeneral')
            ->has('totales')
            ->has('emitido')
            ->has('filtros')
            ->has('opciones.conceptos'));
});

test('admin can export debt report to excel', function (): void {
    $user = User::factory()->create(['role' => Role::ADMIN]);
    $role = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();
    $user->roles()->sync([$role->id]);

    $this->actingAs($user->fresh())
        ->get(route('reportes.deudas.export', ['formato' => 'xlsx']))
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('consulta user cannot view debt report page', function (): void {
    $user = User::factory()->create(['role' => Role::CONSULTA_PAGOS_MULTAS]);
    $role = Role::query()->where('nombre', Role::CONSULTA_PAGOS_MULTAS)->firstOrFail();
    $user->roles()->sync([$role->id]);

    $this->actingAs($user->fresh())
        ->get(route('reportes.deudas'))
        ->assertForbidden();
});
