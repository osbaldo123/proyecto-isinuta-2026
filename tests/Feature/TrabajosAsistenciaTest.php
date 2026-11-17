<?php

use App\Models\Afiliado;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\Trabajo;
use App\Models\User;
use App\Models\Otb;
use Database\Seeders\CategoriasSeeder;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $this->seed(CategoriasSeeder::class);
});

function usuarioDeTrabajos(string $roleName = Role::ADMIN): User
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
 * @return array{otb: Otb, presente: Afiliado, ausente: Afiliado, lotePresente: LoteAfiliado, lotesAusente: array<int, LoteAfiliado>, loteAjeno: LoteAfiliado}
 */
function escenarioListaOtb(): array
{
    $otb = Otb::factory()->create(['nombre' => 'Barrio 21', 'activa' => true]);
    $otra = Otb::factory()->create(['nombre' => 'Otra OTB', 'activa' => true]);
    $agua = Servicio::factory()->create(['nombre' => 'Agua potable', 'tipo' => 'agua', 'activo' => true]);

    $presente = Afiliado::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Vargas']);
    $ausente = Afiliado::factory()->create(['nombres' => 'Luis', 'apellidos' => 'Quispe']);

    $lotePresente = $presente->lotes()->firstOrFail();
    $lotePresente->update(['otb_id' => $otb->id, 'numero_lote' => '9101', 'estado' => Afiliado::ESTADO_ACTIVO]);
    $lotePresente->sincronizarServicios([$agua->id], now()->toDateString());

    $loteUno = $ausente->lotes()->firstOrFail();
    $loteUno->update(['otb_id' => $otb->id, 'numero_lote' => '9102', 'estado' => Afiliado::ESTADO_ACTIVO]);
    $loteUno->sincronizarServicios([$agua->id], now()->toDateString());

    $loteDos = $loteUno->replicate();
    $loteDos->numero_lote = '9103';
    $loteDos->save();
    $loteDos->sincronizarServicios([$agua->id], now()->toDateString());

    $loteAjeno = $loteUno->replicate();
    $loteAjeno->otb_id = $otra->id;
    $loteAjeno->numero_lote = '9104';
    $loteAjeno->save();

    return [
        'otb' => $otb,
        'presente' => $presente->fresh(),
        'ausente' => $ausente->fresh(),
        'lotePresente' => $lotePresente->fresh(),
        'lotesAusente' => [$loteUno->fresh(), $loteDos->fresh()],
        'loteAjeno' => $loteAjeno->fresh(),
    ];
}

/**
 * @param  list<array{0: LoteAfiliado, 1: bool}>  $pares
 * @return list<array{lote_afiliado_id: int, presente: bool}>
 */
function marcasPorLote(array $pares): array
{
    return array_map(
        fn (array $par): array => ['lote_afiliado_id' => $par[0]->id, 'presente' => $par[1]],
        $pares,
    );
}

