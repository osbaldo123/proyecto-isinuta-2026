<?php

use App\Models\Afiliado;
use App\Models\Asistencia;
use App\Models\Categoria;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\Trabajo;
use App\Models\User;
use App\Models\Otb;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
});

function userWithRoleForDocs(string $roleName, bool $isSystemAccount = false): User
{
    $user = User::factory()->create([
        'role' => $roleName,
        'is_system_account' => $isSystemAccount,
        'is_active' => true,
    ]);
    $role = Role::query()->where('nombre', $roleName)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

function multaFaltaTrabajo(Afiliado $afiliado, string $fecha, float $monto = 20, string $tipo = 'limpieza'): void
{
    $lote = $afiliado->lotes()->firstOrFail();
    $trabajo = Trabajo::factory()->create([
        'otb_id' => $lote->otb_id,
        'tipo' => $tipo,
        'fecha_inicio' => $fecha,
        'monto_multa' => $monto,
    ]);

    Multa::query()->create([
        'afiliado_id' => $afiliado->id,
        'lote_afiliado_id' => $lote->id,
        'trabajo_id' => $trabajo->id,
        'fecha_generacion' => $fecha,
        'motivo' => Multa::MOTIVO_FALTA_TRABAJO,
        'descripcion' => 'Inasistencia a '.$tipo,
        'monto' => $monto,
        'saldo' => $monto,
        'estado' => Multa::ESTADO_PENDIENTE,
    ]);

    Asistencia::query()->updateOrCreate(
        [
            'trabajo_id' => $trabajo->id,
            'lote_afiliado_id' => $lote->id,
        ],
        [
            'afiliado_id' => $afiliado->id,
            'presente' => false,
        ],
    );
}

function cuotaMensualPagada(int $loteId, int $servicioId, string $periodo, float $monto): void
{
    Cuota::query()->updateOrCreate(
        [
            'lote_afiliado_id' => $loteId,
            'servicio_id' => $servicioId,
            'periodo' => $periodo,
        ],
        [
            'gestion' => (int) substr($periodo, 0, 4),
            'monto_original' => $monto,
            'saldo' => 0,
            'fecha_vencimiento' => $periodo.'-02',
            'estado' => Cuota::ESTADO_PAGADA,
        ],
    );
}

function crearPagoConDetalle(): array
{
    $afiliado = Afiliado::factory()->create();
    $pago = Pago::factory()->create([
        'afiliado_id' => $afiliado->id,
        'fecha_pago' => now()->toDateString(),
        'monto_total' => 96,
        'estado' => Pago::ESTADO_EMITIDO,
    ]);

    PagoDetalle::query()->create([
        'pago_id' => $pago->id,
        'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
        'descripcion' => 'Servicio agua potable',
        'monto' => 96,
        'periodo' => now()->format('Y-m'),
    ]);

    return [$afiliado->fresh(), $pago->fresh()];
}

test('secretaria can view ingresos report and export excel and word', function (): void {
    $secretaria = userWithRoleForDocs(Role::SECRETARIA, isSystemAccount: true);
    [, $pago] = crearPagoConDetalle();

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', [
            'desde' => $pago->fecha_pago->toDateString(),
            'hasta' => $pago->fecha_pago->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Ingresos')
            ->has('reporte.grupos')
            ->where('reporte.total_general', 0));

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos.export', [
            'formato' => 'xlsx',
            'desde' => $pago->fecha_pago->toDateString(),
            'hasta' => $pago->fecha_pago->toDateString(),
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('consulta user cannot view ingresos report', function (): void {
    $consulta = userWithRoleForDocs(Role::CONSULTA_PAGOS_MULTAS);

    $this->actingAs($consulta)
        ->get(route('reportes.ingresos'))
        ->assertForbidden();
});

test('secretaria can export recibo', function (): void {
    $secretaria = userWithRoleForDocs(Role::SECRETARIA, isSystemAccount: true);
    [, $pago] = crearPagoConDetalle();

    $this->actingAs($secretaria)
        ->get(route('pagos.export', ['id' => $pago->id, 'formato' => 'xlsx']))
        ->assertOk();
});

test('presidente can view ingresos report', function (): void {
    $presidente = userWithRoleForDocs(Role::PRESIDENTE);

    $this->actingAs($presidente)
        ->get(route('reportes.ingresos'))
        ->assertOk();
});

test('ingresos report groups multiple socios by legacy categories', function (): void {
    $secretaria = userWithRoleForDocs(Role::SECRETARIA, isSystemAccount: true);
    $agua = Servicio::query()->create([
        'codigo' => 'AGUA-T',
        'nombre' => 'Agua Potable',
        'tipo' => 'agua',
        'tarifa_mensual' => 96,
        'activo' => true,
    ]);
    $alc = Servicio::query()->create([
        'codigo' => 'ALC-T',
        'nombre' => 'Alcantarillado',
        'tipo' => 'alcantarillado',
        'tarifa_mensual' => 120,
        'activo' => true,
    ]);

    $a1 = Afiliado::factory()->create(['nombres' => 'Wilson', 'apellidos' => 'Felix']);
    $a2 = Afiliado::factory()->create(['nombres' => 'Victor', 'apellidos' => 'Garcia']);
    $fecha = now()->toDateString();

    foreach ([$a1, $a2] as $afiliado) {
        $lote = $afiliado->lotes()->firstOrFail();
        $periodo = now()->format('Y-m');
        cuotaMensualPagada($lote->id, $agua->id, $periodo, 8);
        cuotaMensualPagada($lote->id, $alc->id, $periodo, 15);
        $pago = Pago::factory()->create([
            'afiliado_id' => $afiliado->id,
            'lote_afiliado_id' => $lote->id,
            'fecha_pago' => $fecha,
            'monto_total' => 23,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);
        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
            'descripcion' => 'Servicio agua potable',
            'monto' => 8,
            'servicio_id' => $agua->id,
            'periodo' => $periodo,
        ]);
        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
            'descripcion' => 'Alcantarillado',
            'monto' => 15,
            'servicio_id' => $alc->id,
            'periodo' => $periodo,
        ]);
    }

    multaFaltaTrabajo($a1, $fecha, 20, 'limpieza');

    $pagoInventado = Pago::factory()->create([
        'afiliado_id' => $a1->id,
        'fecha_pago' => $fecha,
        'monto_total' => 50,
        'estado' => Pago::ESTADO_EMITIDO,
    ]);
    PagoDetalle::query()->create([
        'pago_id' => $pagoInventado->id,
        'concepto' => PagoDetalle::CONCEPTO_PAGO_TRABAJO_AGUA,
        'descripcion' => 'Falta de trabajos de agua potable 1 2026',
        'monto' => 50,
    ]);

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', ['desde' => $fecha, 'hasta' => $fecha]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Ingresos')
            ->where('reporte.cuentas', 3)
            ->where('reporte.total_general', 66)
            ->where('reporte.grupos', function ($grupos) {
                $labels = collect($grupos)->pluck('label')->values()->all();
                $falta = collect($grupos)->firstWhere('label', 'Falta de trabajos')['lineas'][0] ?? [];

                return $labels === [
                    'Servicio de agua domiciliar',
                    'Servicio de alcantarillado domiciliar',
                    'Falta de trabajos',
                ]
                    && str_contains((string) ($falta['detalle'] ?? ''), 'limpieza')
                    && (float) ($falta['bolivianos'] ?? 0) === 20.0
                    && count(collect($grupos)->firstWhere('label', 'Servicio de agua domiciliar')['lineas'] ?? []) === 2
                    && count(collect($grupos)->firstWhere('label', 'Servicio de alcantarillado domiciliar')['lineas'] ?? []) === 2
                    && filled(collect($grupos)->firstWhere('label', 'Servicio de agua domiciliar')['lineas'][0]['recibo'] ?? null)
                    && filled(collect($grupos)->firstWhere('label', 'Servicio de agua domiciliar')['lineas'][0]['periodo'] ?? null)
                    && collect($grupos)->firstWhere('label', 'Servicio de agua domiciliar')['lineas'][0]['periodo'] !== '—';
            }));
});

