<?php

/*
 * Cobro mensual desde la fecha de afiliación: una cuota por mes y por servicio, adelantos,
 * inicio de cobro de cada servicio y la multa trimestral: Bs 50 el día 1 del mes siguiente a
 * 3 meses sin pagar; ese mes se cobra normal y no se cuenta (1 abr, 1 ago, 1 dic → Bs 150 al año).
 */

use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\User;
use App\Models\Otb;
use App\Services\CalculadorMultasService;
use App\Services\CuotaMensualService;
use Carbon\Carbon;
use Database\Seeders\CategoriasSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $this->seed(ConfiguracionSeeder::class);
    $this->seed(CategoriasSeeder::class);

    $this->agua = Servicio::factory()->create(['nombre' => 'Agua potable', 'tipo' => 'agua', 'tarifa_mensual' => 8, 'activo' => true]);
    $this->alc = Servicio::factory()->create(['nombre' => 'Alcantarillado', 'tipo' => 'alcantarillado', 'tarifa_mensual' => 15, 'activo' => true]);
    $this->domiciliar = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();
    $this->otb = Otb::factory()->create(['activa' => true]);

    $sec = User::factory()->create();
    $sec->forceFill(['role' => Role::SECRETARIA, 'is_active' => true, 'is_system_account' => true])->save();
    $sec->roles()->sync([Role::query()->where('nombre', Role::SECRETARIA)->value('id')]);
    $this->secretaria = $sec->fresh();
});

function loteMejora(object $t, string $numero, string $fechaAfiliacion, array $servicios): LoteAfiliado
{
    $afiliado = Afiliado::factory()->create(['estado' => Afiliado::ESTADO_ACTIVO]);
    $afiliado->lotes()->delete();
    $lote = $afiliado->lotes()->create([
        'numero_lote' => $numero, 'otb_id' => $t->otb->id, 'direccion' => 'Calle 1',
        'fecha_afiliacion' => $fechaAfiliacion, 'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 3500, 'saldo_afiliacion' => 0, 'estado' => Afiliado::ESTADO_ACTIVO,
        'categoria_id' => $t->domiciliar->id,
    ]);
    $lote->sincronizarServicios($servicios, $fechaAfiliacion);

    return $lote->fresh();
}

function datosEdicion(object $t, LoteAfiliado $lote, string $fecha, array $servicios): array
{
    return [
        'nombres' => $lote->afiliado->nombres, 'apellidos' => $lote->afiliado->apellidos, 'ci' => $lote->afiliado->ci,
        'otb_id' => $t->otb->id, 'numero_lote' => (string) $lote->numero_lote, 'fecha_afiliacion' => $fecha,
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO, 'estado' => Afiliado::ESTADO_ACTIVO,
        'categoria_id' => $t->domiciliar->id, 'servicios' => $servicios,
        'monto_afiliacion_agua' => 3500, 'monto_afiliacion_alcantarillado' => in_array($t->alc->id, $servicios, true) ? 2000 : 0,
    ];
}

it('M1 se puede cobrar el mes en curso y adelantar meses; al cerrarse el mes la cuota nace pagada', function (): void {
    $lote = loteMejora($this, '8001', now()->subMonths(2)->startOfMonth()->toDateString(), [$this->agua->id]);
    app(CuotaMensualService::class)->emitirLote($lote);
    Cuota::query()->where('lote_afiliado_id', $lote->id)->update(['saldo' => 0, 'estado' => Cuota::ESTADO_PAGADA]);

    foreach ([now()->format('Y-m'), now()->addMonth()->format('Y-m')] as $periodo) {
        $this->actingAs($this->secretaria)->post(route('pagos.store'), [
            'afiliado_id' => $lote->id, 'fecha_pago' => now()->toDateString(), 'metodo_pago' => Pago::METODO_EFECTIVO,
            'detalles' => [['concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA, 'monto' => 8, 'servicio_id' => $this->agua->id, 'periodo' => $periodo]],
        ])->assertSessionHasNoErrors();
    }

    expect(Pago::query()->count())->toBe(2);

    // Pasan dos meses: se emiten las cuotas y deben nacer pagadas (saldo 0)
    $this->travelTo(now()->addMonths(2)->startOfMonth()->addDays(2));
    app(CuotaMensualService::class)->emitirLote($lote->fresh());

    $pagadas = Cuota::query()->where('lote_afiliado_id', $lote->id)
        ->whereIn('periodo', [now()->subMonths(2)->format('Y-m'), now()->subMonth()->format('Y-m')])
        ->get();
    expect($pagadas)->toHaveCount(2)
        ->and($pagadas->every(fn (Cuota $c) => (float) $c->saldo === 0.0 && $c->estado === Cuota::ESTADO_PAGADA))->toBeTrue();
});

it('M2 no se puede cobrar dos veces el mismo mes adelantado', function (): void {
    $lote = loteMejora($this, '8002', now()->startOfMonth()->toDateString(), [$this->agua->id]);
    $pago = fn () => $this->actingAs($this->secretaria)->post(route('pagos.store'), [
        'afiliado_id' => $lote->id, 'fecha_pago' => now()->toDateString(), 'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [['concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA, 'monto' => 8, 'servicio_id' => $this->agua->id, 'periodo' => now()->format('Y-m')]],
    ]);

    $pago()->assertSessionHasNoErrors();
    $pago()->assertSessionHasErrors('detalles.0.periodo');
    expect(Pago::query()->count())->toBe(1);
});

