<?php

namespace Database\Seeders;

use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\Servicio;
use App\Models\Otb;
use Illuminate\Database\Seeder;

class AfiliadosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategoriasSeeder::class);

        $domiciliaria = Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->firstOrFail();
        $comercial = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->firstOrFail();

        $zonaCentro = Otb::query()->updateOrCreate(
            ['nombre' => 'Centro Isinuta'],
            [
                'descripcion' => 'Zona central de prueba',
                'tipo' => 'OTB',
                'activa' => true,
            ],
        );

        $zonaNorte = Otb::query()->updateOrCreate(
            ['nombre' => 'Norte Isinuta'],
            [
                'descripcion' => 'Zona norte de prueba',
                'tipo' => 'OTB',
                'activa' => true,
            ],
        );

        $agua = Servicio::query()->updateOrCreate(
            ['codigo' => 'AGUA'],
            [
                'nombre' => 'Agua potable',
                'tipo' => 'agua',
                'tarifa_mensual' => 8.00,
                'descripcion' => 'Servicio de agua potable',
                'activo' => true,
            ],
        );

        $alcantarillado = Servicio::query()->updateOrCreate(
            ['codigo' => 'ALC'],
            [
                'nombre' => 'Alcantarillado',
                'tipo' => 'alcantarillado',
                'tarifa_mensual' => 15.00,
                'descripcion' => 'Servicio de alcantarillado',
                'activo' => true,
            ],
        );

        $afiliados = [
            [
                'ci' => '01234567',
                'nombres' => 'Juan Carlos',
                'apellidos' => 'Mamani Quispe',
                'telefono' => '71234567',
                'direccion' => 'Calle Principal #12',
                'otb_id' => $zonaCentro->id,
                'numero_lote' => '101',
                'estado' => Afiliado::ESTADO_ACTIVO,
                'saldo_afiliacion' => 0,
                'categoria_id' => $domiciliaria->id,
                'servicios' => [$agua->id, $alcantarillado->id],
            ],
            [
                'ci' => '07654321',
                'nombres' => 'María Elena',
                'apellidos' => 'Choque Vargas',
                'telefono' => '72345678',
                'direccion' => 'Av. Bolívar #45',
                'otb_id' => $zonaNorte->id,
                'numero_lote' => '202',
                'estado' => Afiliado::ESTADO_ACTIVO,
                'saldo_afiliacion' => 500,
                'categoria_id' => $comercial->id,
                'servicios' => [$agua->id],
            ],
            [
                'ci' => '09988776',
                'nombres' => 'Pedro Luis',
                'apellidos' => 'Flores Rocha',
                'telefono' => '73456789',
                'direccion' => 'Barrio San José s/n',
                'otb_id' => $zonaCentro->id,
                'numero_lote' => '303',
                'estado' => Afiliado::ESTADO_CORTADO,
                'saldo_afiliacion' => 1200,
                'categoria_id' => $domiciliaria->id,
                'servicios' => [$agua->id, $alcantarillado->id],
            ],
        ];

        foreach ($afiliados as $data) {
            $servicios = $data['servicios'];
            unset($data['servicios']);

            $afiliado = Afiliado::query()->updateOrCreate(
                ['ci' => $data['ci']],
                [
                    'nombres' => $data['nombres'],
                    'apellidos' => $data['apellidos'],
                    'telefono' => $data['telefono'],
                    'direccion' => $data['direccion'],
                    'estado' => $data['estado'],
                ],
            );

            $categoria = Categoria::query()->findOrFail($data['categoria_id']);

            $lote = $afiliado->sincronizarLotePrincipal([
                'numero_lote' => $data['numero_lote'],
                'otb_id' => $data['otb_id'],
                'direccion' => $data['direccion'],
                'fecha_afiliacion' => now()->subMonths(6)->toDateString(),
                'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
                'monto_afiliacion' => 3500,
                'saldo_afiliacion' => $data['saldo_afiliacion'],
                'estado' => $data['estado'],
                'observaciones' => 'Afiliado de prueba',
                'categoria_id' => $categoria->id,
            ]);

            $fechaInicio = $lote->fecha_afiliacion?->toDateString() ?? now()->toDateString();
            $lote->load('categoria');
            $lote->sincronizarServicios($servicios, $fechaInicio);
        }
    }
}