test('ingresos report separa faltas de agua y alcantarillado', function (): void {
    $secretaria = userWithRoleForDocs(Role::SECRETARIA, isSystemAccount: true);
    $afiliado = Afiliado::factory()->conLote()->create();
    $fecha = now()->toDateString();
    multaFaltaTrabajo($afiliado, $fecha, 20, 'limpieza');

    $presente = Afiliado::factory()->conLote()->create();
    $lotePresente = $presente->lotes()->firstOrFail();
    $trabajoPresente = Trabajo::factory()->create([
        'otb_id' => $lotePresente->otb_id,
        'tipo' => 'limpieza',
        'fecha_inicio' => $fecha,
        'monto_multa' => 20,
    ]);
    Multa::query()->create([
        'afiliado_id' => $presente->id,
        'lote_afiliado_id' => $lotePresente->id,
        'trabajo_id' => $trabajoPresente->id,
        'fecha_generacion' => $fecha,
        'motivo' => Multa::MOTIVO_FALTA_TRABAJO,
        'descripcion' => 'Inasistencia a limpieza',
        'monto' => 20,
        'saldo' => 20,
        'estado' => Multa::ESTADO_PENDIENTE,
    ]);
    Asistencia::query()->create([
        'trabajo_id' => $trabajoPresente->id,
        'afiliado_id' => $presente->id,
        'lote_afiliado_id' => $lotePresente->id,
        'presente' => true,
    ]);

    $lineas = [
        [PagoDetalle::CONCEPTO_PAGO_TRABAJO_AGUA, 'Falta de trabajos de agua potable 1 2026', 40],
        [PagoDetalle::CONCEPTO_PAGO_TRABAJO_ALCANTARILLADO, 'Falta de trabajos de alcantarillado 1 2026', 50],
        [PagoDetalle::CONCEPTO_REUNIONES_AGUA, 'Falta de reuniones de agua potable 1 2026', 60],
        [PagoDetalle::CONCEPTO_REUNIONES_ALCANTARILLADO, 'Falta de reuniones de alcantarillado 1 2026', 70],
        [PagoDetalle::CONCEPTO_OTRO, 'cambio de nombre de Elias a Abel', 100],
        [PagoDetalle::CONCEPTO_OTRO, 'cambio de titularidad del lote', 90],
        [PagoDetalle::CONCEPTO_APORTE, '08/2026 · aporte de cañeria', 200],
        [PagoDetalle::CONCEPTO_MULTA, 'falta de trabajos de alcantarillado 2 2021-2022', 80],
    ];

    foreach ($lineas as [$concepto, $descripcion, $monto]) {
        $pago = Pago::factory()->create([
            'afiliado_id' => $afiliado->id,
            'fecha_pago' => $fecha,
            'monto_total' => $monto,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);
        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => $concepto,
            'descripcion' => $descripcion,
            'monto' => $monto,
        ]);
    }

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', ['desde' => $fecha, 'hasta' => $fecha]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Ingresos')
            ->where('opciones.conceptos', function ($conceptos) {
                $labels = collect($conceptos)->pluck('label')->all();

                return in_array('Falta de trabajos', $labels, true)
                    && ! in_array('Consumo de agua', $labels, true)
                    && ! in_array('Alcantarillado', $labels, true)
                    && ! in_array('Cambio de nombre', $labels, true)
                    && ! in_array('Cambio de titularidad', $labels, true)
                    && ! in_array('Falta de reuniones de agua potable', $labels, true)
                    && ! in_array('Falta de reuniones de alcantarillado', $labels, true)
                    && ! in_array('Falta de trabajos de agua potable', $labels, true)
                    && ! in_array('Falta de trabajos de alcantarillado', $labels, true)
                    && ! in_array('Otros conceptos', $labels, true);
            })
            ->where('reporte.grupos', function ($grupos) {
                $labels = collect($grupos)->pluck('label')->values()->all();
                $falta = collect($grupos)->firstWhere('label', 'Falta de trabajos');

                return $labels === ['Falta de trabajos']
                    && ! in_array('Falta de trabajos de agua potable', $labels, true)
                    && ! in_array('Falta de trabajos de alcantarillado', $labels, true)
                    && ! in_array('Falta de reuniones de agua potable', $labels, true)
                    && ! in_array('Falta de reuniones de alcantarillado', $labels, true)
                    && ! in_array('Cambio de nombre', $labels, true)
                    && ! in_array('Cambio de titularidad', $labels, true)
                    && ! in_array('Aporte ordinario', $labels, true)
                    && ! in_array('Otros conceptos', $labels, true)
                    && ! in_array('Falta de reuniones', $labels, true)
                    && count($falta['lineas'] ?? []) === 1
                    && str_contains((string) ($falta['lineas'][0]['detalle'] ?? ''), 'limpieza')
                    && (float) ($falta['subtotal'] ?? 0) === 20.0;
            }));
});

