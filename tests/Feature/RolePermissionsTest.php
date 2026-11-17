<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
});

function userWithSystemRole(string $roleName, bool $isSystemAccount = false): User
{
    $user = User::factory()->create([
        'role' => $roleName,
        'is_system_account' => $isSystemAccount,
    ]);
    $role = Role::query()->where('nombre', $roleName)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

test('secretaria can access protected operational routes', function (): void {
    $secretaria = userWithSystemRole(Role::SECRETARIA, isSystemAccount: true);

    $this->actingAs($secretaria)->get(route('afiliados.create'))->assertOk();
    $this->actingAs($secretaria)->get(route('reportes.deudas'))->assertOk();
    $this->actingAs($secretaria)->get(route('usuarios.index'))->assertForbidden();
    $this->actingAs($secretaria)->get(route('roles.index'))->assertForbidden();
});

test('presidente ve ingresos y deudas en el indice de reportes', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);

    $this->actingAs($presidente)
        ->get(route('reportes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Index')
            ->has('reportes', 2)
            ->where('reportes.0.href', route('reportes.ingresos'))
            ->where('reportes.0.titulo', 'Ingresos')
            ->where('reportes.1.href', route('reportes.deudas'))
            ->where('reportes.1.titulo', 'Deudas'));
});

test('presidente can access only assigned modules', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);

    $this->actingAs($presidente)->get(route('afiliados.index'))->assertOk();
    $this->actingAs($presidente)->get(route('afiliados.index'))->assertOk();
    $this->actingAs($presidente)->get(route('pagos.index'))->assertOk();
    $this->actingAs($presidente)->get(route('multas.index'))->assertOk();
    $this->actingAs($presidente)->get(route('reportes.index'))->assertOk();
    $this->actingAs($presidente)->get(route('reportes.deudas'))->assertOk();
    $this->actingAs($presidente)->get(route('reportes.ingresos'))->assertOk();
    $this->actingAs($presidente)->get(route('usuarios.index'))->assertOk();
    $this->actingAs($presidente)->get(route('roles.index'))->assertOk();
    $this->actingAs($presidente)->get(route('servicios.index'))->assertOk();
    $this->actingAs($presidente)->get(route('trabajos.index'))->assertOk();
    $this->actingAs($presidente)->get(route('otb.index'))->assertOk();

    $this->actingAs($presidente)->get(route('configuracion.index'))->assertForbidden();
});

test('presidente cannot access operational management routes', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);

    $this->actingAs($presidente)->get(route('pagos.create'))->assertForbidden();
    $this->actingAs($presidente)->get(route('multas.create'))->assertForbidden();
    $this->actingAs($presidente)->get(route('afiliados.create'))->assertForbidden();
    $this->actingAs($presidente)->get(route('otb.create'))->assertForbidden();
    $this->actingAs($presidente)->get(route('servicios.create'))->assertForbidden();
    $this->actingAs($presidente)->put(route('configuracion.update'), [])->assertForbidden();
});

test('presidente can manage roles', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);

    $this->actingAs($presidente)->get(route('roles.create'))->assertOk();

    $this->actingAs($presidente)
        ->post(route('roles.store'), [
            'nombre' => 'operador_campo',
            'descripcion' => 'Apoyo operativo en otbs.',
            'permissions' => [],
        ])
        ->assertRedirect();

    expect(Role::query()->where('nombre', 'operador_campo')->exists())->toBeTrue();
});

test('presidente can view role detail for institutional roles', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);
    $secretaria = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();

    $this->actingAs($presidente)
        ->get(route('roles.show', $secretaria->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/Show')
            ->where('role.nombre', Role::SECRETARIA)
            ->where('puede_editar', true)
            ->where('puede_dar_baja', true)
            ->where('puede_eliminar', false));
});

test('presidente cannot view admin role in detail', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);
    $admin = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();

    $this->actingAs($presidente)
        ->get(route('roles.show', $admin->id))
        ->assertNotFound();
});