it('M3 el recibo de 3 meses muestra una línea por Bs 24 con los meses cubiertos', function (): void {
    $lote = loteMejora($this, '8003', now()->subMonths(3)->startOfMonth()->toDateString(), [$this->agua->id]);
    app(CuotaMensualService::class)->emitirLote($lote);

    $this->actingAs($this->secretaria)->post(route('pagos.store'), [
        'afiliado_id' => $lote->id, 'fecha_pago' => now()->toDateString(), 'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [['concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA, 'descripcion' => 'Consumo de agua', 'monto' => 24, 'servicio_id' => $this->agua->id, 'periodo' => now()->format('Y-m')]],
    ])->assertSessionHasNoErrors();

    $primero = now()->subMonths(3);
    $ultimo = now()->subMonth();
    $this->actingAs($this->secretaria)->get(route('pagos.show', Pago::query()->value('id')))
        ->assertInertia(fn ($page) => $page
            ->where('pago.monto_total', 24)
            ->has('pago.detalles', 1)
            ->where('pago.detalles.0.monto', 24)
            ->where('pago.detalles.0.texto', fn (string $texto) => str_contains($texto, '(3 meses)')
                && str_contains($texto, (string) $ultimo->year)));
});

it('M4 al agregar alcantarillado en una edición se cobra desde ese momento, no desde la afiliación', function (): void {
    $fecha = now()->subMonths(8)->startOfMonth()->toDateString();
    $lote = loteMejora($this, '8004', $fecha, [$this->agua->id]);
    app(CuotaMensualService::class)->emitirLote($lote);

    $this->actingAs($this->secretaria)
        ->put(route('afiliados.update', $lote->id), datosEdicion($this, $lote, $fecha, [$this->agua->id, $this->alc->id]))
        ->assertSessionHasNoErrors();
    app(CuotaMensualService::class)->emitirLote($lote->fresh());

    expect(Cuota::query()->where('lote_afiliado_id', $lote->id)->where('servicio_id', $this->alc->id)->count())->toBe(0)
        ->and(Cuota::query()->where('lote_afiliado_id', $lote->id)->where('servicio_id', $this->agua->id)->count())->toBe(8);
});

it('M5 editar otros datos del lote no cambia el inicio de cobro de cada servicio', function (): void {
    $fecha = now()->subMonths(8)->startOfMonth()->toDateString();
    $lote = loteMejora($this, '8005', $fecha, [$this->agua->id]);
    $lote->servicios()->attach($this->alc->id, ['tarifa_acordada' => 15, 'fecha_inicio' => now()->subMonths(3)->startOfMonth()->toDateString()]);

    $this->actingAs($this->secretaria)
        ->put(route('afiliados.update', $lote->id), datosEdicion($this, $lote->fresh(), $fecha, [$this->agua->id, $this->alc->id]))
        ->assertSessionHasNoErrors();

    $inicioAlc = Carbon::parse($lote->fresh()->servicios()->where('servicios.id', $this->alc->id)->first()->pivot->fecha_inicio);
    expect($inicioAlc->format('Y-m'))->toBe(now()->subMonths(3)->format('Y-m'));
});