test('falta de trabajos repite el nombre y separa cada lote con su categoria', function (): void {
    $secretaria = userWithRoleForDocs(Role::SECRETARIA, isSystemAccount: true);
    $domiciliar = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();
    $comercial = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->firstOrFail();
    $otb = Otb::factory()->create(['nombre' => 'BARRIO 21']);
    $afiliado = Afiliado::factory()->conLote([
        'numero_lote' => '555',
        'otb_id' => $otb->id,
        'categoria_id' => $domiciliar->id,
    ])->create([
        'nombres' => 'jose',
        'apellidos' => 'mendosa',
    ]);
    $loteDomiciliar = $afiliado->lotes()->where('numero_lote', '555')->firstOrFail();
    $loteComercial = LoteAfiliado::query()->create([
        'afiliado_id' => $afiliado->id,
        'numero_lote' => '556',
        'otb_id' => $otb->id,
        'categoria_id' => $comercial->id,
        'direccion' => 'Calle 2',
        'fecha_afiliacion' => now()->subMonths(3)->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 3500,
        'saldo_afiliacion' => 0,
        'estado' => Afiliado::ESTADO_ACTIVO,
    ]);
    $fecha = now()->toDateString();
    $trabajo = Trabajo::factory()->create([
        'otb_id' => $otb->id,
        'tipo' => 'limpieza',
        'fecha_inicio' => $fecha,
        'monto_multa' => 20,
    ]);

    foreach ([$loteDomiciliar, $loteComercial] as $lote) {
        Multa::query()->create([
            'afiliado_id' => $afiliado->id,
            'lote_afiliado_id' => $lote->id,
            'trabajo_id' => $trabajo->id,
            'fecha_generacion' => $fecha,
            'motivo' => Multa::MOTIVO_FALTA_TRABAJO,
            'descripcion' => 'Inasistencia a limpieza',
            'monto' => 20,
            'saldo' => 20,
            'estado' => Multa::ESTADO_PENDIENTE,
        ]);
    }

    foreach ([$loteDomiciliar, $loteComercial] as $lote) {
        Asistencia::query()->create([
            'trabajo_id' => $trabajo->id,
            'afiliado_id' => $afiliado->id,
            'lote_afiliado_id' => $lote->id,
            'presente' => false,
        ]);
    }

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', ['desde' => $fecha, 'hasta' => $fecha]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporte.grupos', function ($grupos) use ($domiciliar, $comercial) {
                $falta = collect($grupos)->firstWhere('label', 'Falta de trabajos');
                $lineas = collect($falta['lineas'] ?? []);

                return count($lineas) === 2
                    && (float) ($falta['subtotal'] ?? 0) === 40.0
                    && $lineas->every(fn ($linea) => str_contains((string) $linea['socio'], 'jose mendosa')
                        && (float) $linea['bolivianos'] === 20.0)
                    && $lineas->contains(fn ($linea) => str_contains((string) $linea['socio'], 'Lote 555')
                        && $linea['detalle'] === 'limpieza, '.$domiciliar->nombre)
                    && $lineas->contains(fn ($linea) => str_contains((string) $linea['socio'], 'Lote 556')
                        && $linea['detalle'] === 'limpieza, '.$comercial->nombre);
            }));
});

