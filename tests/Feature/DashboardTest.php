<?php

namespace Tests\Feature;

use App\Models\Afiliado;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create(['role' => Role::ADMIN]);
        $role = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();
        $user->roles()->sync([$role->id]);

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_presidente_ve_pagos_del_mes_en_el_panel(): void
    {
        $user = User::factory()->create(['role' => Role::PRESIDENTE]);
        $role = Role::query()->where('nombre', Role::PRESIDENTE)->firstOrFail();
        $user->roles()->sync([$role->id]);

        $afiliado = Afiliado::factory()->conLote([
            'fecha_afiliacion' => now()->startOfMonth()->toDateString(),
            'saldo_afiliacion' => 0,
        ])->create([
            'nombres' => 'Rosa',
            'apellidos' => 'Mendoza',
            'estado' => Afiliado::ESTADO_ACTIVO,
        ]);

        Afiliado::factory()->conLote([
            'fecha_afiliacion' => now()->startOfMonth()->toDateString(),
            'saldo_afiliacion' => 0,
        ])->create([
            'nombres' => 'Luis',
            'apellidos' => 'Pendiente',
            'estado' => Afiliado::ESTADO_ACTIVO,
        ]);

        $pago = Pago::factory()->create([
            'afiliado_id' => $afiliado->id,
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 80,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);

        PagoDetalle::query()->create([
            'pago_id' => $pago->id,
            'concepto' => PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
            'descripcion' => 'Cuota mensual',
            'monto' => 23,
            'periodo' => now()->format('Y-m'),
        ]);

        $mes = match ((int) now()->format('n')) {
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            default => 'diciembre',
        };

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('es_presidente', true)
                ->where('mes_pago_label', $mes)
                ->has('pagos_del_mes', 1)
                ->where('pagos_del_mes.0.nombre_completo', 'Rosa Mendoza')
                ->where('pagos_recientes', [])
                ->where('resumen.afiliados_pagaron_mes', 1)
                ->where('resumen.afiliados_pendientes_pago', 1)
                ->where('estadistica_anual.anio', (int) now()->format('Y'))
                ->where('estadistica_anual.recibos', 1)
                ->where('estadistica_anual.total', 80)
                ->has('estadistica_anual.meses', 12)
            );
    }

    public function test_dashboard_registra_cortados_por_mora_real(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        $user = User::factory()->create(['role' => Role::ADMIN]);
        $role = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();
        $user->roles()->sync([$role->id]);

        $conMora = Afiliado::factory()->conLote([
            'fecha_afiliacion' => now()->subMonths(13)->startOfMonth()->toDateString(),
            'saldo_afiliacion' => 0,
            'estado' => Afiliado::ESTADO_ACTIVO,
        ])->create([
            'estado' => Afiliado::ESTADO_ACTIVO,
        ]);

        $alDia = Afiliado::factory()->conLote([
            'fecha_afiliacion' => now()->startOfMonth()->toDateString(),
            'saldo_afiliacion' => 0,
            'estado' => Afiliado::ESTADO_ACTIVO,
        ])->create([
            'estado' => Afiliado::ESTADO_ACTIVO,
        ]);

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('resumen.afiliados_cortados', 1)
                ->where('resumen.afiliados_activos', 1)
            );

        $this->assertSame(Afiliado::ESTADO_CORTADO, $conMora->fresh()->estado);
        $this->assertSame(Afiliado::ESTADO_ACTIVO, $alDia->fresh()->estado);
    }

    public function test_dashboard_muestra_ultimo_cierre_si_no_hay_pagos_hoy(): void
    {
        $user = User::factory()->create(['role' => Role::ADMIN]);
        $role = Role::query()->where('nombre', Role::ADMIN)->firstOrFail();
        $user->roles()->sync([$role->id]);

        $afiliado = Afiliado::factory()->conLote([
            'fecha_afiliacion' => now()->startOfMonth()->toDateString(),
            'saldo_afiliacion' => 0,
        ])->create(['estado' => Afiliado::ESTADO_ACTIVO]);

        Pago::factory()->create([
            'afiliado_id' => $afiliado->id,
            'fecha_pago' => now()->subDays(3)->toDateString(),
            'monto_total' => 2362,
            'estado' => Pago::ESTADO_EMITIDO,
        ]);

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('resumen.pagos_hoy', 2362)
                ->where('resumen.pagos_hoy_fecha', now()->subDays(3)->toDateString())
            );
    }
}
