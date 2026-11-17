<?php

use App\Models\Afiliado;
use App\Models\Cuota;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\User;
use App\Services\CalculadorMultasService;
use App\Services\CuotaMensualService;
use Carbon\Carbon;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $this->seed(ConfiguracionSeeder::class);
});

function adminPagos(): User
{
    $user = User::factory()->create();
    $user->forceFill(['role' => Role::ADMIN, 'is_active' => true])->save();
    $role = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

function afiliadoConServicio(int $mesesAtras = 4, float $tarifa = 8): Afiliado
{
    $servicio = Servicio::factory()->create([
        'tipo' => 'agua',
        'tarifa_mensual' => $tarifa,
        'activo' => true,
    ]);

    $afiliado = Afiliado::factory()->conLote([
        'fecha_afiliacion' => now()->subMonths($mesesAtras)->startOfMonth()->toDateString(),
        'saldo_afiliacion' => 0,
        'estado' => Afiliado::ESTADO_ACTIVO,
    ])->create([
        'estado' => Afiliado::ESTADO_ACTIVO,
    ]);

    $afiliado = $afiliado->fresh()->conLote($afiliado->lotes()->first());
    $lote = $afiliado->loteEnContexto();

    $lote?->servicios()->attach($servicio->id, [
        'tarifa_acordada' => $tarifa,
        'fecha_inicio' => $afiliado->fecha_afiliacion,
    ]);

    return $afiliado->fresh()->conLote($afiliado->lotes()->first());
}

it('genera multa de 50 Bs al acumular 3 meses de mora sin cortar el servicio', function (): void {
    // Trimestre may–jun–jul → multa el 1 de agosto.
    $this->travelTo(Carbon::parse('2026-08-05 10:00:00'));
    $admin = adminPagos();
    $afiliado = afiliadoConServicio(4);

    $creadas = app(CalculadorMultasService::class)->evaluarYGenerar($afiliado, $admin);
    app(CalculadorMultasService::class)->actualizarEstadoPorMora($afiliado->fresh());

    expect($creadas)->toBe(1)
        ->and(Multa::query()->where('afiliado_id', $afiliado->id)->first())
        ->motivo->toBe(Multa::MOTIVO_MORA_TRIMESTRAL)
        ->monto->toEqual(50)
        ->and($afiliado->fresh()->estado)->toBe(Afiliado::ESTADO_ACTIVO);
});

it('con 12 meses vencidos la multa trimestral suma 150 Bs y el servicio se corta', function (): void {
    // Tras sep–oct–nov el recibo del año llega a Bs 150 el 1 de diciembre.
    $this->travelTo(Carbon::parse('2026-12-05 10:00:00'));
    $admin = adminPagos();
    $afiliado = afiliadoConServicio(13);

    $creadas = app(CalculadorMultasService::class)->evaluarYGenerar($afiliado, $admin);
    app(CalculadorMultasService::class)->actualizarEstadoPorMora($afiliado->fresh());

    $multas = Multa::query()->where('afiliado_id', $afiliado->id)->orderBy('periodo')->get();

    expect($creadas)->toBeGreaterThanOrEqual(1)
        ->and($multas->where('estado', '!=', Multa::ESTADO_ANULADA))->toHaveCount(1)
        ->and($multas->where('estado', '!=', Multa::ESTADO_ANULADA)->first()->motivo)->toBe(Multa::MOTIVO_MORA_TRIMESTRAL)
        ->and((float) $multas->where('estado', '!=', Multa::ESTADO_ANULADA)->sum('monto'))->toBe(150.0)
        ->and($afiliado->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO);
});

it('al pagar cuotas y multas de un lote cortado por mora anual vuelve a activo', function (): void {
    $admin = adminPagos();
    $afiliado = afiliadoConServicio(13);
    $lote = $afiliado->loteEnContexto();

    app(CalculadorMultasService::class)->evaluarYGenerar($afiliado, $admin);
    app(CalculadorMultasService::class)->actualizarEstadoPorMora($afiliado->fresh());
    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_CORTADO);

    // Simula estar al día en servicio: sin cuotas ni multas pendientes.
    Cuota::query()->where('lote_afiliado_id', $lote->id)->where('saldo', '>', 0)
        ->update(['saldo' => 0, 'estado' => Cuota::ESTADO_PAGADA]);
    Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', Multa::ESTADO_PENDIENTE)
        ->update(['saldo' => 0, 'estado' => Multa::ESTADO_PAGADA]);

    app(CalculadorMultasService::class)->actualizarEstadoPorMora($afiliado->fresh()->conLote($lote->fresh()));

    expect($lote->fresh()->estado)->toBe(Afiliado::ESTADO_ACTIVO)
        ->and($afiliado->fresh()->estado)->toBe(Afiliado::ESTADO_ACTIVO);
});