test('ingresos report muestra el nombre sin numero delante y distingue lotes del mismo afiliado', function (): void {
    $secretaria = userWithRoleForDocs(Role::SECRETARIA, isSystemAccount: true);
    $agua = Servicio::query()->create([
        'codigo' => 'AGUA-LOTES',
        'nombre' => 'Agua Potable',
        'tipo' => 'agua',
        'tarifa_mensual' => 8,
        'activo' => true,
    ]);

    $zonaCentro = Otb::factory()->create(['nombre' => 'Barrio Centro']);
    $zonaMunaypata = Otb::factory()->create(['nombre' => 'Barrio Munaypata']);

    $afiliado = Afiliado::factory()->conLote([
        'numero_lote' => '1',
        'otb_id' => $zonaCentro->id,
    ])->create([
        'nombres' => 'Javier',
        'apellidos' => 'Mercado Huerta',
    ]);

    $lote1 = $afiliado->lotes()->where('numero_lote', '1')->firstOrFail();
    $lote2 = LoteAfiliado::query()->create([
        'afiliado_id' => $afiliado->id,
        'numero_lote' => '78',
        'otb_id' => $zonaMunaypata->id,
        'categoria_id' => $lote1->categoria_id,
        'direccion' => 'Calle 2',
        'fecha_afiliacion' => now()->subMonths(3)->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'monto_afiliacion' => 3500,
        'saldo_afiliacion' => 0,
        'estado' => Afiliado::ESTADO_ACTIVO,
    ]);

    $fecha = now()->toDateString();

    foreach ([$lote1, $lote2] as $lote) {
        $pago = Pago::factory()->create([
            'afiliado_id' => $afiliado->id,
            'lote_afiliado_id' => $lote->id,
            'fecha_pago' => $fecha,
            'monto_total' => 8,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);
        $periodo = now()->format('Y-m');
        cuotaMensualPagada($lote->id, $agua->id, $periodo, 8);
        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
            'descripcion' => 'Servicio agua potable',
            'monto' => 8,
            'servicio_id' => $agua->id,
            'periodo' => $periodo,
        ]);
    }

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', ['desde' => $fecha, 'hasta' => $fecha]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Ingresos')
            ->where('reporte.grupos', function ($grupos) use ($lote1, $lote2) {
                $lineas = collect($grupos)->firstWhere('label', 'Servicio de agua domiciliar')['lineas'] ?? [];
                $socios = collect($lineas)->pluck('socio');
                $loteIds = collect($lineas)->pluck('lote_id')->sort()->values()->all();
                $mes = [
                    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
                    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
                    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
                ][(int) now()->format('n')];

                return count($lineas) === 2
                    && $socios->every(fn ($socio) => is_string($socio)
                        && str_contains($socio, 'Javier Mercado Huerta')
                        && ! preg_match('/^\d+[A-Z-]?\s/', $socio))
                    && $socios->contains(fn ($socio) => str_contains($socio, 'Barrio Centro'))
                    && $socios->contains(fn ($socio) => str_contains($socio, 'Barrio Munaypata'))
                    && $socios->contains(fn ($socio) => str_contains($socio, 'Lote 1'))
                    && $socios->contains(fn ($socio) => str_contains($socio, 'Lote 78'))
                    && collect($lineas)->every(function ($linea) use ($mes) {
                        return ($linea['detalle'] ?? '') === $mes
                            && str_contains((string) ($linea['fecha'] ?? ''), '2026')
                            && str_contains((string) ($linea['fecha'] ?? ''), ' - ')
                            && ($linea['periodo'] ?? '—') !== '—';
                    })
                    && $loteIds === [$lote1->id, $lote2->id];
            }));
});

test('ingresos report describe el rango de meses pagados de agua', function (): void {
    $secretaria = userWithRoleForDocs(Role::SECRETARIA, isSystemAccount: true);
    $agua = Servicio::query()->create([
        'codigo' => 'AGUA-RANGO',
        'nombre' => 'Agua Potable',
        'tipo' => 'agua',
        'tarifa_mensual' => 8,
        'activo' => true,
    ]);
    $afiliado = Afiliado::factory()->conLote()->create([
        'nombres' => 'Rosa',
        'apellidos' => 'Mendoza',
    ]);
    $lote = $afiliado->lotes()->firstOrFail();
    $fecha = now()->toDateString();

    foreach (['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'] as $periodo) {
        cuotaMensualPagada($lote->id, $agua->id, $periodo, 8);
        $pago = Pago::factory()->create([
            'afiliado_id' => $afiliado->id,
            'lote_afiliado_id' => $lote->id,
            'fecha_pago' => $fecha,
            'monto_total' => 8,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);
        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
            'descripcion' => 'Servicio agua potable',
            'monto' => 8,
            'servicio_id' => $agua->id,
            'periodo' => $periodo,
        ]);
    }

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', ['desde' => $fecha, 'hasta' => $fecha]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Ingresos')
            ->where('reporte.grupos', function ($grupos) use ($fecha) {
                $lineas = collect($grupos)->firstWhere('label', 'Servicio de agua domiciliar')['lineas'] ?? [];
                $dia = \Carbon\Carbon::parse($fecha)->format('d/m/Y');

                return count($lineas) === 1
                    && ($lineas[0]['bolivianos'] ?? 0) == 48.0
                    && ($lineas[0]['fecha'] ?? '') === $dia.' - '.$dia
                    && ($lineas[0]['detalle'] ?? '') === 'de enero a junio';
            }));
});

