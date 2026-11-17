<?php

/*
 * Módulo Pagos: cobro de varios meses, validaciones de caja, anulación de recibos
 * y estado de cuenta. La fecha se fija en el 20/10/2026 para que los meses sean exactos.
 */

use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\TipoAfiliacion;
use App\Models\User;
use App\Models\Otb;
use App\Services\CalculadorMultasService;
use App\Services\CuotaMensualService;
use App\Services\Pagos\CobroService;
use App\Services\Pagos\MesesPorCobrarService;
use Carbon\Carbon;
use Database\Seeders\CategoriasSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
    $this->seed(PermissionsSeeder::class);
    $this->seed(ConfiguracionSeeder::class);
    $this->seed(CategoriasSeeder::class);

    $this->agua = Servicio::factory()->create(['nombre' => 'Agua potable', 'tipo' => 'agua', 'tarifa_mensual' => 8, 'activo' => true]);
    $this->alc = Servicio::factory()->create(['nombre' => 'Alcantarillado', 'tipo' => 'alcantarillado', 'tarifa_mensual' => 15, 'activo' => true]);
    $this->domiciliar = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();
    $this->otb = Otb::factory()->create(['activa' => true]);
    $this->cajera = usuarioModuloPagos(Role::SECRETARIA);
});

function usuarioModuloPagos(string $rol): User
{
    $user = User::factory()->create();
    $user->forceFill(['role' => $rol, 'is_active' => true, 'is_system_account' => true])->save();
    $user->roles()->sync([Role::query()->where('nombre', $rol)->value('id')]);

    return $user->fresh();
}

/**
 * @param  list<int>  $servicios
 * @param  array<string, mixed>  $extra
 */
function loteModuloPagos(object $t, string $numero, string $fechaAfiliacion, array $servicios, array $extra = []): LoteAfiliado
{
    $afiliado = Afiliado::factory()->create(['estado' => Afiliado::ESTADO_ACTIVO]);
    $afiliado->lotes()->delete();
    $lote = $afiliado->lotes()->create([
        'numero_lote' => $numero,
        'otb_id' => $t->otb->id,
        'direccion' => 'Calle 1',
        'fecha_afiliacion' => $fechaAfiliacion,
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 0,
        'saldo_afiliacion' => 0,
        'estado' => Afiliado::ESTADO_ACTIVO,
        'categoria_id' => $t->domiciliar->id,
        ...$extra,
    ]);
    $lote->sincronizarServicios($servicios, $fechaAfiliacion);

    return $lote->fresh(['afiliado']);
}

/**
 * @return array<string, mixed>
 */
function lineaDeMeses(Servicio $servicio, int $meses, float $monto): array
{
    return [
        'concepto' => $servicio->esAgua() ? PagoDetalle::CONCEPTO_CONSUMO_AGUA : PagoDetalle::CONCEPTO_PAGO_ALCANTARILLADO,
        'descripcion' => $servicio->esAgua() ? 'Consumo de agua' : 'Pago de alcantarillado',
        'servicio_id' => $servicio->id,
        'meses' => $meses,
        'monto' => $monto,
    ];
}

/**
 * @param  list<array<string, mixed>>  $lineas
 * @param  array<string, mixed>  $extra
 */
function cobrarEnCaja(object $t, LoteAfiliado $lote, array $lineas, array $extra = []): TestResponse
{
    return $t->actingAs($t->cajera)->post(route('pagos.store'), [
        'afiliado_id' => $lote->id,
        'fecha_pago' => now()->toDateString(),
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => $lineas,
        ...$extra,
    ]);
}

function textoDeExcel(TestResponse $response): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($ruta, $response->streamedContent());
    $filas = IOFactory::load($ruta)->getActiveSheet()->toArray();
    @unlink($ruta);

    return collect($filas)->flatten()->filter(fn ($celda): bool => filled($celda))->implode("\n");
}

