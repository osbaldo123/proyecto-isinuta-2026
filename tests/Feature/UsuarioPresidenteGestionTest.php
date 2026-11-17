<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
});

test('presidente can update own institutional account even if role column is empty', function (): void {
    $actor = User::factory()->create([
        'role' => Role::PRESIDENTE,
        'is_active' => true,
        'ci' => '11111111',
        'telefono_codigo' => '+591',
        'telefono' => '70000001',
    ]);
    $role = Role::query()->where('nombre', Role::PRESIDENTE)->firstOrFail();
    $actor->roles()->sync([$role->id]);

    $target = User::factory()->create([
        'name' => 'Presidente Actual',
        'role' => null,
        'is_active' => true,
        'ci' => '22222222',
        'telefono_codigo' => '+591',
        'telefono' => '70000002',
    ]);
    $target->roles()->sync([$role->id]);
    $target->forceFill(['role' => null])->save();

    $this->actingAs($actor)
        ->put(route('usuarios.update', $target->id), [
            'nombres' => 'Enrique',
            'apellidos' => 'Nina',
            'email' => $target->email,
            'ci' => '22222222',
            'telefono_codigo' => '+591',
            'telefono' => '70000002',
            'rol' => Role::PRESIDENTE,
            'estado' => 'Activo',
        ])
        ->assertRedirect(route('usuarios.index'))
        ->assertSessionHas('success');

    expect($target->fresh()->name)->toBe('Enrique Nina')
        ->and($target->fresh()->role)->toBe(Role::PRESIDENTE);
});

test('presidente can deactivate institutional presidente account', function (): void {
    $actor = User::factory()->create([
        'role' => Role::PRESIDENTE,
        'is_active' => true,
    ]);
    $role = Role::query()->where('nombre', Role::PRESIDENTE)->firstOrFail();
    $actor->roles()->sync([$role->id]);

    $target = User::factory()->create([
        'role' => Role::PRESIDENTE,
        'is_active' => true,
    ]);
    $target->roles()->sync([$role->id]);

    $this->actingAs($actor)
        ->from(route('usuarios.index'))
        ->patch(route('usuarios.toggle-estado', $target->id))
        ->assertRedirect(route('usuarios.index'))
        ->assertSessionHas('success');

    expect($target->fresh()->is_active)->toBeFalse();
});

test('presidente can deactivate own account from edit form', function (): void {
    $presidente = User::factory()->create([
        'role' => Role::PRESIDENTE,
        'is_active' => true,
        'ci' => '33333333',
        'telefono_codigo' => '+591',
        'telefono' => '70000003',
    ]);
    $role = Role::query()->where('nombre', Role::PRESIDENTE)->firstOrFail();
    $presidente->roles()->sync([$role->id]);

    $this->actingAs($presidente)
        ->put(route('usuarios.update', $presidente->id), [
            'nombres' => 'Enrique',
            'apellidos' => 'Nina',
            'email' => $presidente->email,
            'ci' => '33333333',
            'telefono_codigo' => '+591',
            'telefono' => '70000003',
            'rol' => Role::PRESIDENTE,
            'estado' => 'Inactivo',
            'fecha_final' => now()->toDateString(),
        ])
        ->assertRedirect(route('usuarios.index'))
        ->assertSessionHas('success');

    expect($presidente->fresh()->is_active)->toBeFalse();
});
