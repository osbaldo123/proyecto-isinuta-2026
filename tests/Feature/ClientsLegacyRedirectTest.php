<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
});

test('legacy clients index redirects to affiliates', function (): void {
    $secretaria = User::factory()->create(['role' => Role::SECRETARIA]);
    $role = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();
    $secretaria->roles()->sync([$role->id]);

    $this->actingAs($secretaria->fresh())
        ->get(route('clients.index'))
        ->assertRedirect(route('afiliados.index'));
});

test('legacy clients create redirects to affiliates create for admin', function (): void {
    $admin = User::factory()->create(['role' => Role::ADMIN]);
    $role = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();
    $admin->roles()->sync([$role->id]);

    $this->actingAs($admin->fresh())
        ->get(route('clients.create'))
        ->assertRedirect(route('afiliados.create'));
});

test('legacy clients edit redirects to affiliates edit for admin', function (): void {
    $admin = User::factory()->create(['role' => Role::ADMIN]);
    $role = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();
    $admin->roles()->sync([$role->id]);

    $this->actingAs($admin->fresh())
        ->get(route('clients.edit', ['id' => 42]))
        ->assertRedirect('/afiliados/42/edit');
});