it('la pantalla de cobro ofrece, por servicio, los meses vencidos, el mes en curso y hasta 12 adelantos', function (): void {
    $lote = loteModuloPagos($this, '7001', '2026-07-01', [$this->agua->id, $this->alc->id]);

    $this->actingAs($this->cajera)
        ->get(route('pagos.create', ['afiliado_id' => $lote->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos/Create')
            ->where('afiliadoSeleccionado.servicios_cobro.0.nombre', 'Agua potable')
            ->where('afiliadoSeleccionado.servicios_cobro.0.vencidos', 3)
            ->has('afiliadoSeleccionado.servicios_cobro.0.meses', 3 + 1 + 12)
            ->where('afiliadoSeleccionado.servicios_cobro.0.meses.0.periodo', '2026-07')
            ->where('afiliadoSeleccionado.servicios_cobro.0.meses.0.estado', MesesPorCobrarService::ESTADO_VENCIDO)
            ->where('afiliadoSeleccionado.servicios_cobro.0.meses.3.periodo', '2026-10')
            ->where('afiliadoSeleccionado.servicios_cobro.0.meses.3.estado', MesesPorCobrarService::ESTADO_ACTUAL)
            ->where('afiliadoSeleccionado.servicios_cobro.0.meses.15.periodo', '2027-10')
            ->where('afiliadoSeleccionado.servicios_cobro.0.meses.15.estado', MesesPorCobrarService::ESTADO_ADELANTO)
            ->where('afiliadoSeleccionado.servicios_cobro.1.nombre', 'Alcantarillado')
            ->where('afiliadoSeleccionado.servicios_cobro.1.meses.0.monto', 15));
});

it('cobra varios meses en un solo recibo, del más antiguo al más reciente', function (): void {
    $lote = loteModuloPagos($this, '7002', '2026-07-01', [$this->agua->id]);

    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 5, 40)])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $pago = Pago::query()->with('detalles')->sole();

    expect($pago->tipo_recibo)->toBe(Pago::TIPO_INGRESO)
        ->and((float) $pago->monto_total)->toBe(40.0)
        ->and($pago->cobro_id)->not->toBeNull()
        ->and($pago->proposito_label)->toContain('domiciliar')
        ->and($pago->detalles->every(fn ($d): bool => str_contains((string) $d->descripcion, 'domiciliar')))->toBeTrue()
        ->and($pago->detalles->pluck('periodo')->all())->toBe(['2026-07', '2026-08', '2026-09', '2026-10', '2026-11'])
        ->and($pago->detalles->whereNotNull('cuota_id'))->toHaveCount(3)
        ->and((float) Cuota::query()->where('lote_afiliado_id', $lote->id)->sum('saldo'))->toBe(0.0);

    $this->actingAs($this->cajera)
        ->get(route('pagos.show', $pago->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos/Show')
            ->has('pago.detalles', 1)
            ->where('pago.detalles.0.meses', 5)
            ->where('pago.detalles.0.monto', 40)
            ->where('pago.afiliado.categoria', 'domiciliar')
            ->where('pago.detalles.0.texto_base', fn (string $texto): bool => str_contains(mb_strtolower($texto), 'domiciliar')));
});

it('agua y alcantarillado del mismo cobro salen en dos recibos enlazados', function (): void {
    $lote = loteModuloPagos($this, '7003', '2026-09-01', [$this->agua->id, $this->alc->id]);

    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 2, 16), lineaDeMeses($this->alc, 2, 30)])
        ->assertSessionHasNoErrors();

    $pagos = Pago::query()->orderBy('id')->get();

    expect($pagos)->toHaveCount(2)
        ->and($pagos->pluck('cobro_id')->unique())->toHaveCount(1)
        ->and($pagos->pluck('tipo_recibo')->unique()->values()->all())->toBe([Pago::TIPO_INGRESO])
        ->and((float) $pagos->sum('monto_total'))->toBe(46.0);

    $this->actingAs($this->cajera)
        ->get(route('pagos.show', $pagos[0]->id))
        ->assertInertia(fn ($page) => $page->has('pago.recibos_del_cobro', 2));
});