test('ingresos de agua y alcantarillado separan la categoria y el rango cobrado', function (): void {
    $secretaria = userWithRoleForDocs(Role::SECRETARIA, isSystemAccount: true);
    $domiciliar = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();
    $comercial = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->firstOrFail();
    $agua = Servicio::query()->create([
        'codigo' => 'AGUA-CAT',
        'nombre' => 'Agua Potable',
        'tipo' => 'agua',
        'tarifa_mensual' => 8,
        'activo' => true,
    ]);
    $alc = Servicio::query()->create([
        'codigo' => 'ALC-CAT',
        'nombre' => 'Alcantarillado',
        'tipo' => 'alcantarillado',
        'tarifa_mensual' => 15,
        'activo' => true,
    ]);
    $fecha = '2026-10-25';

    $loteDom = Afiliado::factory()->conLote(['categoria_id' => $domiciliar->id])->create()->lotes()->firstOrFail();
    $loteCom = Afiliado::factory()->conLote(['categoria_id' => $comercial->id])->create()->lotes()->firstOrFail();

    foreach (['2026-08', '2026-09', '2026-10'] as $periodo) {
        cuotaMensualPagada($loteDom->id, $agua->id, $periodo, 8);
        cuotaMensualPagada($loteDom->id, $alc->id, $periodo, 15);
        $pago = Pago::factory()->create([
            'afiliado_id' => $loteDom->afiliado_id,
            'lote_afiliado_id' => $loteDom->id,
            'fecha_pago' => $fecha,
            'monto_total' => 23,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);
        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA,
            'descripcion' => 'Agua domiciliar',
            'monto' => 8,
            'servicio_id' => $agua->id,
            'periodo' => $periodo,
        ]);
        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => PagoDetalle::CONCEPTO_PAGO_ALCANTARILLADO,
            'descripcion' => 'Alcantarillado domiciliar',
            'monto' => 15,
            'servicio_id' => $alc->id,
            'periodo' => $periodo,
        ]);
    }

    $pagoMal = Pago::factory()->create([
        'afiliado_id' => $loteDom->afiliado_id,
        'lote_afiliado_id' => $loteDom->id,
        'fecha_pago' => $fecha,
        'monto_total' => 10,
        'estado' => Pago::ESTADO_EMITIDO,
    ]);
    PagoDetalle::query()->create([
        'pago_id' => $pagoMal->id,
        'concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA,
        'descripcion' => 'Agua potable mal cargada',
        'monto' => 10,
        'servicio_id' => $agua->id,
        'periodo' => '2026-11',
    ]);

    foreach (['2026-09', '2026-10'] as $periodo) {
        cuotaMensualPagada($loteCom->id, $agua->id, $periodo, 17);
        cuotaMensualPagada($loteCom->id, $alc->id, $periodo, 20);
        $pago = Pago::factory()->create([
            'afiliado_id' => $loteCom->afiliado_id,
            'lote_afiliado_id' => $loteCom->id,
            'fecha_pago' => $fecha,
            'monto_total' => 37,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);
        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => PagoDetalle::CONCEPTO_CONSUMO_AGUA,
            'descripcion' => 'Agua comercial',
            'monto' => 17,
            'servicio_id' => $agua->id,
            'periodo' => $periodo,
        ]);
        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => PagoDetalle::CONCEPTO_PAGO_ALCANTARILLADO,
            'descripcion' => 'Alcantarillado comercial',
            'monto' => 20,
            'servicio_id' => $alc->id,
            'periodo' => $periodo,
        ]);
    }

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', ['desde' => $fecha, 'hasta' => $fecha]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporte.grupos', function ($grupos) {
                $labels = collect($grupos)->pluck('label')->values()->all();
                $porLabel = collect($grupos)->keyBy('label');
                $aguaDom = $porLabel->get('Servicio de agua domiciliar')['lineas'][0] ?? [];
                $aguaCom = $porLabel->get('Servicio de agua comercial')['lineas'][0] ?? [];
                $alcDom = $porLabel->get('Servicio de alcantarillado domiciliar')['lineas'][0] ?? [];
                $alcCom = $porLabel->get('Servicio de alcantarillado comercial')['lineas'][0] ?? [];

                return $labels === [
                    'Servicio de agua domiciliar',
                    'Servicio de agua comercial',
                    'Servicio de alcantarillado domiciliar',
                    'Servicio de alcantarillado comercial',
                ]
                    && ($aguaDom['fecha'] ?? '') === '25/10/2026 - 25/10/2026'
                    && ($aguaDom['detalle'] ?? '') === 'de agosto a octubre'
                    && (float) ($aguaDom['bolivianos'] ?? 0) === 24.0
                    && ($aguaCom['fecha'] ?? '') === '25/10/2026 - 25/10/2026'
                    && ($aguaCom['detalle'] ?? '') === 'de septiembre a octubre'
                    && (float) ($aguaCom['bolivianos'] ?? 0) === 34.0
                    && ($alcDom['detalle'] ?? '') === 'de agosto a octubre'
                    && (float) ($alcDom['bolivianos'] ?? 0) === 45.0
                    && ($alcCom['detalle'] ?? '') === 'de septiembre a octubre'
                    && (float) ($alcCom['bolivianos'] ?? 0) === 40.0;
            }));
});