it('M6 corregir la fecha de afiliación a una posterior elimina las cuotas sin pagos anteriores a esa fecha', function (): void {
    $fecha = now()->subMonths(8)->startOfMonth()->toDateString();
    $lote = loteMejora($this, '8006', $fecha, [$this->agua->id]);
    app(CuotaMensualService::class)->emitirLote($lote);
    $nueva = now()->subMonths(2)->startOfMonth()->toDateString();

    $this->actingAs($this->secretaria)
        ->put(route('afiliados.update', $lote->id), datosEdicion($this, $lote, $nueva, [$this->agua->id]))
        ->assertSessionHasNoErrors();

    expect(Cuota::query()->where('lote_afiliado_id', $lote->id)->orderBy('periodo')->pluck('periodo')->all())
        ->toBe([now()->subMonths(2)->format('Y-m'), now()->subMonth()->format('Y-m')]);
});

it('M7 un servicio que terminó genera las cuotas de los meses en que estuvo activo', function (): void {
    $lote = loteMejora($this, '8007', now()->subMonths(6)->startOfMonth()->toDateString(), [$this->agua->id]);
    $lote->servicios()->updateExistingPivot($this->agua->id, ['fecha_fin' => now()->subMonths(3)->endOfMonth()->toDateString()]);

    app(CuotaMensualService::class)->emitirLote($lote->fresh());

    expect(Cuota::query()->where('lote_afiliado_id', $lote->id)->count())->toBe(4); // 6,5,4 y 3 meses atrás
});

it('M8 la multa de mora se mantiene al pagar un mes y no se duplica cuando otro mes vuelve a vencer', function (): void {
    // Trimestre may–jun–jul → multa el 1 de agosto.
    $this->travelTo(Carbon::parse('2026-08-05 10:00:00'));
    $lote = loteMejora($this, '8008', '2026-05-01', [$this->agua->id]);

    app(CalculadorMultasService::class)->evaluarLote($lote->fresh());

    $this->actingAs($this->secretaria)->post(route('pagos.store'), [
        'afiliado_id' => $lote->id, 'fecha_pago' => now()->toDateString(), 'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [['concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA, 'monto' => 8, 'servicio_id' => $this->agua->id, 'periodo' => '2026-05']],
    ])->assertSessionHasNoErrors();
    expect(Multa::query()->where('lote_afiliado_id', $lote->id)->value('estado'))->toBe(Multa::ESTADO_PENDIENTE);

    // Mayo ya pagado; jun–jul siguen sin pagar: la multa de agosto se mantiene y no se duplica.
    $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
    $this->actingAs($this->secretaria)->get(route('pagos.create', ['afiliado_id' => $lote->id]))->assertOk();

    $multas = Multa::query()->where('lote_afiliado_id', $lote->id)->get();
    expect($multas)->toHaveCount(1)
        ->and($multas->first()->estado)->toBe(Multa::ESTADO_PENDIENTE)
        ->and($multas->first()->periodo)->toBe('2026-08')
        ->and((float) $multas->first()->saldo)->toBe(50.0);
});

it('M9 el trimestre sep–oct–nov genera la multa de Bs 50 el 1 de diciembre', function (): void {
    $this->travelTo(Carbon::parse('2026-12-01 00:20:00'));
    $lote = loteMejora($this, '8009', '2026-09-01', [$this->agua->id]);

    app(CalculadorMultasService::class)->evaluarLote($lote->fresh());

    $multas = Multa::query()->where('lote_afiliado_id', $lote->id)->get();
    expect($multas)->toHaveCount(1)
        ->and($multas->first()->motivo)->toBe(Multa::MOTIVO_MORA_TRIMESTRAL)
        ->and((float) $multas->first()->monto)->toBe(50.0)
        ->and($multas->first()->periodo)->toBe('2026-12')
        ->and($multas->first()->fecha_generacion->toDateString())->toBe('2026-12-01')
        ->and((int) $multas->first()->gestion)->toBe(2026)
        ->and($multas->first()->descripcion)->toContain('septiembre a noviembre 2026');
});