it('rechaza montos, conceptos y líneas repetidas que no cuadran con los meses', function (): void {
    $lote = loteModuloPagos($this, '7004', '2026-09-01', [$this->agua->id]);

    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 2, 10)])
        ->assertSessionHasErrors(['detalles.0.monto' => '2 meses de agua potable suman Bs 16,00. Vuelva a abrir el cobro.']);

    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 1, 8), lineaDeMeses($this->agua, 1, 8)])
        ->assertSessionHasErrors(['detalles.1.meses' => 'Ese servicio ya está en este cobro. Junte los meses en una sola línea.']);

    cobrarEnCaja($this, $lote, [[...lineaDeMeses($this->agua, 1, 8), 'concepto' => PagoDetalle::CONCEPTO_MULTA]])
        ->assertSessionHasErrors(['detalles.0.meses' => 'Solo las mensualidades de agua o alcantarillado se cobran por meses.']);

    expect(Pago::query()->count())->toBe(0);
});

it('permite adelantar hasta 12 meses y no más', function (): void {
    $lote = loteModuloPagos($this, '7005', '2026-10-01', [$this->agua->id]);

    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 14, 112)])
        ->assertSessionHasErrors(['detalles.0.meses' => 'Se pueden cobrar hasta 13 meses de agua potable: los que debe y hasta 12 adelantados.']);

    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 13, 104)])->assertSessionHasNoErrors();

    // Un cobro que cruza gestiones emite un recibo por año; el último mes cubierto es oct 2027.
    $ultimoPeriodo = Pago::query()
        ->with('detalles')
        ->get()
        ->flatMap(fn (Pago $pago) => $pago->detalles)
        ->sortBy('periodo')
        ->pluck('periodo')
        ->last();

    expect($ultimoPeriodo)->toBe('2027-10');

    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 1, 8)])
        ->assertSessionHasErrors(['detalles.0.meses' => 'No hay meses de agua potable por cobrar.']);
});

it('un mes adelantado no se cobra dos veces y su cuota nace pagada al cerrarse el mes', function (): void {
    $lote = loteModuloPagos($this, '7006', '2026-10-01', [$this->agua->id]);

    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 2, 16)])->assertSessionHasNoErrors();

    expect(app(MesesPorCobrarService::class)->paraServicio($lote->fresh(), $this->agua)[0]['periodo'])->toBe('2026-12');

    cobrarEnCaja($this, $lote, [[
        'concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA,
        'descripcion' => 'Consumo de agua',
        'servicio_id' => $this->agua->id,
        'periodo' => '2026-11',
        'monto' => 8,
    ]])->assertSessionHasErrors(['detalles.0.periodo' => 'Ese mes ya tiene un recibo emitido para este lote y servicio.']);

    $this->travelTo(Carbon::parse('2026-12-05 09:00:00'));
    app(CuotaMensualService::class)->emitirLote($lote->fresh());

    $cuotas = Cuota::query()->where('lote_afiliado_id', $lote->id)->orderBy('periodo')->get();

    expect($cuotas->pluck('periodo')->all())->toBe(['2026-10', '2026-11'])
        ->and($cuotas->every(fn (Cuota $c): bool => (float) $c->saldo === 0.0 && $c->estado === Cuota::ESTADO_PAGADA))->toBeTrue();
});

it('no acepta una fecha de pago futura', function (): void {
    $lote = loteModuloPagos($this, '7007', '2026-10-01', [$this->agua->id]);

    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 1, 8)], ['fecha_pago' => '2026-10-21'])
        ->assertSessionHasErrors(['fecha_pago' => 'La fecha de pago no puede ser posterior a hoy.']);

    expect(Pago::query()->count())->toBe(0);
});

