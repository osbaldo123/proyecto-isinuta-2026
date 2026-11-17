<?php

use App\Models\Afiliado;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\User;
use App\Models\Otb;
use App\Services\ReciboPropositoService;
use App\Support\TextoPresentacion;
use Database\Seeders\PermissionsSeeder;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
});

function userCobranza(): User
{
    $user = User::factory()->create([
        'role' => Role::SECRETARIA,
        'is_system_account' => true,
        'is_active' => true,
    ]);
    $role = Role::query()->where('nombre', Role::SECRETARIA)->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

it('emite recibos separados por propósito ingreso y caja', function (): void {
    $user = userCobranza();
    $agua = Servicio::factory()->create(['tipo' => 'agua', 'codigo' => 'AGUA', 'activo' => true]);
    $alc = Servicio::factory()->create(['tipo' => 'alcantarillado', 'codigo' => 'ALC', 'activo' => true]);
    $afiliado = Afiliado::factory()->conLote(['saldo_afiliacion' => 0])->create(['estado' => Afiliado::ESTADO_ACTIVO]);
    $afiliado->lotes()->first()?->servicios()->attach([$agua->id, $alc->id]);

    $this->actingAs($user)->post(route('pagos.store'), [
        'afiliado_id' => $afiliado->id,
        'fecha_pago' => now()->toDateString(),
        'metodo_pago' => Pago::METODO_EFECTIVO,
        'detalles' => [
            [
                'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
                'descripcion' => 'Servicio Agua Potable',
                'monto' => 8,
                'servicio_id' => $agua->id,
                'periodo' => now()->format('Y-m'),
            ],
            [
                'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
                'descripcion' => 'Servicio de Alcantarillado',
                'monto' => 15,
                'servicio_id' => $alc->id,
                'periodo' => now()->format('Y-m'),
            ],
            [
                'concepto' => PagoDetalle::CONCEPTO_OTRO,
                'descripcion' => 'cambio de nombre',
                'monto' => 100,
            ],
            [
                'concepto' => PagoDetalle::CONCEPTO_MULTA,
                'descripcion' => 'falta de reuniones y trabajos 2020-2021',
                'monto' => 1000,
            ],
        ],
    ])->assertRedirect();

    expect(Pago::query()->count())->toBe(5);

    $ingreso = Pago::query()->where('tipo_recibo', Pago::TIPO_INGRESO)->get();
    $caja = Pago::query()->where('tipo_recibo', Pago::TIPO_CAJA)->get();

    expect($ingreso)->toHaveCount(2)
        ->and((float) $ingreso->sum('monto_total'))->toBe(23.0)
        ->and($caja)->toHaveCount(3);

    $proposito = app(ReciboPropositoService::class);
    expect($proposito->montoEnLetras(57))->toBe('Son: CINCUENTA Y SIETE BOLIVIANOS')
        ->and($proposito->montoEnLetras(57.5))->toBe('Son: CINCUENTA Y SIETE BOLIVIANOS 50/100')
        ->and($proposito->montoEnLetras(100))->toBe('Son: CIEN BOLIVIANOS')
        ->and($proposito->montoEnLetras(15))->toBe('Son: QUINCE BOLIVIANOS')
        ->and($proposito->montoEnLetras(1000))->toBe('Son: MIL BOLIVIANOS')
        ->and($proposito->montoEnLetras(1))->toBe('Son: UN BOLIVIANO');

    $reciboCaja = $caja->firstWhere('proposito', 'cambio_de_nombre');
    $this->actingAs($user)
        ->get(route('pagos.show', $reciboCaja->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos/Show')
            ->where('pago.tipo_recibo', 'caja')
            ->where('pago.titulo_recibo', 'RECIBO DE CAJA')
            ->where('pago.monto_en_letras', 'Son: CIEN BOLIVIANOS'));

    $reciboIngreso = $ingreso->first();
    $this->actingAs($user)
        ->get(route('pagos.show', $reciboIngreso->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pagos/Show')
            ->where('pago.tipo_recibo', 'ingreso')
            ->where('pago.titulo_recibo', 'RECIBO DE INGRESO'));
});

it('no duplica el barrio ni frases repetidas en el recibo', function (): void {
    expect(TextoPresentacion::componerNombreConOtb(
        'JOSE MER (BARRIO 21 DE SEPTIEMBRE)',
        'BARRIO 21 DE SEPTIEMBRE',
    ))->toBe('JOSE MER (BARRIO 21 DE SEPTIEMBRE)');

    expect(TextoPresentacion::sinDuplicados(
        'JOSE MER (BARRIO 21 DE SEPTIEMBRE) (BARRIO 21 DE SEPTIEMBRE)',
    ))->toBe('JOSE MER (BARRIO 21 DE SEPTIEMBRE)');

    expect(TextoPresentacion::sinDuplicados('Barrio Barrio 21 de Septiembre'))
        ->toBe('Barrio 21 de Septiembre');

    expect(TextoPresentacion::sinDuplicados('Aporte ordinario Aporte ordinario'))
        ->toBe('Aporte ordinario');

    expect(TextoPresentacion::componerNombreConOtb('JOSE MER', 'BARRIO 21 DE SEPTIEMBRE'))
        ->toBe('JOSE MER (BARRIO 21 DE SEPTIEMBRE)');

    $otb = Otb::factory()->create(['nombre' => 'BARRIO 21 DE SEPTIEMBRE']);
    $afiliado = Afiliado::factory()->conLote(['otb_id' => $otb->id])->create([
        'nombres' => 'JOSE MER (BARRIO 21 DE SEPTIEMBRE)',
        'apellidos' => '',
    ]);

    expect($afiliado->fresh()->identidadDocumento())->toBe('JOSE MER (BARRIO 21 DE SEPTIEMBRE)');

    $proposito = app(ReciboPropositoService::class);
    expect(TextoPresentacion::sinDuplicados('JOSE MER JOSE MER (BARRIO 21 DE SEPTIEMBRE)'))
        ->toBe('JOSE MER (BARRIO 21 DE SEPTIEMBRE)');

    expect(TextoPresentacion::sinDuplicados('08/2026 Servicio Agua Potable 08/2026 Servicio Agua Potable'))
        ->toBe('08/2026 Servicio Agua Potable');

    expect($proposito->textoConcepto(
        ['descripcion' => '08/2026 Servicio Agua Potable', 'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL, 'periodo' => '2026-08'],
        ['key' => 'servicio_agua_potable', 'label' => 'Servicio Agua Potable', 'tipo' => ReciboPropositoService::TIPO_INGRESO],
        '25/08/2026',
    ))->toBe('Servicio Agua Potable');

    expect($proposito->textoConcepto(
        ['descripcion' => 'multa', 'concepto' => PagoDetalle::CONCEPTO_MULTA, 'periodo' => '2026-08'],
        ['key' => 'multa:multa', 'label' => 'Multa', 'tipo' => ReciboPropositoService::TIPO_CAJA],
        '25/08/2026',
    ))->toBe('multa');
});