it('M10 un año sin pagar acumula tramos fijos abr/ago/dic y abre el recibo del año siguiente', function (): void {
    $this->travelTo(Carbon::parse('2027-04-01 00:20:00')); // desde mar 2026: tramos 2026 ago+dic y 2027 abr
    $lote = loteMejora($this, '8010', '2026-03-01', [$this->agua->id]);

    app(CalculadorMultasService::class)->evaluarLote($lote->fresh());
    app(CalculadorMultasService::class)->evaluarLote($lote->fresh()); // evaluar de nuevo no duplica

    $multas = Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', '!=', Multa::ESTADO_ANULADA)->orderBy('periodo')->get();
    // 2026: may–jul → ago y sep–nov → dic (Bs 100). 2027: ene–mar → abr (Bs 50).
    expect($multas->map(fn (Multa $m) => [$m->motivo, $m->periodo, (int) $m->gestion, (float) $m->monto])->all())
        ->toBe([
            [Multa::MOTIVO_MORA_TRIMESTRAL, '2026-12', 2026, 100.0],
            [Multa::MOTIVO_MORA_TRIMESTRAL, '2027-04', 2027, 50.0],
        ])
        ->and((float) Cuota::query()->where('lote_afiliado_id', $lote->id)->sum('saldo'))->toBe(104.0);

    $this->travelTo(Carbon::parse('2027-04-16 10:00:00'));
    app(CalculadorMultasService::class)->evaluarLote($lote->fresh());
    expect(Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', '!=', Multa::ESTADO_ANULADA)->count())->toBe(2)
        ->and((float) Cuota::query()->where('lote_afiliado_id', $lote->id)->sum('saldo'))->toBe(104.0);
});

it('M11 dos años sin pagar generan un recibo de Bs 150 por año', function (): void {
    $this->travelTo(Carbon::parse('2028-01-16 10:00:00')); // 24 meses sin pagar: ene 2026 a dic 2027
    $lote = loteMejora($this, '8011', '2026-01-01', [$this->agua->id]);

    app(CalculadorMultasService::class)->evaluarLote($lote->fresh());
    app(CalculadorMultasService::class)->evaluarLote($lote->fresh());

    $multas = Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', '!=', Multa::ESTADO_ANULADA)->orderBy('periodo')->get();
    expect($multas)->toHaveCount(2)
        ->and($multas->pluck('periodo')->all())->toBe(['2026-12', '2027-12'])
        ->and((float) $multas->sum('monto'))->toBe(300.0) // 150 por año × 2
        ->and($multas->every(fn (Multa $m): bool => (float) $m->monto === 150.0))->toBeTrue();
});

it('M12 sin pagar desde enero: Bs 50 el 1 de abril, acumulado 100 el 1 de agosto y anual 150 el 1 de diciembre', function (): void {
    $lote = loteMejora($this, '8012', '2026-01-01', [$this->agua->id]);
    $calculador = app(CalculadorMultasService::class);
    $acumulado = fn (): float => (float) Multa::query()->where('lote_afiliado_id', $lote->id)
        ->where('estado', Multa::ESTADO_PENDIENTE)->sum('saldo');

    $this->travelTo(Carbon::parse('2026-03-31 18:00:00')); // marzo todavía no termina
    $calculador->evaluarLote($lote->fresh());
    expect($acumulado())->toBe(0.0);

    $this->travelTo(Carbon::parse('2026-04-01 00:20:00')); // pasaron enero, febrero y marzo
    expect($calculador->evaluarLote($lote->fresh()))->toBe(1)
        ->and($acumulado())->toBe(50.0);

    $abril = Multa::query()->where('lote_afiliado_id', $lote->id)->sole();
    expect($abril->periodo)->toBe('2026-04')
        ->and($abril->fecha_generacion->toDateString())->toBe('2026-04-01')
        ->and((float) $abril->monto)->toBe(50.0)
        ->and($abril->descripcion)->toBe('Mora trimestral Bs 50: 3 meses sin pagar (enero a marzo 2026)');

    $this->travelTo(Carbon::parse('2026-07-31 18:00:00')); // abril se cobra normal y no se cuenta
    expect($calculador->evaluarLote($lote->fresh()))->toBe(0)
        ->and($acumulado())->toBe(50.0);

    $this->travelTo(Carbon::parse('2026-08-01 00:20:00')); // mayo, junio y julio → mismo recibo a Bs 100
    expect($calculador->evaluarLote($lote->fresh()))->toBe(1)
        ->and($acumulado())->toBe(100.0)
        ->and(Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', Multa::ESTADO_PENDIENTE)->count())->toBe(1)
        ->and((float) Multa::query()->where('lote_afiliado_id', $lote->id)->where('periodo', '2026-08')->value('monto'))->toBe(100.0);

    $this->travelTo(Carbon::parse('2026-12-01 00:20:00')); // septiembre a noviembre → mismo recibo anual Bs 150
    expect($calculador->evaluarLote($lote->fresh()))->toBe(1)
        ->and($acumulado())->toBe(150.0)
        ->and(Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', Multa::ESTADO_PENDIENTE)->count())->toBe(1)
        ->and(Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', Multa::ESTADO_PENDIENTE)->value('descripcion'))
        ->toBe('Mora anual Bs 150: 3 meses sin pagar (septiembre a noviembre 2026)')
        // Las mensualidades se siguen cobrando cada mes: enero a noviembre, Bs 8 cada una.
        ->and((float) Cuota::query()->where('lote_afiliado_id', $lote->id)->sum('saldo'))->toBe(88.0);

    $this->travelTo(Carbon::parse('2027-04-01 00:20:00')); // año siguiente: recibo aparte Bs 50
    expect($calculador->evaluarLote($lote->fresh()))->toBe(1)
        ->and($acumulado())->toBe(200.0)
        ->and(Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', Multa::ESTADO_PENDIENTE)->count())->toBe(2)
        ->and((float) Multa::query()->where('lote_afiliado_id', $lote->id)->where('periodo', '2027-04')->value('monto'))->toBe(50.0)
        ->and(Multa::query()->where('lote_afiliado_id', $lote->id)->where('periodo', '2027-04')->value('gestion'))->toBe(2027);
});

it('M13 pagar los meses no quita la multa y el mes en que se aplicó no entra en la cuenta siguiente', function (): void {
    $this->travelTo(Carbon::parse('2026-04-05 10:00:00'));
    $lote = loteMejora($this, '8013', '2026-01-01', [$this->agua->id]);
    $calculador = app(CalculadorMultasService::class);
    $calculador->evaluarLote($lote->fresh()); // 1 de abril: enero a marzo

    $this->actingAs($this->secretaria)->post(route('pagos.store'), [
        'afiliado_id' => $lote->id, 'fecha_pago' => now()->toDateString(), 'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [['concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA, 'monto' => 24, 'servicio_id' => $this->agua->id, 'periodo' => '2026-01']],
    ])->assertSessionHasNoErrors();
    expect((float) Cuota::query()->where('lote_afiliado_id', $lote->id)->sum('saldo'))->toBe(0.0);

    // Abril, mayo y junio sin pagar: abril fue el mes de la multa, así que todavía no hay otra.
    $this->travelTo(Carbon::parse('2026-07-01 00:20:00'));
    expect($calculador->evaluarLote($lote->fresh()))->toBe(0);

    $this->travelTo(Carbon::parse('2026-08-01 00:20:00')); // mayo, junio y julio → suma en el mismo recibo
    expect($calculador->evaluarLote($lote->fresh()))->toBe(1);

    $multas = Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', Multa::ESTADO_PENDIENTE)->orderBy('periodo')->get();
    expect($multas)->toHaveCount(1)
        ->and($multas->first()->periodo)->toBe('2026-08')
        ->and((float) $multas->first()->monto)->toBe(100.0);
});

it('M14 las multas sin pagar de la regla anterior se recalculan con la regla nueva y las demás se mantienen', function (): void {
    $this->travelTo(Carbon::parse('2026-11-08 10:00:00'));
    $calculador = app(CalculadorMultasService::class);
    $anterior = fn (LoteAfiliado $lote, string $motivo, int $gestion, float $monto, int $vencidas, string $estado = Multa::ESTADO_PENDIENTE): Multa => Multa::query()->create([
        'afiliado_id' => $lote->afiliado_id, 'lote_afiliado_id' => $lote->id, 'fecha_generacion' => '2026-10-25',
        'motivo' => $motivo, 'gestion' => $gestion, 'cuotas_vencidas' => $vencidas,
        'descripcion' => ($motivo === Multa::MOTIVO_MORA_ANUAL ? 'Mora anual' : 'Mora trimestral')." gestión {$gestion} ({$vencidas} cuotas vencidas)",
        'monto' => $monto, 'saldo' => $estado === Multa::ESTADO_PAGADA ? 0 : $monto, 'estado' => $estado,
    ]);
    $vigentes = fn (LoteAfiliado $lote) => Multa::query()->where('lote_afiliado_id', $lote->id)
        ->where('estado', '!=', Multa::ESTADO_ANULADA)->orderBy('periodo')->get();

    // Debe desde enero (9 meses vencidos al generarla): la trimestral anterior queda como las del 1 de abril y el 1 de agosto.
    $enero = loteMejora($this, '8014', '2026-01-01', [$this->agua->id]);
    $trimestral = $anterior($enero, Multa::MOTIVO_MORA_TRIMESTRAL, 2026, 50, 9);
    $calculador->evaluarLote($enero->fresh());
    expect($trimestral->fresh()->estado)->toBe(Multa::ESTADO_ANULADA)
        ->and($vigentes($enero)->pluck('periodo')->all())->toBe(['2026-08'])
        ->and((float) $vigentes($enero)->sum('saldo'))->toBe(100.0);

    // Debe desde septiembre de 2025: regla anterior → un recibo por año (2025 y 2026).
    $septiembre = loteMejora($this, '8015', '2025-09-01', [$this->agua->id]);
    $t2025 = $anterior($septiembre, Multa::MOTIVO_MORA_TRIMESTRAL, 2025, 50, 13);
    $a2026 = $anterior($septiembre, Multa::MOTIVO_MORA_ANUAL, 2026, 150, 13);
    $calculador->evaluarLote($septiembre->fresh());
    expect($t2025->fresh()->estado)->toBe(Multa::ESTADO_ANULADA)
        ->and($a2026->fresh()->estado)->toBe(Multa::ESTADO_ANULADA)
        ->and($a2026->fresh()->descripcion)->toContain('recalculada con la regla trimestral')
        ->and($vigentes($septiembre)->pluck('periodo')->all())->toBe(['2025-12', '2026-08'])
        ->and((float) $vigentes($septiembre)->sum('saldo'))->toBe(150.0);

    $this->travelTo(Carbon::parse('2026-12-01 00:20:00')); // septiembre a noviembre → recibo 2026 a Bs 150
    expect($calculador->evaluarLote($septiembre->fresh()))->toBe(1)
        ->and((float) $vigentes($septiembre)->sum('saldo'))->toBe(200.0);

    // Ya pagó los meses: la multa anterior se mantiene y queda en el mes en que se generó.
    $this->travelTo(Carbon::parse('2026-11-08 10:00:00'));
    $alDia = loteMejora($this, '8016', '2026-07-01', [$this->agua->id]);
    app(CuotaMensualService::class)->emitirLote($alDia);
    Cuota::query()->where('lote_afiliado_id', $alDia->id)->update(['saldo' => 0, 'estado' => Cuota::ESTADO_PAGADA]);
    $pendiente = $anterior($alDia, Multa::MOTIVO_MORA_TRIMESTRAL, 2026, 50, 3);
    expect($calculador->evaluarLote($alDia->fresh()))->toBe(0)
        ->and($pendiente->fresh()->estado)->toBe(Multa::ESTADO_PENDIENTE)
        ->and($pendiente->fresh()->periodo)->toBe('2026-10');

    // Con trimestres fijos, nov–dic–ene no forman tramo; sep–oct–nov → multa el 1 de dic.
    // Este lote ya pagó las cuotas: no se genera tramo nuevo.
    $this->travelTo(Carbon::parse('2026-12-01 00:20:00'));
    expect($calculador->evaluarLote($alDia->fresh()))->toBe(0)
        ->and($vigentes($alDia)->pluck('periodo')->all())->toBe(['2026-10']);

    // Una multa anterior ya cobrada también se mantiene.
    $this->travelTo(Carbon::parse('2026-11-08 10:00:00'));
    $cobrada = loteMejora($this, '8017', '2026-01-01', [$this->agua->id]);
    $pagada = $anterior($cobrada, Multa::MOTIVO_MORA_TRIMESTRAL, 2026, 50, 9, Multa::ESTADO_PAGADA);
    $calculador->evaluarLote($cobrada->fresh());
    expect($pagada->fresh()->estado)->toBe(Multa::ESTADO_PAGADA)
        ->and($pagada->fresh()->periodo)->toBe('2026-04')
        ->and($vigentes($cobrada)->pluck('periodo')->all())->toBe(['2026-04', '2026-08']);
});

it('M15 recalcular los cortes devuelve a Activo a los cortados con menos de 12 meses vencidos', function (): void {
    $this->travelTo(Carbon::parse('2026-11-08 10:00:00'));
    $corto = loteMejora($this, '8018', '2026-08-01', [$this->agua->id]); // agosto y septiembre vencidos
    $anual = loteMejora($this, '8019', '2025-08-01', [$this->agua->id]); // 14 meses vencidos
    foreach ([$corto, $anual] as $lote) {
        $lote->update(['estado' => Afiliado::ESTADO_CORTADO]);
        $lote->afiliado->update(['estado' => Afiliado::ESTADO_CORTADO]);
    }
    $calculador = app(CalculadorMultasService::class);

    // La revisión de todos los días no lo reactiva: sigue debiendo y para volver a Activo debe pagar todo.
    $calculador->sincronizarCortesPorMora();
    expect($corto->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO);

    expect($calculador->recalcularCortes())->toBeGreaterThanOrEqual(1)
        ->and($corto->fresh()->estado)->toBe(Afiliado::ESTADO_ACTIVO)
        ->and($corto->afiliado->fresh()->estado)->toBe(Afiliado::ESTADO_ACTIVO)
        ->and($anual->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO)
        ->and($anual->afiliado->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO);
});

it('M16 el corte llega con 12 meses vencidos y solo se quita al pagar cuotas y multas', function (): void {
    $lote = loteMejora($this, '8020', '2026-01-01', [$this->agua->id]);
    $calculador = app(CalculadorMultasService::class);

    // Diciembre 2026: gracia 1–2 ene; desde el 3 queda vencido → 12 meses y corte.
    $this->travelTo(Carbon::parse('2027-01-02 10:00:00')); // aún en gracia: 11 meses vencidos
    $calculador->sincronizarMora();
    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_ACTIVO);

    $this->travelTo(Carbon::parse('2027-01-03 10:00:00')); // enero a diciembre de 2026 vencidos
    $calculador->sincronizarMora();
    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO)
        ->and((float) Multa::query()->where('lote_afiliado_id', $lote->id)->sum('saldo'))->toBe(150.0);

    Cuota::query()->where('lote_afiliado_id', $lote->id)->where('periodo', '2026-01')->update(['saldo' => 0, 'estado' => Cuota::ESTADO_PAGADA]);
    $calculador->sincronizarMora();
    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO); // todavía debe

    Cuota::query()->where('lote_afiliado_id', $lote->id)->update(['saldo' => 0, 'estado' => Cuota::ESTADO_PAGADA]);
    $calculador->sincronizarMora();
    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO); // faltan las multas

    Multa::query()->where('lote_afiliado_id', $lote->id)->update(['saldo' => 0, 'estado' => Multa::ESTADO_PAGADA]);
    $calculador->sincronizarMora();
    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_ACTIVO);
});

