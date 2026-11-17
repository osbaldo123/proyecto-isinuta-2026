<?php

/*
 * Transferencia (cesión) de un lote a otra persona.
 * Regla principal: solo se transfiere un lote con TODAS sus deudas canceladas.
 */

use App\Models\ActivityLog;
use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\CesionLote;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Otb;
use App\Models\Pago;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\User;
use Database\Seeders\CategoriasSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $this->seed(ConfiguracionSeeder::class);
    $this->seed(CategoriasSeeder::class);

    $this->agua = Servicio::factory()->create(['nombre' => 'Agua potable', 'tipo' => 'agua', 'tarifa_mensual' => 8, 'activo' => true]);
    $this->domiciliar = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();
    $this->otb = Otb::factory()->create(['activa' => true]);

    $sec = User::factory()->create();
    $sec->forceFill(['role' => Role::SECRETARIA, 'is_active' => true, 'is_system_account' => true])->save();
    $sec->roles()->sync([Role::query()->where('nombre', Role::SECRETARIA)->value('id')]);
    $this->secretaria = $sec->fresh();

    // Juan Pérez con 3 lotes, todos al día (afiliados este mes: aún no hay meses cerrados que cobrar).
    $this->juan = Afiliado::factory()->create([
        'nombres' => 'Juan', 'apellidos' => 'Pérez', 'ci' => '11111111', 'telefono' => '71111111', 'estado' => Afiliado::ESTADO_ACTIVO,
    ]);
    $this->juan->lotes()->delete();
    $this->lotes = collect(['12', '40', '55'])->map(fn (string $numero) => crearLoteTransferencia($this, $this->juan, $numero));
    $this->lote55 = $this->lotes[2];
});

function crearLoteTransferencia(object $t, Afiliado $afiliado, string $numero, ?string $fecha = null): LoteAfiliado
{
    $fecha ??= now()->startOfMonth()->toDateString();
    $lote = $afiliado->lotes()->create([
        'numero_lote' => $numero, 'otb_id' => $t->otb->id, 'direccion' => "Calle {$numero}", 'telefono' => $afiliado->telefono,
        'fecha_afiliacion' => $fecha, 'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 3500, 'saldo_afiliacion' => 0, 'estado' => Afiliado::ESTADO_ACTIVO,
        'categoria_id' => $t->domiciliar->id,
    ]);
    $lote->sincronizarServicios([$t->agua->id], $fecha);

    return $lote->fresh();
}

function datosTransferencia(array $extra = []): array
{
    return array_merge([
        'ci' => '22222222', 'nombres' => 'María', 'apellidos' => 'Quispe', 'telefono' => '72222222',
        'fecha_cesion' => now()->toDateString(), 'motivo' => 'Venta del lote', 'documento_respaldo' => 'Minuta 45/2026',
    ], $extra);
}

it('T1 transfiere un lote al día a una persona nueva y Juan conserva sus otros 2 lotes', function (): void {
    $this->actingAs($this->secretaria)
        ->post(route('afiliados.transferir.store', $this->lote55->id), datosTransferencia())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('afiliados.show', $this->lote55->id));

    $maria = Afiliado::query()->where('ci', '22222222')->firstOrFail();
    $lote = $this->lote55->fresh();

    // Es el mismo lote (mismo id y número); solo cambió el titular y su teléfono.
    expect($lote->afiliado_id)->toBe($maria->id)
        ->and($lote->numero_lote)->toBe('55')
        ->and($lote->telefono)->toBe('72222222')
        ->and($maria->nombre_completo)->toBe('María Quispe')
        ->and($this->juan->fresh()->lotes()->pluck('numero_lote')->sort()->values()->all())->toBe(['12', '40'])
        ->and($this->juan->fresh()->estado)->toBe(Afiliado::ESTADO_ACTIVO);

    $cesion = CesionLote::query()->sole();
    expect($cesion->lote_afiliado_id)->toBe($lote->id)
        ->and($cesion->afiliado_anterior_id)->toBe($this->juan->id)
        ->and($cesion->nombre_anterior)->toBe('Juan Pérez')
        ->and($cesion->ci_anterior)->toBe('11111111')
        ->and($cesion->nombre_nuevo)->toBe('María Quispe')
        ->and($cesion->documento_respaldo)->toBe('Minuta 45/2026')
        ->and($cesion->registrado_por)->toBe($this->secretaria->id);

    expect(ActivityLog::query()->where('accion', 'like', 'Transferencia del lote 55%')->exists())->toBeTrue();
});