test('presidente can view own role in catalog and detail', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);
    $rolPresidente = Role::query()->where('nombre', Role::PRESIDENTE)->firstOrFail();

    $this->actingAs($presidente)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('roles', function ($roles) {
                $items = collect($roles);
                $rolPresidente = $items->firstWhere('nombre', Role::PRESIDENTE);
                $secretaria = $items->firstWhere('nombre', Role::SECRETARIA);

                return $rolPresidente
                    && $rolPresidente['puede_editar'] === true
                    && $rolPresidente['puede_dar_baja'] === true
                    && $rolPresidente['puede_eliminar'] === false
                    && $secretaria
                    && $secretaria['puede_editar'] === true
                    && $secretaria['puede_dar_baja'] === true
                    && $secretaria['puede_eliminar'] === false;
            }));

    $this->actingAs($presidente)
        ->get(route('roles.show', $rolPresidente->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/Show')
            ->where('role.nombre', Role::PRESIDENTE)
            ->where('role.nombre_label', 'Presidente administrador')
            ->where('puede_editar', true)
            ->where('puede_dar_baja', true)
            ->where('puede_eliminar', false));
});

test('presidente can view and edit custom role detail', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);

    $rol = Role::query()->create([
        'nombre' => 'supervisor_zona',
        'descripcion' => 'Supervisa trabajos de campo',
    ]);

    $this->actingAs($presidente)
        ->get(route('roles.show', $rol->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/Show')
            ->where('role.nombre', 'supervisor_zona')
            ->where('puede_editar', true)
            ->where('puede_dar_baja', true)
            ->where('puede_eliminar', true));
});

test('presidente can filter roles by multicampo search', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);

    Role::query()->create([
        'nombre' => 'supervisor_zona',
        'descripcion' => 'Supervisa trabajos de campo',
    ]);

    $this->actingAs($presidente)
        ->get(route('roles.index', ['q' => 'supervisor']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/Index')
            ->where('filtros.q', 'supervisor')
            ->where('roles', function ($roles) {
                $nombres = collect($roles)->pluck('nombre');

                return $nombres->contains('supervisor_zona')
                    && ! $nombres->contains(Role::PRESIDENTE);
            }));
});

test('presidente no ve roles duplicados de administrador en el catalogo', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);

    $this->actingAs($presidente)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/Index')
            ->where('roles', function ($roles) {
                $nombres = collect($roles)->pluck('nombre');
                $labels = collect($roles)->pluck('nombre_label');

                return ! $nombres->contains(Role::ADMIN)
                    && $nombres->contains(Role::PRESIDENTE)
                    && $labels->duplicates()->isEmpty();
            }));
});

test('presidente no puede crear rol con nombre reservado del sistema', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);

    $this->actingAs($presidente)
        ->post(route('roles.store'), [
            'nombre' => Role::PRESIDENTE,
            'descripcion' => 'Duplicado',
            'permissions' => [],
        ])
        ->assertSessionHasErrors('nombre');
});

test('admin ve todos los roles incluido desarrollo', function (): void {
    $admin = userWithSystemRole(Role::ADMIN);

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('roles', function ($roles) {
                $nombres = collect($roles)->pluck('nombre');

                return $nombres->contains(Role::ADMIN)
                    && $nombres->contains(Role::PRESIDENTE);
            }));
});

test('presidente can edit own role', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);
    $rolPresidente = Role::query()->where('nombre', Role::PRESIDENTE)->firstOrFail();

    $this->actingAs($presidente)
        ->get(route('roles.edit', $rolPresidente->id))
        ->assertOk();

    $this->actingAs($presidente)
        ->put(route('roles.update', $rolPresidente->id), [
            'nombre' => Role::PRESIDENTE,
            'descripcion' => 'Descripción del presidente actualizada',
            'permissions' => [],
        ])
        ->assertRedirect(route('roles.show', $rolPresidente->id))
        ->assertSessionHas('success');

    expect($rolPresidente->fresh()->descripcion)->toBe('Descripción del presidente actualizada')
        ->and($rolPresidente->fresh()->nombre)->toBe(Role::PRESIDENTE);
});

test('presidente can update other system roles', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);
    $secretaria = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();

    $this->actingAs($presidente)
        ->put(route('roles.update', $secretaria->id), [
            'nombre' => Role::SECRETARIA,
            'descripcion' => 'Descripción actualizada',
            'permissions' => [],
        ])
        ->assertRedirect(route('roles.show', $secretaria->id))
        ->assertSessionHas('success');

    expect($secretaria->fresh()->descripcion)->toBe('Descripción actualizada');
});