it('revisar el lote otra vez no repite la multa trimestral', function (): void {
    $this->travelTo(Carbon::parse('2026-08-05 10:00:00'));
    $admin = adminPagos();
    $afiliado = afiliadoConServicio(4);

    $primera = app(CalculadorMultasService::class)->evaluarYGenerar($afiliado, $admin);
    $segunda = app(CalculadorMultasService::class)->evaluarYGenerar($afiliado->fresh(), $admin);

    expect($primera)->toBe(1)
        ->and($segunda)->toBe(0)
        ->and(Multa::query()->where('afiliado_id', $afiliado->id)->where('motivo', Multa::MOTIVO_MORA_TRIMESTRAL)->count())->toBe(1);
});

it('registra un pago atómico con recibo y marca la multa como pagada', function (): void {
    $admin = adminPagos();
    $afiliado = afiliadoConServicio(4);
    $servicio = $afiliado->loteEnContexto()?->serviciosActivos()->first();

    $lote = $afiliado->loteEnContexto();
    app(CuotaMensualService::class)->emitirLote($lote);

    $multa = Multa::create([
        'afiliado_id' => $afiliado->id,
        'lote_afiliado_id' => $lote?->id,
        'fecha_generacion' => now()->toDateString(),
        'motivo' => Multa::MOTIVO_MORA_TRIMESTRAL,
        'gestion' => (int) now()->year,
        'descripcion' => 'Mora de prueba',
        'monto' => 50,
        'saldo' => 50,
        'estado' => Multa::ESTADO_PENDIENTE,
    ]);

    $this->actingAs($admin)->post(route('pagos.store'), [
        'afiliado_id' => $afiliado->id,
        'fecha_pago' => now()->toDateString(),
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'observaciones' => 'Cobro ventanilla',
        'detalles' => [
            [
                'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
                'descripcion' => 'Cuota agua',
                'monto' => 8,
                'servicio_id' => $servicio->id,
                'multa_id' => null,
                'periodo' => now()->format('Y-m'),
            ],
            [
                'concepto' => PagoDetalle::CONCEPTO_MULTA,
                'descripcion' => 'Multa mora',
                'monto' => 50,
                'servicio_id' => null,
                'multa_id' => $multa->id,
                'periodo' => null,
            ],
        ],
    ])->assertRedirect();

    expect(Pago::query()->count())->toBe(2);

    $pagoAgua = Pago::query()->where('tipo_recibo', Pago::TIPO_INGRESO)->firstOrFail();
    $pagoMulta = Pago::query()->where('tipo_recibo', Pago::TIPO_CAJA)->firstOrFail();

    expect((float) $pagoAgua->monto_total)->toBe(8.0)
        ->and($pagoAgua->detalles)->toHaveCount(1)
        ->and((float) $pagoMulta->monto_total)->toBe(50.0)
        ->and($pagoMulta->detalles)->toHaveCount(1)
        ->and($pagoAgua->cobrado_por)->toBe($admin->id)
        ->and($pagoMulta->proposito_label)->not->toBeEmpty()
        ->and($multa->fresh()->estado)->toBe(Multa::ESTADO_PAGADA)
        ->and((float) $multa->fresh()->saldo)->toBe(0.0);

    $this->actingAs($admin)
        ->get(route('multas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'multas.data',
            fn ($filas): bool => collect($filas)->pluck('id')->doesntContain($multa->id),
        ));
});