it('la afiliación al contado se cobra completa y una sola vez por lote', function (): void {
    $lote = loteModuloPagos($this, '7008', '2026-10-01', [$this->agua->id]);
    $afiliacion = fn (float $monto): array => [
        'concepto' => 'afiliacion_'.TipoAfiliacion::CODIGO_AGUA,
        'descripcion' => 'Afiliación agua al contado',
        'monto' => $monto,
        'modalidad' => Afiliado::FORMA_CONTADO,
    ];

    cobrarEnCaja($this, $lote, [$afiliacion(1000)])
        ->assertSessionHasErrors(['detalles.0.monto' => 'La afiliación al contado cuesta Bs 3.500,00. Si paga una parte, elija «a plazo».']);

    cobrarEnCaja($this, $lote, [$afiliacion(3500)])->assertSessionHasNoErrors();

    $recibo = Pago::query()->sole()->numero_recibo;

    cobrarEnCaja($this, $lote, [$afiliacion(3500)])
        ->assertSessionHasErrors(['detalles.0.concepto' => "Este lote ya tiene esa afiliación registrada en el recibo {$recibo}."]);
});

it('un cobro que choca con otro cobro se cancela completo', function (): void {
    $lote = loteModuloPagos($this, '7009', '2026-09-01', [$this->agua->id]);
    $multa = Multa::query()->create([
        'afiliado_id' => $lote->afiliado_id,
        'lote_afiliado_id' => $lote->id,
        'fecha_generacion' => now()->toDateString(),
        'motivo' => Multa::MOTIVO_INDIVIDUAL,
        'descripcion' => 'Multa individual',
        'monto' => 20,
        'saldo' => 20,
        'estado' => Multa::ESTADO_PENDIENTE,
    ]);

    // Otra caja cobró la multa entre que se abrió el cobro y se confirmó.
    $multa->update(['estado' => Multa::ESTADO_PAGADA, 'saldo' => 0]);

    expect(fn () => app(CobroService::class)->registrar($lote->afiliado->conLote($lote), $lote, [
        'fecha_pago' => now()->toDateString(),
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [
            lineaDeMeses($this->agua, 2, 16),
            ['concepto' => PagoDetalle::CONCEPTO_MULTA, 'descripcion' => 'Multa individual', 'monto' => 20, 'multa_id' => $multa->id],
        ],
    ], $this->cajera))->toThrow(ValidationException::class, 'ya fue cobrada o anulada');

    expect(Pago::query()->count())->toBe(0)
        ->and(PagoDetalle::query()->count())->toBe(0)
        ->and((float) Cuota::query()->where('lote_afiliado_id', $lote->id)->sum('saldo'))->toBe(8.0);
});

it('la multa de mora se mantiene aunque pague los meses', function (): void {
    // may–jun–jul sin pagar → multa el 1 de agosto (Bs 50); en octubre ya está aplicada.
    $lote = loteModuloPagos($this, '7010', '2026-05-01', [$this->agua->id]);
    $mora = fn (): ?string => Multa::query()
        ->where('lote_afiliado_id', $lote->id)
        ->where('motivo', Multa::MOTIVO_MORA_TRIMESTRAL)
        ->value('estado');

    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 5, 40)])->assertSessionHasNoErrors();

    expect((float) Cuota::query()->where('lote_afiliado_id', $lote->id)->sum('saldo'))->toBe(0.0)
        ->and($mora())->toBe(Multa::ESTADO_PENDIENTE)
        ->and(Multa::query()->where('lote_afiliado_id', $lote->id)->count())->toBe(1);
});

it('corregir una fecha de afiliación mal cargada retira la mora que solo existía por esos meses', function (): void {
    $lote = loteModuloPagos($this, '7019', '2026-03-01', [$this->agua->id]);
    app(CalculadorMultasService::class)->evaluarLote($lote->fresh()); // may–jul → multa el 1 de agosto

    expect(Multa::query()->where('lote_afiliado_id', $lote->id)->value('estado'))->toBe(Multa::ESTADO_PENDIENTE);

    $this->actingAs($this->cajera)
        ->put(route('afiliados.update', $lote->id), [
            'nombres' => $lote->afiliado->nombres,
            'apellidos' => $lote->afiliado->apellidos,
            'ci' => $lote->afiliado->ci,
            'otb_id' => $this->otb->id,
            'numero_lote' => (string) $lote->numero_lote,
            'fecha_afiliacion' => '2026-08-01',
            'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
            'estado' => Afiliado::ESTADO_ACTIVO,
            'categoria_id' => $this->domiciliar->id,
            'servicios' => [$this->agua->id],
            'monto_afiliacion_agua' => 3500,
            'monto_afiliacion_alcantarillado' => 0,
        ])
        ->assertSessionHasNoErrors();

    // La multa de agosto contaba may–jul, que ya no existen al afiliarse en agosto.
    expect(Cuota::query()->where('lote_afiliado_id', $lote->id)->orderBy('periodo')->pluck('periodo')->all())->toBe(['2026-08', '2026-09'])
        ->and(Multa::query()->where('lote_afiliado_id', $lote->id)->pluck('estado')->unique()->all())->toBe([Multa::ESTADO_ANULADA]);
});