test('ingresos report muestra rangos distintos en agua y alcantarillado entre años', function (): void {
    $secretaria = userWithRoleForDocs(Role::SECRETARIA, isSystemAccount: true);
    $agua = Servicio::query()->create([
        'codigo' => 'AGUA-XANIO',
        'nombre' => 'Agua Potable',
        'tipo' => 'agua',
        'tarifa_mensual' => 8,
        'activo' => true,
    ]);
    $alc = Servicio::query()->create([
        'codigo' => 'ALC-XANIO',
        'nombre' => 'Alcantarillado',
        'tipo' => 'alcantarillado',
        'tarifa_mensual' => 15,
        'activo' => true,
    ]);
    $afiliado = Afiliado::factory()->conLote()->create([
        'nombres' => 'Wilson',
        'apellidos' => 'Felix',
    ]);
    $lote = $afiliado->lotes()->firstOrFail();
    $fecha = '2026-08-15';

    foreach (['2025-08', '2025-09', '2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03'] as $periodo) {
        cuotaMensualPagada($lote->id, $agua->id, $periodo, 8);
        $pago = Pago::factory()->create([
            'afiliado_id' => $afiliado->id,
            'lote_afiliado_id' => $lote->id,
            'fecha_pago' => $fecha,
            'monto_total' => 8,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);
        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
            'descripcion' => 'Servicio agua potable',
            'monto' => 8,
            'servicio_id' => $agua->id,
            'periodo' => $periodo,
        ]);
    }

    foreach (['2026-08', '2026-09', '2026-10', '2026-11', '2026-12'] as $periodo) {
        cuotaMensualPagada($lote->id, $alc->id, $periodo, 15);
        $pago = Pago::factory()->create([
            'afiliado_id' => $afiliado->id,
            'lote_afiliado_id' => $lote->id,
            'fecha_pago' => $fecha,
            'monto_total' => 15,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);
        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
            'descripcion' => 'Servicio de Alcantarillado',
            'monto' => 15,
            'servicio_id' => $alc->id,
            'periodo' => $periodo,
        ]);
    }

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', ['desde' => $fecha, 'hasta' => $fecha]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Ingresos')
            ->where('reporte.grupos', function ($grupos) {
                $agua = collect($grupos)->firstWhere('label', 'Servicio de agua domiciliar')['lineas'][0] ?? [];
                $alc = collect($grupos)->firstWhere('label', 'Servicio de alcantarillado domiciliar')['lineas'][0] ?? [];

                return ($agua['fecha'] ?? '') === '15/08/2026 - 15/08/2026'
                    && ($agua['detalle'] ?? '') === 'de agosto 2025 a marzo 2026'
                    && ($alc['fecha'] ?? '') === '15/08/2026 - 15/08/2026'
                    && ($alc['detalle'] ?? '') === 'de agosto a diciembre'
                    && (float) ($agua['bolivianos'] ?? 0) === 64.0
                    && (float) ($alc['bolivianos'] ?? 0) === 75.0;
            }));
});

test('ingresos report suma solo los meses cobrados dentro del rango de fechas', function (): void {
    $secretaria = userWithRoleForDocs(Role::SECRETARIA, isSystemAccount: true);
    $agua = Servicio::query()->create([
        'codigo' => 'AGUA-12M',
        'nombre' => 'Agua Potable',
        'tipo' => 'agua',
        'tarifa_mensual' => 8,
        'activo' => true,
    ]);
    $afiliado = Afiliado::factory()->conLote()->create();
    $lote = $afiliado->lotes()->firstOrFail();
    $fechaPago = '2026-08-15';

    // 12 meses a 8 Bs, cada uno cobrado en su propio mes.
    foreach (range(1, 12) as $mes) {
        $periodo = sprintf('2026-%02d', $mes);
        cuotaMensualPagada($lote->id, $agua->id, $periodo, 8);
        $pago = Pago::factory()->create([
            'afiliado_id' => $afiliado->id,
            'lote_afiliado_id' => $lote->id,
            'fecha_pago' => $mes === 8 ? $fechaPago : sprintf('2026-%02d-10', $mes),
            'monto_total' => 8,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);
        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
            'descripcion' => 'Servicio agua potable',
            'monto' => 8,
            'servicio_id' => $agua->id,
            'periodo' => $periodo,
        ]);
    }

    $subtotalAgua = fn (array $grupos): float => (float) collect($grupos)
        ->filter(fn (array $grupo): bool => str_starts_with((string) $grupo['concepto'], 'servicio_agua_potable'))
        ->sum('subtotal');

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', ['desde' => $fechaPago, 'hasta' => $fechaPago]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporte.grupos', fn ($grupos) => $subtotalAgua(collect($grupos)->all()) === 8.0));

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', ['desde' => '2026-01-01', 'hasta' => '2026-06-30']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporte.grupos', fn ($grupos) => $subtotalAgua(collect($grupos)->all()) === 48.0));

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', ['desde' => '2026-01-01', 'hasta' => '2026-12-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporte.grupos', function ($grupos) use ($subtotalAgua) {
                $linea = collect($grupos)->first(fn (array $grupo): bool => str_starts_with((string) $grupo['concepto'], 'servicio_agua_potable'))['lineas'][0] ?? [];

                return ($linea['fecha'] ?? '') === '10/01/2026 - 10/12/2026'
                    && ($linea['detalle'] ?? '') === 'de enero a diciembre'
                    && $subtotalAgua(collect($grupos)->all()) === 96.0;
            }));
});