it('M21 el mes de servicio se cobra el mes siguiente: gracia días 1–2 y vencido desde el 3', function (): void {
    $lote = loteMejora($this, '8021b', '2026-09-01', [$this->agua->id]);
    $meses = app(\App\Services\Pagos\MesesPorCobrarService::class);

    $this->travelTo(Carbon::parse('2026-11-02 10:00:00')); // octubre: último día de gracia
    app(CuotaMensualService::class)->emitirLote($lote->fresh());
    $oct = Cuota::query()->where('lote_afiliado_id', $lote->id)->where('periodo', '2026-10')->sole();
    expect($oct->fecha_vencimiento->toDateString())->toBe('2026-11-02')
        ->and($oct->vencida())->toBeFalse()
        ->and(collect($meses->paraServicio($lote->fresh(), $this->agua))->firstWhere('periodo', '2026-10')['estado'])
        ->toBe('pendiente');

    $this->travelTo(Carbon::parse('2026-11-03 10:00:00')); // desde el 3: vencido
    expect($oct->fresh()->vencida())->toBeTrue()
        ->and(collect($meses->paraServicio($lote->fresh(), $this->agua))->firstWhere('periodo', '2026-10')['estado'])
        ->toBe('vencido')
        ->and(collect($meses->paraServicio($lote->fresh(), $this->agua))->firstWhere('periodo', '2026-09')['estado'])
        ->toBe('vencido');
});