it('el estado de cuenta muestra mes por mes lo pagado, lo vencido, el mes en curso y lo que viene', function (): void {
    $lote = loteModuloPagos($this, '7014', '2026-05-01', [$this->agua->id]);

    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 3, 24)])->assertSessionHasNoErrors();
    $pago = Pago::query()->sole();

    $this->actingAs($this->cajera)
        ->get(route('pagos.estado-cuenta', $lote->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos/EstadoCuenta')
            ->where('lote.id', $lote->id)
            ->where('lote.numero_lote', '7014')
            ->where('estado.gestion', 2026)
            ->where('estado.gestiones', [2026, 2027])
            ->where('estado.servicios.0.meses.0.estado', 'sin_servicio')
            ->where('estado.servicios.0.meses.4.estado', 'pagado')
            ->where('estado.servicios.0.meses.4.detalle', $pago->numero_recibo)
            ->where('estado.servicios.0.meses.6.estado', 'pagado')
            ->where('estado.servicios.0.meses.7.estado', 'vencido')
            ->where('estado.servicios.0.meses.8.estado', 'vencido')
            ->where('estado.servicios.0.meses.9.estado', 'pendiente')
            ->where('estado.servicios.0.meses.10.estado', 'por_venir')
            ->where('estado.resumen.cuotas_vencidas', 16)
            ->where('estado.resumen.meses_vencidos', 2)
            ->has('estado.recibos', 1)
            ->where('puedeCobrar', true));
});

it('el estado de cuenta marca los meses adelantados y permite ver la gestión siguiente', function (): void {
    $lote = loteModuloPagos($this, '7015', '2026-10-01', [$this->agua->id]);
    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 5, 40)])->assertSessionHasNoErrors();

    $this->actingAs($this->cajera)
        ->get(route('pagos.estado-cuenta', ['id' => $lote->id, 'gestion' => 2027]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('estado.gestion', 2027)
            ->where('estado.servicios.0.meses.0.estado', 'adelantado')
            ->where('estado.servicios.0.meses.1.estado', 'adelantado')
            ->where('estado.servicios.0.meses.2.estado', 'por_venir')
            ->where('estado.servicios.0.meses.11.detalle', 'Todavía no se puede adelantar'));
});

