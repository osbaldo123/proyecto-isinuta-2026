<?php

use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\User;
use App\Models\Otb;
use Database\Seeders\CategoriasSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $this->seed(CategoriasSeeder::class);
});

function userSprint2(string $roleName = Role::ADMIN): User
{
    $user = User::factory()->create();
    $user->forceFill([
        'role' => $roleName,
        'is_active' => true,
    ])->save();

    $role = Role::query()->where('nombre', $roleName)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

/**
 * @return array<int, float>
 */
function tarifasServicio(float $domiciliar, float $comercial): array
{
    return [
        Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->value('id') => $domiciliar,
        Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->value('id') => $comercial,
    ];
}

it('lista servicios con paginación y permite crear con tarifa por categoría', function (): void {
    $admin = userSprint2();

    $this->actingAs($admin)
        ->get(route('servicios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('servicios/Index')->has('servicios.data'));

    $this->actingAs($admin)->post(route('servicios.store'), [
        'nombre' => 'Conexión agua',
        'codigo' => 'conex',
        'tipo' => 'otro',
        'tarifas' => tarifasServicio(35, 50),
        'descripcion' => 'Instalación domiciliaria',
        'activo' => true,
    ])->assertRedirect(route('servicios.index'));

    $servicio = Servicio::query()->where('nombre', 'Conexión agua')->firstOrFail();
    $comercial = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->firstOrFail();
    $domiciliar = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    expect($servicio->codigo)->toBeNull()
        ->and($servicio->tipo)->toBe('otro')
        ->and($servicio->tarifaParaCategoria($domiciliar))->toBe(35.0)
        ->and($servicio->tarifaParaCategoria($comercial))->toBe(50.0);

    // Solo las categorías que se guardaron al crear (no inventa industrial si no se cargó).
    $this->actingAs($admin)
        ->get(route('servicios.edit', $servicio->id))
        ->assertInertia(fn ($page) => $page
            ->component('servicios/Edit')
            ->has('servicio.tarifas_por_categoria', 2));
});

it('rechaza un servicio con nombre duplicado sin importar mayúsculas', function (): void {
    $admin = userSprint2();
    Servicio::factory()->create(['nombre' => 'Agua potable']);

    $this->actingAs($admin)->post(route('servicios.store'), [
        'nombre' => 'AGUA POTABLE',
        'codigo' => 'AGUA2',
        'tipo' => 'otro',
        'tarifas' => tarifasServicio(8, 17),
        'costo_instalacion_contado' => 1000,
        'costo_instalacion_plazos' => 1200,
        'activo' => true,
    ])->assertSessionHasErrors('nombre');
});

it('muestra la tarifa del agua por categoría en el listado', function (): void {
    $admin = userSprint2();
    $agua = Servicio::factory()->create(['nombre' => 'Agua potable', 'tipo' => 'agua', 'tarifa_mensual' => 8]);
    $domiciliar = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    $this->actingAs($admin)
        ->get(route('servicios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('servicios/Index')
            ->where('servicios.data.0.tarifas_por_categoria', fn ($tarifas): bool => collect($tarifas)
                ->contains(fn ($t): bool => (float) $t['monto'] === (float) $domiciliar->tarifa_agua)));
});
it('permite registrar servicio de inspeccion y filtrar por tipo', function (): void {
    $admin = userSprint2();

    $this->actingAs($admin)->post(route('servicios.store'), [
        'nombre' => 'Inspección domiciliaria',
        'codigo' => 'INSP',
        'tipo' => 'inspeccion',
        'tarifas' => tarifasServicio(20, 20),
        'costo_instalacion_contado' => 0,
        'costo_instalacion_plazos' => 0,
        'activo' => true,
    ])->assertRedirect(route('servicios.index'));

    $this->assertDatabaseHas('servicios', [
        'nombre' => 'Inspección domiciliaria',
        'tipo' => 'inspeccion',
    ]);

    $this->actingAs($admin)
        ->get(route('servicios.index', ['tipo' => 'inspeccion']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('servicios/Index')
            ->where('filtros.tipo', 'inspeccion')
            ->has('servicios.data', 1));
});

it('asigna servicios a cada lote y no a la persona', function (): void {
    $admin = userSprint2();
    $otb = Otb::factory()->create(['activa' => true]);
    $agua = Servicio::factory()->create(['nombre' => 'Agua potable lote', 'tipo' => 'agua', 'activo' => true, 'tarifa_mensual' => 8]);
    $alc = Servicio::factory()->create(['nombre' => 'Alcantarillado lote', 'tipo' => 'alcantarillado', 'activo' => true, 'tarifa_mensual' => 15]);

    $payload = [
        'nombres' => 'Ana',
        'apellidos' => 'Vargas Soto',
        'ci' => '11223344',
        'telefono' => '71112233',
        'direccion' => 'Calle 10',
        'otb_id' => $otb->id,
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 3500,
        'saldo_afiliacion' => 0,
        'categoria_id' => Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->value('id'),
        'estado' => Afiliado::ESTADO_ACTIVO,
        'observaciones' => null,
    ];

    $this->actingAs($admin)->post(route('afiliados.store'), [
        ...$payload,
        'numero_lote' => '1',
        'servicios' => [$agua->id],
    ])->assertRedirect();

    $afiliado = Afiliado::query()->where('ci', '11223344')->firstOrFail();
    $lote1 = $afiliado->lotes()->where('numero_lote', '1')->firstOrFail();

    expect($lote1->servicios()->pluck('servicios.id')->all())->toBe([$agua->id])
        ->and(Schema::hasTable('afiliado_servicio'))->toBeFalse()
        ->and(Schema::hasTable('lote_servicio'))->toBeTrue();

    $this->actingAs($admin)->post(route('afiliados.store'), [
        ...$payload,
        'numero_lote' => '2',
        'direccion' => 'Calle 20',
        'servicios' => [$agua->id, $alc->id],
    ])->assertRedirect();

    $lote2 = $afiliado->fresh()->lotes()->where('numero_lote', '2')->firstOrFail();

    expect($lote1->fresh()->servicios()->pluck('servicios.id')->all())->toEqualCanonicalizing([$agua->id])
        ->and($lote2->servicios()->pluck('servicios.id')->all())->toEqualCanonicalizing([$agua->id, $alc->id]);
});

it('no permite eliminar agua ni alcantarillado y sí otros servicios', function (): void {
    $admin = userSprint2();
    $agua = Servicio::factory()->create(['nombre' => 'Agua potable', 'tipo' => 'agua']);
    $alc = Servicio::factory()->create(['nombre' => 'Alcantarillado', 'tipo' => 'alcantarillado']);
    $otro = Servicio::factory()->create(['nombre' => 'Aporte extraordinario', 'tipo' => 'otro']);

    $this->actingAs($admin)
        ->delete(route('servicios.destroy', $agua->id))
        ->assertRedirect(route('servicios.index'))
        ->assertSessionHas('error');

    $this->actingAs($admin)
        ->delete(route('servicios.destroy', $alc->id))
        ->assertRedirect(route('servicios.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('servicios', ['id' => $agua->id]);
    $this->assertDatabaseHas('servicios', ['id' => $alc->id]);

    $this->actingAs($admin)
        ->delete(route('servicios.destroy', $otro->id))
        ->assertRedirect(route('servicios.index'));

    $this->assertDatabaseMissing('servicios', ['id' => $otro->id]);
});

it('guarda el tipo que se elige a mano en el formulario sin pedir código', function (): void {
    $admin = userSprint2();

    $this->actingAs($admin)->post(route('servicios.store'), [
        'nombre' => 'Agua para riego',
        'tipo' => 'otro',
        'tarifas' => tarifasServicio(5, 9),
        'activo' => true,
    ])->assertRedirect(route('servicios.index'));

    $servicio = Servicio::query()->where('nombre', 'Agua para riego')->firstOrFail();

    expect($servicio->tipo)->toBe('otro')
        ->and($servicio->codigo)->toBeNull()
        ->and($servicio->esBase())->toBeFalse();
});

it('guarda el tipo tal como se escribe y reconoce agua potable escrito a mano', function (): void {
    $admin = userSprint2();

    $this->actingAs($admin)->post(route('servicios.store'), [
        'nombre' => 'Limpieza de cámara',
        'tipo' => '  Mantenimiento  ',
        'tarifas' => tarifasServicio(5, 9),
    ])->assertRedirect(route('servicios.index'));

    $this->actingAs($admin)->post(route('servicios.store'), [
        'nombre' => 'Agua potable',
        'tipo' => 'agua POTABLE',
        'tarifas' => tarifasServicio(8, 17),
    ])->assertRedirect(route('servicios.index'));

    expect(Servicio::query()->where('nombre', 'Limpieza de cámara')->value('tipo'))->toBe('Mantenimiento')
        ->and(Servicio::query()->where('nombre', 'Agua potable')->firstOrFail()->esAgua())->toBeTrue();
});

it('no permite registrar un segundo servicio de agua potable', function (): void {
    $admin = userSprint2();
    Servicio::factory()->create(['codigo' => 'AGUA', 'nombre' => 'Agua potable', 'tipo' => 'agua']);

    $this->actingAs($admin)->post(route('servicios.store'), [
        'nombre' => 'Agua de pozo',
        'codigo' => 'POZO',
        'tipo' => 'agua',
        'tarifas' => tarifasServicio(8, 17),
        'activo' => true,
    ])->assertSessionHasErrors(['tipo' => 'Ya existe un servicio de ese tipo.']);
});

it('actualiza la tarifa del agua sin renombrar la categoría', function (): void {
    $admin = userSprint2();
    $agua = Servicio::factory()->create(['codigo' => 'AGUA', 'nombre' => 'Agua potable', 'tipo' => 'agua']);
    $domiciliar = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();
    $nombreOriginal = $domiciliar->nombre;

    $this->actingAs($admin)->put(route('servicios.update', $agua->id), [
        'nombre' => 'Agua potable',
        'codigo' => 'agua-p',
        'tipo' => 'agua',
        'categoria_id' => $domiciliar->id,
        'tarifas' => [$domiciliar->id => 9],
        'activo' => true,
    ])->assertRedirect(route('servicios.index'));

    expect($agua->fresh()->codigo)->toBe('AGUA')
        ->and($domiciliar->fresh()->nombre)->toBe($nombreOriginal)
        ->and((float) $domiciliar->fresh()->tarifa_agua)->toBe(9.0);
});

it('conserva las tarifas por categoría si el agua cambia a otro tipo', function (): void {
    $admin = userSprint2();
    $agua = Servicio::factory()->create(['codigo' => 'AGUA', 'nombre' => 'Agua potable', 'tipo' => 'agua']);
    $comercial = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->firstOrFail();
    $domiciliar = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();
    $tarifaDomiciliar = (float) $domiciliar->tarifa_agua;

    $this->actingAs($admin)->put(route('servicios.update', $agua->id), [
        'nombre' => 'Agua potable',
        'codigo' => 'AGUA',
        'tipo' => 'otro',
        'tarifas' => [$comercial->id => 40],
        'activo' => true,
    ])->assertRedirect(route('servicios.index'));

    $agua = $agua->fresh();

    expect($agua->tipo)->toBe('otro')
        ->and($agua->tarifaParaCategoria($comercial))->toBe(40.0)
        ->and($agua->tarifaParaCategoria($domiciliar))->toBe($tarifaDomiciliar);
});

it('guarda a mano la tarifa por categoría del agua y la aplica a los lotes', function (): void {
    $admin = userSprint2();
    $agua = Servicio::factory()->create(['codigo' => 'AGUA', 'nombre' => 'Agua potable', 'tipo' => 'agua']);
    $comercial = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->firstOrFail();
    $domiciliar = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    $this->actingAs($admin)->put(route('servicios.update', $agua->id), [
        'nombre' => 'Agua potable',
        'activo' => true,
        'tarifas' => [
            $domiciliar->id => (float) $domiciliar->tarifa_agua,
            $comercial->id => 20,
        ],
    ])->assertRedirect(route('servicios.index'));

    expect((float) $comercial->fresh()->tarifa_agua)->toBe(20.0)
        ->and((float) $domiciliar->fresh()->tarifa_agua)->toBe((float) $domiciliar->tarifa_agua)
        ->and((float) $comercial->fresh()->tarifa_alcantarillado)->toBe((float) $comercial->tarifa_alcantarillado);
});

it('abre el formulario con la categoría de la fila y solo cambia esa tarifa', function (): void {
    $admin = userSprint2();
    $agua = Servicio::factory()->create(['codigo' => 'AGUA', 'nombre' => 'Agua potable', 'tipo' => 'agua']);
    $comercial = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->firstOrFail();
    $domiciliar = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();

    $this->actingAs($admin)
        ->get(route('servicios.edit', ['id' => $agua->id, 'categoria' => $comercial->id]))
        ->assertInertia(fn ($page) => $page
            ->component('servicios/Edit')
            ->where('categoria_id', $comercial->id));

    $this->actingAs($admin)->put(route('servicios.update', $agua->id), [
        'nombre' => 'Agua potable',
        'activo' => true,
        'tarifas' => [$comercial->id => 25],
    ])->assertRedirect(route('servicios.index'));

    expect((float) $comercial->fresh()->tarifa_agua)->toBe(25.0)
        ->and((float) $domiciliar->fresh()->tarifa_agua)->toBe((float) $domiciliar->tarifa_agua);
});
