<?php

use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CategoriasSeeder;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $this->seed(CategoriasSeeder::class);
});

function adminForCategorias(): User
{
    $user = User::factory()->create(['role' => Role::ADMIN]);
    $role = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

test('admin can create and list categorias', function (): void {
    $admin = adminForCategorias();

    $this->actingAs($admin)
        ->post(route('categorias.store'), [
            'nombre' => 'Categoría Prueba',
            'descripcion' => 'Descripción de prueba',
            'estado' => 'Activa',
        ])
        ->assertRedirect(route('categorias.index'));

    $this->assertDatabaseHas('categorias', [
        'nombre' => 'Categoría Prueba',
        'tarifa_agua' => 0,
        'tarifa_alcantarillado' => 0,
        'activo' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('categorias.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('categorias/Index')
            ->has('categorias', 3));
});

test('admin can toggle categoria estado', function (): void {
    $admin = adminForCategorias();
    $categoria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();
    expect($categoria->activo)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('categorias.toggle-estado', $categoria->id))
        ->assertRedirect(route('categorias.index'));

    expect($categoria->fresh()->activo)->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('categorias.toggle-estado', $categoria->id))
        ->assertRedirect(route('categorias.index'));

    expect($categoria->fresh()->activo)->toBeTrue();
});

test('categorias del sistema tienen tarifas domiciliaria y comercial correctas', function (): void {
    $domiciliaria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();
    $comercial = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->firstOrFail();

    expect((float) $domiciliaria->tarifa_agua)->toBe(8.0)
        ->and((float) $domiciliaria->tarifa_alcantarillado)->toBe(15.0)
        ->and((float) $comercial->tarifa_agua)->toBe(17.0)
        ->and((float) $comercial->tarifa_alcantarillado)->toBe(20.0);
});

test('las tarifas se cambian desde el formulario de servicio y se aplican a los lotes', function (): void {
    $admin = adminForCategorias();
    $categoria = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->firstOrFail();

    $lote = Afiliado::factory()->conLote(['categoria_id' => $categoria->id])->create()->lotes()->first();
    $agua = \App\Models\Servicio::factory()->create(['tipo' => 'agua', 'activo' => true, 'tarifa_mensual' => 17]);
    $alc = \App\Models\Servicio::factory()->create(['tipo' => 'alcantarillado', 'activo' => true, 'tarifa_mensual' => 30]);
    $lote->servicios()->attach([
        $agua->id => ['tarifa_acordada' => 17, 'fecha_inicio' => now()->toDateString(), 'fecha_fin' => null],
        $alc->id => ['tarifa_acordada' => 30, 'fecha_inicio' => now()->toDateString(), 'fecha_fin' => null],
    ]);

    foreach ([[$agua, 20], [$alc, 35]] as [$servicio, $monto]) {
        $this->actingAs($admin)
            ->put(route('servicios.update', $servicio->id), [
                'nombre' => $servicio->nombre,
                'activo' => true,
                'tarifas' => [$categoria->id => $monto],
            ])
            ->assertRedirect(route('servicios.index'));
    }

    expect((float) $categoria->fresh()->tarifa_agua)->toBe(20.0)
        ->and((float) $lote->fresh()->monto_categorizado)->toBe(55.0);
});