it('M17 al corregir la fecha de afiliación el corte y las multas se vuelven a calcular', function (): void {
    $this->travelTo(Carbon::parse('2026-11-08 10:00:00'));
    $lote = loteMejora($this, '8021', '2025-08-01', [$this->agua->id]); // 14 meses vencidos
    app(CalculadorMultasService::class)->sincronizarMora();
    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO);

    $this->actingAs($this->secretaria)
        ->put(route('afiliados.update', $lote->id), datosEdicion($this, $lote->fresh(), '2026-08-01', [$this->agua->id]))
        ->assertSessionHasNoErrors();

    // Quedan agosto a octubre de 2026: agosto no forma trimestre; sep–oct–nov aún no cierra (falta nov).
    // No hay multa de «ago–oct» el 1 de noviembre.
    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_ACTIVO)
        ->and(Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', Multa::ESTADO_PENDIENTE)->pluck('periodo')->all())->toBe([]);
});

it('M18 al cambiar los meses del corte en Configuración los lotes cortados se vuelven a revisar', function (): void {
    $this->travelTo(Carbon::parse('2026-11-08 10:00:00'));
    $lote = loteMejora($this, '8022', '2025-08-01', [$this->agua->id]); // 14 meses vencidos
    app(CalculadorMultasService::class)->sincronizarCortesPorMora();
    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO);

    $guardar = fn (int $meses) => $this->actingAs($this->secretaria)->put(route('configuracion.update'), [
        Configuracion::MULTA_TRIMESTRAL => 50,
        Configuracion::MESES_MULTA_TRIMESTRAL => 3,
        Configuracion::MESES_CORTE_SERVICIO => $meses,
    ])->assertRedirect(route('configuracion.index'));

    $guardar(18);
    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_ACTIVO);

    $guardar(12);
    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO);
});