it('T1b si cede su único lote, el titular anterior pasa a baja y sus recibos siguen existiendo', function (): void {
    $solo = Afiliado::factory()->create([
        'nombres' => 'Ana', 'apellidos' => 'Rojas', 'ci' => '44444444', 'estado' => Afiliado::ESTADO_ACTIVO,
    ]);
    $solo->lotes()->delete();
    $loteUnico = crearLoteTransferencia($this, $solo, '88');

    $this->actingAs($this->secretaria)
        ->post(route('afiliados.transferir.store', $loteUnico->id), datosTransferencia([
            'ci' => '55555555', 'nombres' => 'Luis', 'apellidos' => 'Cruz',
        ]))
        ->assertSessionHasNoErrors();

    expect($solo->fresh()->estado)->toBe(Afiliado::ESTADO_BAJA)
        ->and($solo->fresh()->lotes()->count())->toBe(0)
        ->and($loteUnico->fresh()->afiliado_id)->toBe(Afiliado::query()->where('ci', '55555555')->value('id'))
        ->and(Afiliado::query()->where('ci', '55555555')->value('estado'))->toBe(Afiliado::ESTADO_ACTIVO);
});

it('T2 transfiere a una persona ya registrada por su CI sin cambiar sus datos', function (): void {
    $pedro = Afiliado::factory()->create(['nombres' => 'Pedro', 'apellidos' => 'Mamani', 'ci' => '33333333', 'telefono' => '73333333']);
    $pedro->lotes()->delete();
    crearLoteTransferencia($this, $pedro, '90');

    $this->actingAs($this->secretaria)
        ->post(route('afiliados.transferir.store', $this->lote55->id), datosTransferencia([
            'ci' => '33333333', 'nombres' => '', 'apellidos' => '', 'telefono' => '',
        ]))
        ->assertSessionHasNoErrors();

    $lote = $this->lote55->fresh();
    expect($lote->afiliado_id)->toBe($pedro->id)
        ->and($lote->telefono)->toBe('73333333')
        ->and($pedro->fresh()->nombre_completo)->toBe('Pedro Mamani')
        ->and($pedro->fresh()->lotes()->count())->toBe(2)
        ->and(Afiliado::query()->where('ci', '33333333')->count())->toBe(1);
});

it('T3 no transfiere si el lote tiene cuotas mensuales pendientes', function (): void {
    $conDeuda = crearLoteTransferencia($this, $this->juan, '70', now()->subMonths(3)->startOfMonth()->toDateString());

    $this->actingAs($this->secretaria)
        ->post(route('afiliados.transferir.store', $conDeuda->id), datosTransferencia())
        ->assertSessionHasErrors('lote');

    $deuda = app(\App\Services\TransferenciaLoteService::class)->deudaDelLote($conDeuda->fresh());
    expect($deuda['al_dia'])->toBeFalse()
        ->and($deuda['meses'])->toBeGreaterThanOrEqual(3)
        ->and($deuda['cuotas'])->toBeGreaterThan(0)
        ->and($conDeuda->fresh()->afiliado_id)->toBe($this->juan->id)
        ->and(CesionLote::query()->count())->toBe(0)
        ->and(Afiliado::query()->where('ci', '22222222')->exists())->toBeFalse();
});

it('T4 no transfiere si el lote tiene una multa pendiente o saldo de afiliación', function (): void {
    Multa::query()->create([
        'afiliado_id' => $this->juan->id, 'lote_afiliado_id' => $this->lote55->id, 'fecha_generacion' => now()->toDateString(),
        'motivo' => 'inasistencia', 'gestion' => (int) now()->year, 'descripcion' => 'Falta a trabajo',
        'monto' => 50, 'saldo' => 50, 'estado' => Multa::ESTADO_PENDIENTE,
    ]);

    $this->actingAs($this->secretaria)
        ->post(route('afiliados.transferir.store', $this->lote55->id), datosTransferencia())
        ->assertSessionHasErrors('lote');

    $lote40 = $this->lotes[1];
    $lote40->update(['forma_pago_afiliacion' => Afiliado::FORMA_PLAZO, 'saldo_afiliacion' => 500, 'saldo_afiliacion_agua' => 500]);

    $this->actingAs($this->secretaria)
        ->post(route('afiliados.transferir.store', $lote40->id), datosTransferencia())
        ->assertSessionHasErrors('lote');

    expect(CesionLote::query()->count())->toBe(0)
        ->and($this->juan->fresh()->lotes()->count())->toBe(3);
});