it('una cuenta de consulta solo ve el estado de cuenta de sus propios lotes', function (): void {
    $propio = loteModuloPagos($this, '7016', '2026-05-01', [$this->agua->id]);
    $ajeno = loteModuloPagos($this, '7017', '2026-05-01', [$this->agua->id]);

    $usuario = User::factory()->create([
        'role' => Role::CONSULTA_PAGOS_MULTAS,
        'afiliado_id' => $propio->afiliado_id,
        'ci' => $propio->afiliado->ci,
    ]);
    $usuario->assignRole(Role::CONSULTA_PAGOS_MULTAS);

    $this->actingAs($usuario)
        ->get(route('pagos.estado-cuenta', $propio->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('puedeCobrar', false));

    $this->actingAs($usuario)->get(route('pagos.estado-cuenta', $ajeno->id))->assertForbidden();
});

it('acepta el cobro tal como lo arma la pantalla y emite un recibo por propósito', function (): void {
    // Desde mayo: may–jun–jul generan la mora trimestral el 1 de agosto.
    $lote = loteModuloPagos($this, '7020', '2026-05-01', [$this->agua->id, $this->alc->id], [
        'forma_pago_afiliacion' => Afiliado::FORMA_PLAZO,
        'monto_afiliacion_agua' => 3800,
        'saldo_afiliacion_agua' => 2800,
        'saldo_afiliacion' => 2800,
    ]);

    $props = $this->actingAs($this->cajera)
        ->get(route('pagos.create', ['afiliado_id' => $lote->id]))
        ->assertOk()
        ->viewData('page')['props']['afiliadoSeleccionado'];

    [$agua, $alcantarillado] = $props['servicios_cobro'];
    $mora = collect($props['multas_pendientes'])->firstWhere('motivo', Multa::MOTIVO_MORA_TRIMESTRAL);
    $afiliacion = $props['afiliaciones_cobro'][0];
    $linea = fn (array $servicio, int $meses): array => [
        'concepto' => $servicio['concepto'],
        'descripcion' => $servicio['descripcion'],
        'servicio_id' => $servicio['servicio_id'],
        'meses' => $meses,
        'monto' => array_sum(array_column(array_slice($servicio['meses'], 0, $meses), 'monto')),
    ];

    expect($afiliacion)->toMatchArray(['concepto' => 'afiliacion_'.TipoAfiliacion::CODIGO_AGUA, 'modalidad' => Afiliado::FORMA_PLAZO, 'saldo' => 2800, 'editable' => true]);

    cobrarEnCaja($this, $lote, [
        $linea($agua, 4),
        $linea($alcantarillado, 2),
        ['concepto' => $mora['concepto'], 'descripcion' => $mora['descripcion_cobro'], 'monto' => $mora['monto'], 'multa_id' => $mora['id']],
        ['concepto' => $afiliacion['concepto'], 'descripcion' => $afiliacion['descripcion'], 'monto' => 500, 'modalidad' => $afiliacion['modalidad']],
        ['concepto' => PagoDetalle::CONCEPTO_APORTE, 'descripcion' => 'Certificado de no adeudo', 'monto' => 10],
    ])->assertSessionHasNoErrors();

    $pagos = Pago::query()->orderBy('id')->get();

    // Agua/alc: un recibo por gestión (año). Multas/afiliación/aportes aparte.
    expect($pagos->pluck('proposito')->all())->toBe([
        $agua['recibo']['key'].':2026',
        $alcantarillado['recibo']['key'].':2026',
        $mora['recibo']['key'],
        $afiliacion['recibo']['key'],
        'aporte:certificado de no adeudo',
    ])
        ->and($pagos->pluck('cobro_id')->unique())->toHaveCount(1)
        ->and((float) $pagos->sum('monto_total'))->toBe(32.0 + 30.0 + 50.0 + 500.0 + 10.0)
        ->and((float) $lote->fresh()->saldo_afiliacion_agua)->toBe(2300.0)
        ->and(Multa::query()->find($mora['id'])->estado)->toBe(Multa::ESTADO_PAGADA)
        ->and(Cuota::query()->where('lote_afiliado_id', $lote->id)->where('servicio_id', $this->alc->id)->where('saldo', '>', 0)->pluck('periodo')->all())->toBe(['2026-07', '2026-08', '2026-09']);

    $this->actingAs($this->cajera)
        ->get(route('pagos.show', $pagos[0]->id))
        ->assertInertia(fn ($page) => $page
            ->where('pago.detalles.0.unitario', 8)
            ->where('pago.detalles.0.rango', 'mayo a agosto 2026 (4 meses)')
            ->where('pago.lote_afiliado_id', $lote->id)
            ->has('pago.recibos_del_cobro', 5));
});

it('imprime juntos los recibos de un cobro, solo para quien puede verlos', function (): void {
    $lote = loteModuloPagos($this, '7021', '2026-09-01', [$this->agua->id, $this->alc->id]);
    cobrarEnCaja($this, $lote, [lineaDeMeses($this->agua, 1, 8), lineaDeMeses($this->alc, 1, 15)])->assertSessionHasNoErrors();
    $cobro = Pago::query()->value('cobro_id');

    $this->actingAs($this->cajera)
        ->get(route('pagos.cobro.imprimir', $cobro))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos/ImprimirCobro')
            ->has('recibos', 2)
            ->where('recibos.0.tipo_recibo', Pago::TIPO_INGRESO));

    $ajeno = loteModuloPagos($this, '7022', '2026-09-01', [$this->agua->id]);
    $consulta = User::factory()->create(['role' => Role::CONSULTA_PAGOS_MULTAS, 'afiliado_id' => $ajeno->afiliado_id, 'ci' => $ajeno->afiliado->ci]);
    $consulta->assignRole(Role::CONSULTA_PAGOS_MULTAS);

    $this->actingAs($consulta)->get(route('pagos.cobro.imprimir', $cobro))->assertForbidden();
    $this->actingAs($this->cajera)->get(route('pagos.cobro.imprimir', '0a2b6f3e-8d61-4c2a-9a52-1f7e0c3b9d44'))->assertNotFound();
});