test('presidente cannot delete own role', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);
    $rolPresidente = Role::query()->where('nombre', Role::PRESIDENTE)->firstOrFail();

    $this->actingAs($presidente)
        ->delete(route('roles.destroy', $rolPresidente->id))
        ->assertRedirect(route('roles.show', $rolPresidente->id))
        ->assertSessionHas('error');

    expect(Role::query()->where('nombre', Role::PRESIDENTE)->exists())->toBeTrue();
});

test('presidente can deactivate own role', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);
    $rolPresidente = Role::query()->where('nombre', Role::PRESIDENTE)->firstOrFail();

    $this->actingAs($presidente)
        ->from(route('roles.index'))
        ->patch(route('roles.toggle-estado', $rolPresidente->id))
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('success');

    expect($rolPresidente->fresh()->activo)->toBeFalse();
});

test('presidente cannot delete institutional roles like secretaria', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);
    $secretaria = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();

    $this->actingAs($presidente)
        ->delete(route('roles.destroy', $secretaria->id))
        ->assertRedirect(route('roles.show', $secretaria->id))
        ->assertSessionHas('error');

    expect(Role::query()->where('nombre', Role::SECRETARIA)->exists())->toBeTrue();
});

test('presidente can deactivate and reactivate institutional roles', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);
    $secretaria = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();

    $this->actingAs($presidente)
        ->from(route('roles.index'))
        ->patch(route('roles.toggle-estado', $secretaria->id))
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('success');

    expect($secretaria->fresh()->activo)->toBeFalse();

    $this->actingAs($presidente)
        ->from(route('roles.index'))
        ->patch(route('roles.toggle-estado', $secretaria->id))
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('success');

    expect($secretaria->fresh()->activo)->toBeTrue();
});

test('presidente can delete custom roles', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);

    $rol = Role::query()->create([
        'nombre' => 'rol_temporal',
        'descripcion' => 'Para prueba',
    ]);

    $this->actingAs($presidente)
        ->delete(route('roles.destroy', $rol->id))
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('success');

    expect(Role::query()->where('nombre', 'rol_temporal')->exists())->toBeFalse();
});

test('consulta user can only access pagos and multas', function (): void {
    $consulta = userWithSystemRole(Role::CONSULTA_PAGOS_MULTAS);

    $this->actingAs($consulta)->get(route('pagos.index'))->assertOk();
    $this->actingAs($consulta)->get(route('multas.index'))->assertOk();
    $this->actingAs($consulta)->get(route('afiliados.index'))->assertForbidden();
    $this->actingAs($consulta)->get(route('usuarios.index'))->assertForbidden();
    $this->actingAs($consulta)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($consulta)->get(route('reportes.deudas'))->assertForbidden();
});

test('un rol personalizado con permisos puede entrar al panel y módulos asignados', function (): void {
    $rol = Role::query()->create([
        'nombre' => 'otro',
        'descripcion' => 'Rol personalizado de prueba',
    ]);
    $rol->syncPermissionsByName(['panel.ver', 'afiliados.ver', 'reportes.ver']);

    $user = User::factory()->create([
        'role' => 'otro',
        'is_active' => true,
    ]);
    $user->roles()->sync([$rol->id]);

    $auth = $user->fresh();

    $this->actingAs($auth)->get(route('dashboard'))->assertOk();
    $this->actingAs($auth)->get(route('afiliados.index'))->assertOk();
    $this->actingAs($auth)->get(route('reportes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Index')
            ->has('reportes', 1)
            ->where('reportes.0.titulo', 'Ingresos'));
    $this->actingAs($auth)->get(route('reportes.ingresos'))->assertOk();
    $this->actingAs($auth)->get(route('pagos.index'))->assertForbidden();
    $this->actingAs($auth)->get(route('roles.index'))->assertForbidden();
});

test('presidente can view afiliados but not manage them', function (): void {
    $presidente = userWithSystemRole(Role::PRESIDENTE);

    expect($presidente->hasPermission('afiliados.ver'))->toBeTrue()
        ->and($presidente->hasPermission('afiliados.gestionar'))->toBeFalse()
        ->and($presidente->hasPermission('roles.ver'))->toBeTrue()
        ->and($presidente->hasPermission('roles.gestionar'))->toBeTrue()
        ->and($presidente->hasPermission('otb.ver'))->toBeTrue()
        ->and($presidente->hasPermission('configuracion.ver'))->toBeFalse();
});