it('T5 cuando se cancela la deuda el lote ya se puede transferir', function (): void {
    $conDeuda = crearLoteTransferencia($this, $this->juan, '70', now()->subMonths(2)->startOfMonth()->toDateString());

    $this->actingAs($this->secretaria)->get(route('afiliados.transferir', $conDeuda->id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('afiliados/Transferir')
            ->where('deuda.al_dia', false)
            ->whereNot('bloqueo', null));

    Cuota::query()->where('lote_afiliado_id', $conDeuda->id)->update(['saldo' => 0, 'estado' => Cuota::ESTADO_PAGADA]);

    $this->actingAs($this->secretaria)->get(route('afiliados.transferir', $conDeuda->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('deuda.al_dia', true)
            ->where('bloqueo', null)
            ->where('titular.nombre_completo', 'Juan Pérez')
            ->has('titular.otros_lotes', 3));

    $this->actingAs($this->secretaria)
        ->post(route('afiliados.transferir.store', $conDeuda->id), datosTransferencia())
        ->assertSessionHasNoErrors();

    expect($conDeuda->fresh()->afiliado->ci)->toBe('22222222');
});

it('T6 los recibos anteriores siguen a nombre de quien pagó', function (): void {
    $pago = Pago::factory()->create([
        'afiliado_id' => $this->juan->id, 'lote_afiliado_id' => $this->lote55->id, 'estado' => 'emitido',
    ]);

    $this->actingAs($this->secretaria)
        ->post(route('afiliados.transferir.store', $this->lote55->id), datosTransferencia())
        ->assertSessionHasNoErrors();

    expect($pago->fresh()->afiliado_id)->toBe($this->juan->id)
        ->and($pago->fresh()->lote_afiliado_id)->toBe($this->lote55->id);
});

it('T7 no permite transferir el lote a su mismo titular ni con datos incompletos', function (): void {
    $this->actingAs($this->secretaria)
        ->post(route('afiliados.transferir.store', $this->lote55->id), datosTransferencia(['ci' => '11111111']))
        ->assertSessionHasErrors('ci');

    $this->actingAs($this->secretaria)
        ->post(route('afiliados.transferir.store', $this->lote55->id), datosTransferencia(['nombres' => '', 'motivo' => '']))
        ->assertSessionHasErrors(['nombres', 'motivo']);

    $this->actingAs($this->secretaria)
        ->post(route('afiliados.transferir.store', $this->lote55->id), datosTransferencia(['fecha_cesion' => now()->addDay()->toDateString()]))
        ->assertSessionHasErrors('fecha_cesion');

    expect(CesionLote::query()->count())->toBe(0);
});

it('T8 la ficha del lote muestra el historial de titulares', function (): void {
    $this->actingAs($this->secretaria)
        ->post(route('afiliados.transferir.store', $this->lote55->id), datosTransferencia())
        ->assertSessionHasNoErrors();

    $this->actingAs($this->secretaria)->get(route('afiliados.show', $this->lote55->id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('afiliados/Show')
            ->where('afiliado.nombre_completo', 'María Quispe')
            ->has('historial_titulares', 1)
            ->where('historial_titulares.0.anterior.nombre', 'Juan Pérez')
            ->where('historial_titulares.0.nuevo.nombre', 'María Quispe')
            ->where('historial_titulares.0.motivo', 'Venta del lote'));
});

it('T9 buscar titular por CI responde si la persona existe y sus lotes', function (): void {
    $this->actingAs($this->secretaria)
        ->getJson(route('afiliados.transferir.buscar', ['id' => $this->lote55->id, 'ci' => '11111111']))
        ->assertOk()
        ->assertJson(['encontrado' => true, 'es_titular_actual' => true])
        ->assertJsonCount(3, 'persona.lotes');

    $this->actingAs($this->secretaria)
        ->getJson(route('afiliados.transferir.buscar', ['id' => $this->lote55->id, 'ci' => '99999999']))
        ->assertOk()
        ->assertJson(['valido' => true, 'encontrado' => false]);
});

it('T10 el presidente (solo consulta) no puede transferir lotes', function (): void {
    $pres = User::factory()->create();
    $pres->forceFill(['role' => Role::PRESIDENTE, 'is_active' => true, 'is_system_account' => true])->save();
    $pres->roles()->sync([Role::query()->where('nombre', Role::PRESIDENTE)->value('id')]);

    $this->actingAs($pres->fresh())->get(route('afiliados.transferir', $this->lote55->id))->assertForbidden();
    $this->actingAs($pres->fresh())
        ->post(route('afiliados.transferir.store', $this->lote55->id), datosTransferencia())
        ->assertForbidden();

    expect($this->lote55->fresh()->afiliado_id)->toBe($this->juan->id);
});