it('registra el trabajo y pasa lista por lote del mismo afiliado', function (): void {
    $admin = usuarioDeTrabajos();
    $escenario = escenarioListaOtb();

    $this->actingAs($admin)
        ->get(route('trabajos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('trabajos/Index'));

    $this->actingAs($admin)->post(route('trabajos.store'), [
        'otb_id' => $escenario['otb']->id,
        'tipo' => 'Limpieza de maleza',
        'descripcion' => 'Limpieza',
        'fecha_inicio' => '2026-07-01',
        'monto_multa' => 20,
    ])->assertRedirect();

    $trabajo = Trabajo::query()->firstOrFail();

    expect($trabajo->otb_id)->toBe($escenario['otb']->id)
        ->and($trabajo->tipo)->toBe('Limpieza de maleza')
        ->and((float) $trabajo->monto_multa)->toBe(20.0);

    $this->actingAs($admin)
        ->get(route('trabajos.show', $trabajo->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('trabajos/Lista')
            ->has('filas', 3)
            ->where('filas', function ($filas) use ($escenario): bool {
                $delAusente = collect($filas)->where('afiliado_id', $escenario['ausente']->id)->values();

                return $delAusente->count() === 2
                    && $delAusente->pluck('numero_lote')->sort()->values()->all() === ['9102', '9103']
                    && $delAusente->every(fn ($fila): bool => $fila['otb'] === 'Barrio 21'
                        && in_array('Agua potable', $fila['servicios'], true));
            }));

    // Luis: falta en ambos lotes; Ana presente en el suyo.
    $this->actingAs($admin)->put(route('trabajos.lista', $trabajo->id), [
        'asistencias' => marcasPorLote([
            [$escenario['lotePresente'], true],
            [$escenario['lotesAusente'][0], false],
            [$escenario['lotesAusente'][1], false],
        ]),
    ])->assertRedirect(route('trabajos.show', $trabajo->id));

    $this->actingAs($admin)
        ->get(route('trabajos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trabajos.data', function ($trabajos): bool {
                $fila = collect($trabajos)->first();

                return (int) ($fila['presentes'] ?? 0) === 1
                    && (int) ($fila['ausentes'] ?? 0) === 2;
            }));

    $multas = Multa::query()->where('trabajo_id', $trabajo->id)->where('estado', Multa::ESTADO_PENDIENTE)->get();

    expect($multas)->toHaveCount(2)
        ->and($multas->pluck('lote_afiliado_id')->sort()->values()->all())
        ->toEqual(collect($escenario['lotesAusente'])->pluck('id')->sort()->values()->all())
        ->and($multas->pluck('lote_afiliado_id'))->not->toContain($escenario['loteAjeno']->id)
        ->and($multas->every(fn (Multa $multa): bool => (float) $multa->monto === 20.0
            && $multa->motivo === Multa::MOTIVO_FALTA_TRABAJO
            && $multa->fecha_generacion->toDateString() === '2026-07-01'
            && str_contains((string) $multa->descripcion, 'Barrio 21')))
        ->toBeTrue()
        ->and(Multa::query()->where('afiliado_id', $escenario['presente']->id)->count())->toBe(0);
});

it('puede marcar presente en un lote y falta en otro del mismo afiliado', function (): void {
    $admin = usuarioDeTrabajos();
    $escenario = escenarioListaOtb();
    $trabajo = Trabajo::factory()->create([
        'otb_id' => $escenario['otb']->id,
        'monto_multa' => 20,
        'fecha_inicio' => '2026-07-01',
        'descripcion' => 'Limpieza',
    ]);

    // Luis: presente en 9102, falta en 9103 → solo una multa.
    $this->actingAs($admin)->put(route('trabajos.lista', $trabajo->id), [
        'asistencias' => marcasPorLote([
            [$escenario['lotePresente'], true],
            [$escenario['lotesAusente'][0], true],
            [$escenario['lotesAusente'][1], false],
        ]),
    ])->assertRedirect();

    $multas = Multa::query()->where('trabajo_id', $trabajo->id)->where('estado', Multa::ESTADO_PENDIENTE)->get();

    expect($multas)->toHaveCount(1)
        ->and($multas->first()->lote_afiliado_id)->toBe($escenario['lotesAusente'][1]->id)
        ->and($multas->first()->afiliado_id)->toBe($escenario['ausente']->id);
});

it('al marcar presente anula la multa pendiente de ese lote', function (): void {
    $admin = usuarioDeTrabajos();
    $escenario = escenarioListaOtb();
    $trabajo = Trabajo::factory()->create([
        'otb_id' => $escenario['otb']->id,
        'monto_multa' => 20,
        'fecha_inicio' => '2026-07-01',
        'descripcion' => 'Limpieza',
    ]);

    $this->actingAs($admin)->put(route('trabajos.lista', $trabajo->id), [
        'asistencias' => marcasPorLote([
            [$escenario['lotePresente'], true],
            [$escenario['lotesAusente'][0], false],
            [$escenario['lotesAusente'][1], false],
        ]),
    ])->assertRedirect();

    $this->actingAs($admin)->put(route('trabajos.lista', $trabajo->id), [
        'asistencias' => marcasPorLote([
            [$escenario['lotePresente'], true],
            [$escenario['lotesAusente'][0], true],
            [$escenario['lotesAusente'][1], true],
        ]),
    ])->assertRedirect();

    expect(Multa::query()->where('trabajo_id', $trabajo->id)->where('estado', Multa::ESTADO_PENDIENTE)->count())->toBe(0)
        ->and(Multa::query()->where('trabajo_id', $trabajo->id)->where('estado', Multa::ESTADO_ANULADA)->count())->toBe(2);
});

it('muestra en gestionar pagos la multa de cualquier lista nueva de trabajos', function (): void {
    $admin = usuarioDeTrabajos();
    $escenario = escenarioListaOtb();
    $lote = $escenario['lotesAusente'][0];

    $zanjeo = Trabajo::factory()->create([
        'otb_id' => $escenario['otb']->id,
        'tipo' => 'Zanjeo',
        'descripcion' => 'Zanja del barrio',
        'fecha_inicio' => '2026-10-01',
        'monto_multa' => 40,
    ]);
    $reunion = Trabajo::factory()->create([
        'otb_id' => $escenario['otb']->id,
        'tipo' => 'Reunión de agua',
        'descripcion' => 'Reunión',
        'fecha_inicio' => '2026-10-02',
        'monto_multa' => 15,
    ]);

    foreach ([$zanjeo, $reunion] as $trabajo) {
        $this->actingAs($admin)->put(route('trabajos.lista', $trabajo->id), [
            'asistencias' => marcasPorLote([
                [$escenario['lotePresente'], true],
                [$escenario['lotesAusente'][0], false],
                [$escenario['lotesAusente'][1], false],
            ]),
        ])->assertRedirect();
    }

    $this->actingAs($admin)
        ->get(route('pagos.create', ['afiliado_id' => $lote->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos/Create')
            ->where('afiliadoSeleccionado.multas_pendientes', function ($multas) use ($lote): bool {
                $trabajos = collect($multas)->where('motivo', Multa::MOTIVO_FALTA_TRABAJO)->values();
                $ids = Multa::query()
                    ->where('lote_afiliado_id', $lote->id)
                    ->where('motivo', Multa::MOTIVO_FALTA_TRABAJO)
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->all();

                return $trabajos->count() === 2
                    && $trabajos->pluck('id')->sort()->values()->all() === $ids
                    && $trabajos->contains(fn ($multa): bool => $multa['etiqueta'] === 'Zanjeo Barrio 21'
                        && (float) $multa['monto'] === 40.0
                        && $multa['concepto'] === 'pago_trabajo')
                    && $trabajos->contains(fn ($multa): bool => $multa['etiqueta'] === 'Reunión de agua Barrio 21'
                        && (float) $multa['monto'] === 15.0
                        && $multa['concepto'] === 'reuniones_agua');
            }));

    $lotePresente = $escenario['lotePresente'];

    $this->actingAs($admin)
        ->get(route('pagos.create', ['afiliado_id' => $lotePresente->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'afiliadoSeleccionado.multas_pendientes',
            fn ($multas): bool => collect($multas)->where('motivo', Multa::MOTIVO_FALTA_TRABAJO)->isEmpty(),
        ));
});

it('el presidente consulta trabajos y no puede registrarlos', function (): void {
    $presidente = usuarioDeTrabajos(Role::PRESIDENTE);
    $otb = Otb::factory()->create(['activa' => true]);

    $this->actingAs($presidente)->get(route('trabajos.index'))->assertOk();

    $this->actingAs($presidente)->post(route('trabajos.store'), [
        'otb_id' => $otb->id,
        'tipo' => 'Limpieza de maleza',
        'descripcion' => 'Limpieza',
        'fecha_inicio' => now()->toDateString(),
        'monto_multa' => 20,
    ])->assertForbidden();
});