it('no deja dos líneas libres iguales en el mismo cobro', function (): void {
    $lote = loteModuloPagos($this, '7023', '2026-10-01', [$this->agua->id]);
    $otro = ['concepto' => PagoDetalle::CONCEPTO_OTRO, 'descripcion' => 'Certificado', 'monto' => 10];

    cobrarEnCaja($this, $lote, [$otro, [...$otro, 'descripcion' => ' certificado ']])
        ->assertSessionHasErrors(['detalles.1.descripcion' => 'Esa línea está repetida en este cobro. Si son dos cobros distintos, cambie la descripción.']);

    cobrarEnCaja($this, $lote, [$otro, [...$otro, 'descripcion' => 'Certificado de domicilio']])->assertSessionHasNoErrors();

    expect(Pago::query()->count())->toBe(2);
});

it('suma el saldo de la multa parcialmente pagada, no el monto original', function (): void {
    $lote = loteModuloPagos($this, '7024', '2026-10-01', [$this->agua->id]);
    Multa::query()->create([
        'afiliado_id' => $lote->afiliado_id,
        'lote_afiliado_id' => $lote->id,
        'fecha_generacion' => now()->toDateString(),
        'motivo' => Multa::MOTIVO_INDIVIDUAL,
        'descripcion' => 'Multa parcial',
        'monto' => 50,
        'saldo' => 20,
        'estado' => Multa::ESTADO_PENDIENTE,
    ]);

    $resumen = app(\App\Services\DeudaAfiliadoService::class)
        ->calcularDeudaResumen($lote->afiliado->fresh()->conLote($lote));

    expect($resumen['total_multas_pendientes'])->toBe(20.0);
});

it('pagar un lote cortado no reactiva otro lote del mismo afiliado que sigue en mora', function (): void {
    // 2025-09-01 → oct/2026: ≥12 meses vencidos → corte por mora anual.
    $loteA = loteModuloPagos($this, '7025', '2025-09-01', [$this->agua->id]);
    $afiliado = $loteA->afiliado;
    $loteB = $afiliado->lotes()->create([
        'numero_lote' => '7026',
        'otb_id' => $this->otb->id,
        'direccion' => 'Calle 2',
        'fecha_afiliacion' => '2025-09-01',
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 0,
        'saldo_afiliacion' => 0,
        'estado' => Afiliado::ESTADO_ACTIVO,
        'categoria_id' => $this->domiciliar->id,
    ]);
    $loteB->sincronizarServicios([$this->agua->id], '2025-09-01');

    $calc = app(CalculadorMultasService::class);
    $calc->evaluarYGenerar($afiliado->fresh());
    $calc->actualizarEstadoPorMora($afiliado->fresh());

    expect($loteA->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO)
        ->and($loteB->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO);

    $pendienteA = (float) Cuota::query()->where('lote_afiliado_id', $loteA->id)->where('saldo', '>', 0)->sum('saldo');
    $mesesA = (int) Cuota::query()->where('lote_afiliado_id', $loteA->id)->where('saldo', '>', 0)->count();
    $multasA = Multa::query()->where('lote_afiliado_id', $loteA->id)->where('estado', Multa::ESTADO_PENDIENTE)->get();

    $lineas = [lineaDeMeses($this->agua, $mesesA, $pendienteA)];
    foreach ($multasA as $multa) {
        $lineas[] = [
            'concepto' => PagoDetalle::CONCEPTO_MULTA,
            'monto' => (float) ($multa->saldo ?? $multa->monto),
            'multa_id' => $multa->id,
        ];
    }

    cobrarEnCaja($this, $loteA, $lineas)->assertSessionHasNoErrors();

    expect($loteA->fresh()->estado)->toBe(Afiliado::ESTADO_ACTIVO)
        ->and($loteB->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO)
        ->and($afiliado->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO);
});