it('filtra el listado de pagos por recibo, CI y nombre completo', function (): void {
    $admin = adminPagos();

    $buscado = Afiliado::factory()->create([
        'nombres' => 'Ximena',
        'apellidos' => 'Choquehuanca Mamani',
        'ci' => '88776655',
    ]);
    $otro = Afiliado::factory()->create([
        'nombres' => 'Roberto',
        'apellidos' => 'Quispe Flores',
        'ci' => '11223344',
    ]);

    $pagoBuscado = Pago::create([
        'numero_recibo' => 'REC-2026-009001',
        'afiliado_id' => $buscado->id,
        'lote_afiliado_id' => $buscado->lotes()->value('id'),
        'fecha_pago' => now()->toDateString(),
        'monto_total' => 8,
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'estado' => Pago::ESTADO_EMITIDO,
        'cobrado_por' => $admin->id,
    ]);
    Pago::create([
        'numero_recibo' => 'REC-2026-009002',
        'afiliado_id' => $otro->id,
        'lote_afiliado_id' => $otro->lotes()->value('id'),
        'fecha_pago' => now()->toDateString(),
        'monto_total' => 8,
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'estado' => Pago::ESTADO_EMITIDO,
        'cobrado_por' => $admin->id,
    ]);

    $assertUnPago = function (string $q) use ($admin, $pagoBuscado): void {
        $this->actingAs($admin)
            ->get(route('pagos.index', ['q' => $q]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pagos/Index')
                ->has('pagos.data', 1)
                ->where('pagos.data.0.id', $pagoBuscado->id)
                ->where('filtros.q', $q));
    };

    $assertUnPago('88776655');
    $assertUnPago('88776655 LP');
    $assertUnPago('Ximena');
    $assertUnPago('Choquehuanca');
    $assertUnPago('Ximena Choquehuanca');
    $assertUnPago('REC-2026-009001');
    $assertUnPago('009001');
});

it('filtra pagos por fecha y concepto en el listado de gestión', function (): void {
    $admin = adminPagos();
    $afiliado = afiliadoConServicio(1);

    $pago = Pago::create([
        'numero_recibo' => Pago::generarNumeroRecibo(),
        'afiliado_id' => $afiliado->id,
        'fecha_pago' => now()->toDateString(),
        'monto_total' => 8,
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'estado' => Pago::ESTADO_EMITIDO,
        'cobrado_por' => $admin->id,
    ]);
    $pago->detalles()->create([
        'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
        'descripcion' => 'Cuota',
        'monto' => 8,
        'periodo' => now()->format('Y-m'),
    ]);

    $this->actingAs($admin)
        ->get(route('pagos.index', [
            'fecha_desde' => now()->toDateString(),
            'fecha_hasta' => now()->toDateString(),
            'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos/Index')
            ->has('pagos.data', 1)
            ->where('filtros.concepto', PagoDetalle::CONCEPTO_SERVICIO_MENSUAL));
});

it('rechaza cuotas duplicadas del mismo mes en un cobro', function (): void {
    $admin = adminPagos();
    $afiliado = afiliadoConServicio(1);
    $servicio = $afiliado->loteEnContexto()?->serviciosActivos()->first();
    $periodo = now()->format('Y-m');

    $this->actingAs($admin)->post(route('pagos.store'), [
        'afiliado_id' => $afiliado->loteEnContexto()?->id ?? $afiliado->id,
        'fecha_pago' => now()->toDateString(),
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [
            [
                'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
                'descripcion' => 'Cuota agua',
                'monto' => 8,
                'servicio_id' => $servicio?->id,
                'periodo' => $periodo,
            ],
            [
                'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
                'descripcion' => 'Cuota agua',
                'monto' => 8,
                'servicio_id' => $servicio?->id,
                'periodo' => $periodo,
            ],
        ],
    ])->assertSessionHasErrors('detalles.1.periodo');
});

it('rechaza cobro de un mes que ya tiene recibo emitido', function (): void {
    $admin = adminPagos();
    $afiliado = afiliadoConServicio(1);
    $loteId = $afiliado->loteEnContexto()?->id;
    $servicio = $afiliado->loteEnContexto()?->serviciosActivos()->first();
    $periodo = now()->format('Y-m');

    $pago = Pago::create([
        'numero_recibo' => 'REC-2026-008901',
        'afiliado_id' => $afiliado->id,
        'lote_afiliado_id' => $loteId,
        'fecha_pago' => now()->toDateString(),
        'monto_total' => 8,
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'estado' => Pago::ESTADO_EMITIDO,
        'cobrado_por' => $admin->id,
    ]);
    $pago->detalles()->create([
        'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
        'descripcion' => 'Cuota agua',
        'monto' => 8,
        'servicio_id' => $servicio?->id,
        'periodo' => $periodo,
    ]);

    $this->actingAs($admin)->post(route('pagos.store'), [
        'afiliado_id' => $loteId ?? $afiliado->id,
        'fecha_pago' => now()->toDateString(),
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [
            [
                'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
                'descripcion' => 'Cuota agua',
                'monto' => 8,
                'servicio_id' => $servicio?->id,
                'periodo' => $periodo,
            ],
        ],
    ])->assertSessionHasErrors('detalles.0.periodo');
});

it('ofrece los conceptos de cuota en el filtro y omite cuota mensual', function (): void {
    $admin = adminPagos();
    $esperados = [
        'Consumo de agua',
        'Pago de alcantarillado',
        'Multas',
        'Aporte',
        'Otro',
    ];

    $this->actingAs($admin)
        ->get(route('pagos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos/Index')
            ->where('conceptos', function ($conceptos) use ($esperados): bool {
                $labels = collect($conceptos)->pluck('label');

                return $labels->intersect($esperados)->count() === count($esperados)
                    && ! $labels->contains('Falta de trabajos')
                    && ! $labels->contains('Falta de reuniones de agua potable')
                    && ! $labels->contains('Afiliación')
                    && ! $labels->contains('Cuota mensual')
                    && ! $labels->contains('Cuota');
            }));
});

it('registra consumo de agua y pago de alcantarillado como conceptos de cuota', function (): void {
    $admin = adminPagos();
    $afiliado = afiliadoConServicio(1);
    $alc = Servicio::factory()->create([
        'tipo' => 'alcantarillado',
        'codigo' => 'ALC',
        'tarifa_mensual' => 15,
        'activo' => true,
    ]);
    $afiliado->loteEnContexto()?->servicios()->attach($alc->id, [
        'tarifa_acordada' => 15,
        'fecha_inicio' => $afiliado->fecha_afiliacion,
    ]);
    $servicioAgua = $afiliado->loteEnContexto()?->serviciosActivos()->where('tipo', 'agua')->first();
    $periodo = now()->format('Y-m');

    $this->actingAs($admin)->post(route('pagos.store'), [
        'afiliado_id' => $afiliado->loteEnContexto()?->id ?? $afiliado->id,
        'fecha_pago' => now()->toDateString(),
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [
            [
                'concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA,
                'descripcion' => 'Consumo de agua',
                'monto' => 8,
                'servicio_id' => $servicioAgua?->id,
                'periodo' => $periodo,
            ],
            [
                'concepto' => PagoDetalle::CONCEPTO_PAGO_ALCANTARILLADO,
                'descripcion' => 'Pago de alcantarillado',
                'monto' => 15,
                'servicio_id' => $alc->id,
                'periodo' => $periodo,
            ],
        ],
    ])->assertRedirect();

    expect(PagoDetalle::query()->where('concepto', PagoDetalle::CONCEPTO_CONSUMO_AGUA)->exists())->toBeTrue()
        ->and(PagoDetalle::query()->where('concepto', PagoDetalle::CONCEPTO_PAGO_ALCANTARILLADO)->exists())->toBeTrue();
});

it('genera la mora del documento y rechaza cobrar mas que el saldo de la cuota', function (): void {
    \Carbon\Carbon::setTestNow('2026-04-16');
    $admin = adminPagos();
    $afiliado = afiliadoConServicio(3);
    $lote = $afiliado->loteEnContexto();

    $creadas = app(CalculadorMultasService::class)->evaluarLote($lote, $admin);

    expect($creadas)->toBe(1)
        ->and(Multa::query()->where('lote_afiliado_id', $lote->id)->first())
        ->motivo->toBe(Multa::MOTIVO_MORA_TRIMESTRAL)
        ->monto->toEqual(50)
        ->gestion->toBe(2026);

    \Carbon\Carbon::setTestNow('2026-05-16');
    expect(app(CalculadorMultasService::class)->evaluarLote($lote->fresh(), $admin))->toBe(0);

    // Enero 2027: el mismo recibo del año subió a Bs 150 (ago + dic).
    \Carbon\Carbon::setTestNow('2027-01-16');
    expect(app(CalculadorMultasService::class)->evaluarLote($lote->fresh(), $admin))->toBeGreaterThanOrEqual(1);

    $deuda = app(\App\Services\DeudaAfiliadoService::class)
        ->calcularDeudaResumen($afiliado->fresh()->conLote($lote->fresh()));

    expect($deuda['total_cuotas_pendientes'])->toBe(96.0)
        ->and($deuda['total_multas_pendientes'])->toBe(150.0)
        ->and($deuda['total_deuda'])->toBe(246.0)
        ->and(Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', '!=', Multa::ESTADO_ANULADA)->pluck('periodo')->all())
        ->toBe(['2026-12'])
        ->and((float) Multa::query()->where('lote_afiliado_id', $lote->id)->where('estado', '!=', Multa::ESTADO_ANULADA)->value('monto'))
        ->toBe(150.0);

    $cuota = \App\Models\Cuota::query()->where('lote_afiliado_id', $lote->id)->orderBy('periodo')->firstOrFail();

    $this->actingAs($admin)->post(route('pagos.store'), [
        'afiliado_id' => $lote->id,
        'fecha_pago' => '2027-01-16',
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [[
            'concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA,
            'descripcion' => 'Consumo de agua',
            'monto' => 200,
            'servicio_id' => $cuota->servicio_id,
            'cuota_id' => $cuota->id,
            'multa_id' => null,
            'periodo' => $cuota->periodo,
        ]],
    ])->assertSessionHasErrors('detalles.0.monto');

    \Carbon\Carbon::setTestNow();
});

it('rechaza montos inválidos vía StorePagoRequest', function (): void {
    $admin = adminPagos();
    $afiliado = afiliadoConServicio(1);

    $this->actingAs($admin)->post(route('pagos.store'), [
        'afiliado_id' => $afiliado->id,
        'fecha_pago' => now()->toDateString(),
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [
            [
                'concepto' => PagoDetalle::CONCEPTO_APORTE,
                'descripcion' => 'Aporte',
                'monto' => 0,
            ],
        ],
    ])->assertSessionHasErrors('detalles.0.monto');

    \Carbon\Carbon::setTestNow();
});

it('una multa individual no altera la multa trimestral', function (): void {
    $this->travelTo(Carbon::parse('2026-08-05 10:00:00'));
    $admin = adminPagos();
    $afiliado = afiliadoConServicio(4);
    $lote = $afiliado->loteEnContexto();

    $this->actingAs($admin)->post(route('multas.store'), [
        'afiliado_id' => $afiliado->id,
        'fecha_generacion' => now()->toDateString(),
        'descripcion' => 'Rotura de medidor',
        'monto' => 25,
    ])->assertRedirect(route('multas.index'));

    app(CalculadorMultasService::class)->evaluarYGenerar($afiliado->fresh(), $admin);

    $individual = Multa::query()
        ->where('afiliado_id', $afiliado->id)
        ->where('motivo', Multa::MOTIVO_INDIVIDUAL)
        ->first();

    expect($individual)->not->toBeNull()
        ->and($individual->estado)->toBe(Multa::ESTADO_PENDIENTE)
        ->and($individual->gestion)->toBeNull()
        ->and((float) $individual->monto)->toBe(25.0)
        ->and(Multa::query()->where('lote_afiliado_id', $lote?->id)->where('motivo', Multa::MOTIVO_MORA_TRIMESTRAL)->where('estado', Multa::ESTADO_PENDIENTE)->count())->toBe(1);
});