it('M19 al abrir el Panel se aplican las multas que faltan aunque no corra la tarea diaria', function (): void {
    $this->travelTo(Carbon::parse('2026-11-08 10:00:00'));
    $lote = loteMejora($this, '8023', '2025-08-01', [$this->agua->id]); // agosto 2025 a octubre 2026 sin pagar
    $alDia = loteMejora($this, '8024', '2026-10-01', [$this->agua->id]); // solo octubre

    $this->actingAs($this->secretaria)->get(route('dashboard'))->assertOk();

    // Trimestres fijos: 2025 dic (sep–nov) y 2026 ago (ene–mar→abr + may–jul→ago = Bs 100; dic aún no llega).
    expect(Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', '!=', Multa::ESTADO_ANULADA)->orderBy('periodo')->pluck('periodo')->all())
        ->toBe(['2025-12', '2026-08'])
        ->and((float) Multa::query()->where('lote_afiliado_id', $lote->id)->where('periodo', '2026-08')->value('monto'))->toBe(100.0)
        ->and($lote->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO)
        ->and(Multa::query()->where('lote_afiliado_id', $alDia->id)->count())->toBe(0)
        ->and($alDia->fresh()->estado)->toBe(Afiliado::ESTADO_ACTIVO);
});

it('M20 agua: un recibo por gestión; el año completo sale como recibo anual', function (): void {
    $this->travelTo(Carbon::parse('2027-01-16 10:00:00'));

    // Cruza 2025 y 2026: al cobrar 5 meses (nov–dic 2025 + ene–mar 2026) → 2 recibos de agua.
    $cruzado = loteMejora($this, '8025', '2025-11-01', [$this->agua->id]);
    app(CuotaMensualService::class)->emitirLote($cruzado);
    $this->actingAs($this->secretaria)->post(route('pagos.store'), [
        'afiliado_id' => $cruzado->id,
        'fecha_pago' => now()->toDateString(),
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [[
            'concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA,
            'descripcion' => 'Consumo de agua',
            'servicio_id' => $this->agua->id,
            'meses' => 5,
            'monto' => 40,
        ]],
    ])->assertSessionHasNoErrors();

    $recibosCruzados = Pago::query()->where('lote_afiliado_id', $cruzado->id)->orderBy('proposito')->get();
    expect($recibosCruzados->pluck('proposito')->all())->toBe(['servicio_agua_potable:2025', 'servicio_agua_potable:2026'])
        ->and((float) $recibosCruzados->sum('monto_total'))->toBe(40.0)
        ->and((float) $recibosCruzados->firstWhere('proposito', 'servicio_agua_potable:2025')->monto_total)->toBe(16.0)
        ->and((float) $recibosCruzados->firstWhere('proposito', 'servicio_agua_potable:2026')->monto_total)->toBe(24.0);

    // Año 2026 completo (12×8 = 96) → un solo recibo anual.
    $anual = loteMejora($this, '8026', '2026-01-01', [$this->agua->id]);
    app(CuotaMensualService::class)->emitirLote($anual);
    $this->actingAs($this->secretaria)->post(route('pagos.store'), [
        'afiliado_id' => $anual->id,
        'fecha_pago' => now()->toDateString(),
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [[
            'concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA,
            'descripcion' => 'Consumo de agua',
            'servicio_id' => $this->agua->id,
            'meses' => 12,
            'monto' => 96,
        ]],
    ])->assertSessionHasNoErrors();

    $reciboAnual = Pago::query()->where('lote_afiliado_id', $anual->id)->sole();
    expect($reciboAnual->proposito)->toBe('servicio_agua_potable:2026')
        ->and((float) $reciboAnual->monto_total)->toBe(96.0)
        ->and($reciboAnual->proposito_label)->toContain('gestión 2026');

    $this->actingAs($this->secretaria)->get(route('pagos.show', $reciboAnual->id))
        ->assertInertia(fn ($page) => $page
            ->where('pago.monto_total', 96)
            ->has('pago.detalles', 1)
            ->where('pago.detalles.0.texto', fn (string $texto) => str_contains($texto, '(anual)')
                && str_contains($texto, '2026')));
});