test('ingresos report usa titulo de concepto y no descripciones cortas como d o ect', function (): void {
    $secretaria = userWithRoleForDocs(Role::SECRETARIA, isSystemAccount: true);
    $afiliado = Afiliado::factory()->create([
        'nombres' => 'Abraham',
        'apellidos' => 'Alanes Roman',
    ]);
    $fecha = now()->toDateString();

    $pagoMulta = Pago::factory()->create([
        'afiliado_id' => $afiliado->id,
        'fecha_pago' => $fecha,
        'monto_total' => 250,
        'estado' => Pago::ESTADO_EMITIDO,
    ]);
    PagoDetalle::query()->create([
        'pago_id' => $pagoMulta->id,
        'concepto' => PagoDetalle::CONCEPTO_MULTA,
        'descripcion' => 'd',
        'monto' => 250,
        'periodo' => now()->format('Y-m'),
    ]);

    $pagoServicio = Pago::factory()->create([
        'afiliado_id' => $afiliado->id,
        'fecha_pago' => $fecha,
        'monto_total' => 4.75,
        'estado' => Pago::ESTADO_EMITIDO,
    ]);
    PagoDetalle::query()->create([
        'pago_id' => $pagoServicio->id,
        'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
        'descripcion' => 'ect',
        'monto' => 4.75,
        'periodo' => now()->format('Y-m'),
    ]);

    $pagoMultaTexto = Pago::factory()->create([
        'afiliado_id' => $afiliado->id,
        'fecha_pago' => $fecha,
        'monto_total' => 225,
        'estado' => Pago::ESTADO_EMITIDO,
    ]);
    PagoDetalle::query()->create([
        'pago_id' => $pagoMultaTexto->id,
        'concepto' => PagoDetalle::CONCEPTO_MULTA,
        'descripcion' => 'multa',
        'monto' => 225,
        'periodo' => now()->format('Y-m'),
    ]);

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', ['desde' => $fecha, 'hasta' => $fecha]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/Ingresos')
            ->where('reporte.grupos', function ($grupos) {
                $labels = collect($grupos)->pluck('label')->values()->all();

                return $labels === ['Multas']
                    && ! collect($labels)->contains(fn ($l) => in_array($l, ['d', 'ect', 'multa'], true));
            }));
});

