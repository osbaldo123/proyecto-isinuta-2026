<?php

use App\Models\Afiliado;
use App\Models\Otb;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
});

function adminForOtbs(): User
{
    $user = User::factory()->create(['role' => Role::ADMIN]);
    $role = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

test('admin can create and list otbs in database', function (): void {
    $admin = adminForOtbs();

    $this->actingAs($admin)
        ->post(route('otb.store'), [
            'nombre' => 'OTB Prueba',
            'descripcion' => 'Descripción de prueba',
            'estado' => 'Activa',
        ])
        ->assertRedirect(route('otb.index'));

    $this->assertDatabaseHas('otbs', [
        'nombre' => 'OTB Prueba',
        'activa' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('otb.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('otb/Index')
            ->has('otbs', 1)
            ->where('otbs.0.nombre', 'OTB Prueba'));
});

test('admin alterna una otb entre activa e inactiva sin eliminarla', function (): void {
    $admin = adminForOtbs();
    $otb = Otb::factory()->create(['activa' => true]);

    $this->actingAs($admin)
        ->patch(route('otb.toggle-estado', $otb->id))
        ->assertRedirect(route('otb.index'));

    expect($otb->fresh()->activa)->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('otb.toggle-estado', $otb->id))
        ->assertRedirect(route('otb.index'));

    expect($otb->fresh()->activa)->toBeTrue()
        ->and(Otb::query()->whereKey($otb->id)->exists())->toBeTrue();
});

test('admin cannot delete otb with affiliates', function (): void {
    $admin = adminForOtbs();
    $otb = Otb::factory()->create();
    Afiliado::factory()->conLote(['otb_id' => $otb->id])->create();

    $this->actingAs($admin)
        ->delete(route('otb.destroy', $otb->id))
        ->assertRedirect(route('otb.index'));

    expect(Otb::query()->whereKey($otb->id)->exists())->toBeTrue();
});

test('admin can search otb by name and counts unique affiliates', function (): void {
    $admin = adminForOtbs();
    $otb = Otb::factory()->create(['nombre' => 'OTB Central', 'descripcion' => 'Sector norte']);
    $otra = Otb::factory()->create(['nombre' => 'OTB Sur']);

    $afiliado = Afiliado::factory()->conLote(['otb_id' => $otb->id, 'numero_lote' => '1'])->create();
    $afiliado->lotes()->create([
        'numero_lote' => '2',
        'otb_id' => $otb->id,
        'direccion' => 'Calle B',
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 100,
        'saldo_afiliacion' => 0,
        'estado' => Afiliado::ESTADO_ACTIVO,
    ]);

    $this->actingAs($admin)
        ->get(route('otb.index', ['q' => 'Central']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('otb/Index')
            ->has('otbs', 1)
            ->where('otbs.0.nombre', 'OTB Central')
            ->where('otbs.0.afiliados', 1)
            ->where('otbs.0.lotes', 2)
            ->where('resumen.afiliados', 1)
            ->where('resumen.lotes', 2)
            ->where('filtros.q', 'Central'));

    expect(Otb::query()->whereKey($otra->id)->exists())->toBeTrue();
});
