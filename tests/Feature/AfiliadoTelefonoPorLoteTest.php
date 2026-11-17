<?php

use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\LoteAfiliado;
use App\Models\Role;
use App\Models\User;
use App\Models\Otb;
use Database\Seeders\CategoriasSeeder;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $this->seed(CategoriasSeeder::class);
});

function secretariaTelefono(): User
{
    $user = User::factory()->create([
        'role' => Role::SECRETARIA,
        'is_system_account' => true,
        'telefono' => null,
    ]);
    $role = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

function payloadLote(Otb $otb, string $ci, string $lote, ?string $telefono): array
{
    return [
        'nombres' => 'Abel',
        'apellidos' => 'Hurtado',
        'ci' => $ci,
        'telefono' => $telefono,
        'direccion' => 'Calle '.$lote,
        'otb_id' => $otb->id,
        'numero_lote' => $lote,
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 0,
        'saldo_afiliacion' => 0,
        'categoria_id' => Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->value('id'),
        'estado' => Afiliado::ESTADO_ACTIVO,
        'servicios' => [],
    ];
}

it('permite el mismo telefono o uno distinto en cada lote de la misma persona', function (): void {
    $user = secretariaTelefono();
    $otb = Otb::factory()->create();
    $otraZona = Otb::factory()->create();

    $persona = Afiliado::factory()->conLote([
        'otb_id' => $otb->id,
        'numero_lote' => '346',
        'telefono' => '70078001',
    ])->create([
        'nombres' => 'Abel',
        'apellidos' => 'Hurtado',
        'ci' => '10078001',
        'telefono' => '70078001',
    ]);

    $this->actingAs($user)->post(route('afiliados.store'), [
        ...payloadLote($otraZona, '10078001', '540', '70078001'),
    ])->assertRedirect();

    $this->actingAs($user)->post(route('afiliados.store'), [
        ...payloadLote($otb, '10078001', '541', '70111222'),
    ])->assertRedirect();

    $lotes = LoteAfiliado::query()->where('afiliado_id', $persona->id)->get()->keyBy('numero_lote');

    expect($lotes)->toHaveCount(3)
        ->and($lotes['346']->telefono)->toBe('70078001')
        ->and($lotes['540']->telefono)->toBe('70078001')
        ->and($lotes['541']->telefono)->toBe('70111222')
        ->and($persona->fresh()->telefono)->toBe('70078001');
});

it('no deja que otra persona use el telefono de un lote', function (): void {
    $user = secretariaTelefono();
    $otb = Otb::factory()->create();

    Afiliado::factory()->conLote([
        'otb_id' => $otb->id,
        'numero_lote' => '346',
        'telefono' => '70078001',
    ])->create([
        'nombres' => 'Abel',
        'apellidos' => 'Hurtado',
        'ci' => '10078001',
        'telefono' => '70078001',
    ]);

    $this->actingAs($user)->post(route('afiliados.store'), [
        ...payloadLote($otb, '10078002', '10', '70078001'),
        'nombres' => 'Pedro',
        'apellidos' => 'Rojas',
    ])->assertSessionHasErrors('telefono');
});

it('al editar un lote cambia solo el telefono de ese lote', function (): void {
    $user = secretariaTelefono();
    $otb = Otb::factory()->create();

    $persona = Afiliado::factory()->conLote([
        'otb_id' => $otb->id,
        'numero_lote' => '346',
        'telefono' => '70078001',
    ])->create([
        'nombres' => 'Abel',
        'apellidos' => 'Hurtado',
        'ci' => '10078001',
        'telefono' => '70078001',
    ]);

    $segundo = $persona->sincronizarLotePrincipal([
        'numero_lote' => '540',
        'otb_id' => $otb->id,
        'telefono' => '70078001',
        'direccion' => 'Lote 78',
        'fecha_afiliacion' => now()->toDateString(),
        'estado' => Afiliado::ESTADO_ACTIVO,
    ]);

    $this->actingAs($user)->put(route('afiliados.update', $segundo->id), [
        ...payloadLote($otb, '10078001', '540', '70999888'),
        'direccion' => 'Lote 78',
    ])->assertRedirect();

    expect($persona->lotes()->where('numero_lote', '346')->value('telefono'))->toBe('70078001')
        ->and($segundo->fresh()->telefono)->toBe('70999888');
});