test('afiliacion se separa en agua y alcantarillado al contado y a plazo', function (): void {
    $secretaria = userWithRoleForDocs(Role::SECRETARIA, isSystemAccount: true);
    $fecha = now()->toDateString();
    $otb = Otb::factory()->create(['nombre' => 'BARRIO 21']);

    $contado = Afiliado::factory()->conLote([
        'numero_lote' => '9',
        'otb_id' => $otb->id,
        'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
        'fecha_afiliacion' => $fecha,
        'monto_afiliacion_agua' => 3500,
        'monto_afiliacion_alcantarillado' => 2000,
        'saldo_afiliacion' => 0,
    ])->create([
        'nombres' => 'Hilarion',
        'apellidos' => 'Copa Gonzales',
    ]);
    $loteContado = $contado->lotes()->firstOrFail();
    $loteContado->update([
        'saldo_afiliacion_agua' => 0,
        'saldo_afiliacion_alcantarillado' => 0,
    ]);

    $plazo = Afiliado::factory()->conLote([
        'numero_lote' => '547',
        'otb_id' => $otb->id,
        'forma_pago_afiliacion' => Afiliado::FORMA_PLAZO,
        'fecha_afiliacion' => $fecha,
        'monto_afiliacion' => 5500,
        'monto_afiliacion_agua' => 3500,
        'monto_afiliacion_alcantarillado' => 2000,
        'saldo_afiliacion' => 2801,
    ])->create([
        'nombres' => 'Javier',
        'apellidos' => 'Mercado Huerta',
    ]);
    $lotePlazo = $plazo->lotes()->firstOrFail();
    $lotePlazo->update([
        'saldo_afiliacion_agua' => 1782,
        'saldo_afiliacion_alcantarillado' => 1019,
    ]);
    LoteAfiliado::query()->create([
        'afiliado_id' => $plazo->id,
        'numero_lote' => '548',
        'otb_id' => $otb->id,
        'direccion' => 'Calle 2',
        'fecha_afiliacion' => now()->subYear()->toDateString(),
        'forma_pago_afiliacion' => Afiliado::FORMA_PLAZO,
        'monto_afiliacion' => 3800,
        'monto_afiliacion_agua' => 3800,
        'saldo_afiliacion_agua' => 2600,
        'saldo_afiliacion_alcantarillado' => 0,
        'saldo_afiliacion' => 2600,
        'estado' => Afiliado::ESTADO_ACTIVO,
    ]);

    Afiliado::factory()->conLote([
        'numero_lote' => '557',
        'otb_id' => $otb->id,
        'forma_pago_afiliacion' => Afiliado::FORMA_PLAZO,
        'fecha_afiliacion' => $fecha,
        'monto_afiliacion' => 1000,
        'monto_afiliacion_agua' => 1000,
        'saldo_afiliacion' => 1000,
    ])->create([
        'nombres' => 'pepe',
        'apellidos' => 'llanos',
    ])->lotes()->firstOrFail()->update([
        'saldo_afiliacion_agua' => 1000,
        'saldo_afiliacion_alcantarillado' => 0,
    ]);

    $pagoContado = Pago::factory()->create([
        'afiliado_id' => $contado->id,
        'lote_afiliado_id' => $loteContado->id,
        'fecha_pago' => $fecha,
        'monto_total' => 5500,
        'estado' => Pago::ESTADO_EMITIDO,
    ]);
    PagoDetalle::query()->create([
        'pago_id' => $pagoContado->id,
        'concepto' => PagoDetalle::CONCEPTO_AFILIACION_AGUA,
        'descripcion' => 'Afiliación agua potable',
        'monto' => 3500,
    ]);
    PagoDetalle::query()->create([
        'pago_id' => $pagoContado->id,
        'concepto' => PagoDetalle::CONCEPTO_AFILIACION_ALCANTARILLADO,
        'descripcion' => 'Afiliación alcantarillado',
        'monto' => 2000,
    ]);

    $pagoPlazo = Pago::factory()->create([
        'afiliado_id' => $plazo->id,
        'lote_afiliado_id' => $lotePlazo->id,
        'fecha_pago' => $fecha,
        'monto_total' => 2000,
        'estado' => Pago::ESTADO_EMITIDO,
    ]);
    PagoDetalle::query()->create([
        'pago_id' => $pagoPlazo->id,
        'concepto' => PagoDetalle::CONCEPTO_AFILIACION_AGUA,
        'descripcion' => 'Afiliación agua potable',
        'monto' => 1310.34,
    ]);
    PagoDetalle::query()->create([
        'pago_id' => $pagoPlazo->id,
        'concepto' => PagoDetalle::CONCEPTO_AFILIACION_ALCANTARILLADO,
        'descripcion' => 'Afiliación alcantarillado',
        'monto' => 689.66,
    ]);

    $this->actingAs($secretaria)
        ->get(route('reportes.ingresos', ['desde' => $fecha, 'hasta' => $fecha]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporte.grupos', function ($grupos) {
                $porLabel = collect($grupos)->keyBy('label');
                $buscar = function (?array $grupo, string $nombre): ?array {
                    return collect($grupo['lineas'] ?? [])->first(
                        fn ($linea) => str_contains((string) $linea['socio'], $nombre),
                    );
                };
                $aguaContado = $buscar($porLabel->get('Afiliación de agua al contado'), 'Hilarion');
                $aguaJavier = $buscar($porLabel->get('Afiliación de agua a plazo'), 'Javier');
                $aguaPepe = $buscar($porLabel->get('Afiliación de agua a plazo'), 'pepe');
                $alcContado = $buscar($porLabel->get('Afiliación de alcantarillado al contado'), 'Hilarion');
                $alcJavier = $buscar($porLabel->get('Afiliación de alcantarillado a plazo'), 'Javier');
                $alcPepe = $buscar($porLabel->get('Afiliación de alcantarillado a plazo'), 'pepe');

                return $aguaContado !== null
                    && $aguaJavier !== null
                    && $aguaPepe !== null
                    && $alcContado !== null
                    && $alcJavier !== null
                    && $alcPepe === null
                    && ! $porLabel->has('Afiliación')
                    && round((float) $aguaContado['bolivianos'], 2) === 3500.0
                    && $aguaContado['detalle'] === 'cancelado'
                    && round((float) $alcContado['bolivianos'], 2) === 2000.0
                    && $alcContado['detalle'] === 'cancelado'
                    && str_contains((string) $aguaJavier['socio'], 'Lote 547')
                    && round((float) $aguaJavier['bolivianos'], 2) === 1782.0
                    && $aguaJavier['detalle'] === 'debe Bs 1.782,00'
                    && str_contains((string) $alcJavier['socio'], 'Lote 547')
                    && round((float) $alcJavier['bolivianos'], 2) === 1019.0
                    && $alcJavier['detalle'] === 'debe Bs 1.019,00'
                    && round((float) $aguaPepe['bolivianos'], 2) === 1000.0
                    && $aguaPepe['detalle'] === 'debe Bs 1.000,00'
                    && collect($porLabel->get('Afiliación de agua a plazo')['lineas'] ?? [])
                        ->contains(fn ($linea) => str_contains((string) $linea['socio'], 'Javier')
                            && str_contains((string) $linea['socio'], 'Lote 548')
                            && round((float) $linea['bolivianos'], 2) === 2600.0);
            }));
});