it('el reporte de deudas incluye lotes cortados por mora', function (): void {
    $lote = loteModuloPagos($this, '7027', '2025-09-01', [$this->agua->id]);
    $calc = app(CalculadorMultasService::class);
    $calc->evaluarLote($lote->fresh());
    $calc->actualizarEstadoPorMora($lote->afiliado->fresh()->conLote($lote->fresh()));

    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO);

    $this->actingAs($this->cajera)
        ->get(route('reportes.deudas'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Deudas')
            ->where(
                'reporte.grupos',
                fn ($grupos) => collect($grupos)
                    ->flatMap(fn ($grupo) => $grupo['lineas'] ?? [])
                    ->contains('lote_id', $lote->id),
            ));
});

it('el reporte de ingresos sigue separando domiciliar y comercial tras cobrar con categoría en el recibo', function (): void {
    $comercial = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->firstOrFail();
    $loteDom = loteModuloPagos($this, '7028', '2026-09-01', [$this->agua->id, $this->alc->id]);
    $loteCom = loteModuloPagos($this, '7029', '2026-09-01', [$this->agua->id, $this->alc->id], [
        'categoria_id' => $comercial->id,
    ]);

    // Un mes vencido por lote: entra al reporte (tiene cuota pagada).
    cobrarEnCaja($this, $loteDom, [
        lineaDeMeses($this->agua, 1, 8),
        lineaDeMeses($this->alc, 1, 15),
    ])->assertSessionHasNoErrors();

    cobrarEnCaja($this, $loteCom, [
        lineaDeMeses($this->agua, 1, 17),
        lineaDeMeses($this->alc, 1, 20),
    ])->assertSessionHasNoErrors();

    $descripciones = PagoDetalle::query()
        ->whereIn('concepto', PagoDetalle::conceptosServicioMensual())
        ->pluck('descripcion');

    expect($descripciones)->not->toBeEmpty()
        ->and($descripciones->every(fn (string $texto): bool => (bool) preg_match('/domiciliar|comercial/i', $texto)))->toBeTrue();

    $fecha = now()->toDateString();

    $this->actingAs($this->cajera)
        ->get(route('reportes.ingresos', ['desde' => $fecha, 'hasta' => $fecha]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Ingresos')
            ->where('reporte.grupos', function ($grupos) {
                $porLabel = collect($grupos)->keyBy('label');

                return $porLabel->keys()->all() === [
                    'Servicio de agua domiciliar',
                    'Servicio de agua comercial',
                    'Servicio de alcantarillado domiciliar',
                    'Servicio de alcantarillado comercial',
                ]
                    && (float) $porLabel['Servicio de agua domiciliar']['subtotal'] === 8.0
                    && (float) $porLabel['Servicio de agua comercial']['subtotal'] === 17.0
                    && (float) $porLabel['Servicio de alcantarillado domiciliar']['subtotal'] === 15.0
                    && (float) $porLabel['Servicio de alcantarillado comercial']['subtotal'] === 20.0;
            })
            ->where('reporte.total_general', fn ($total): bool => (float) $total === 60.0));
});
