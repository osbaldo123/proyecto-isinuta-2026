<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionEnum::cases() as $permission) {
            Permission::query()->updateOrCreate(
                ['nombre' => $permission->value],
                [
                    'descripcion' => $permission->label(),
                    'grupo' => $permission->group(),
                ]
            );
        }

        $secretaria = Role::query()->updateOrCreate(
            ['nombre' => Role::SECRETARIA],
            ['descripcion' => 'Registro y actualización de afiliados, servicios, trabajos y pagos; consultas e impresión de reportes administrativos.']
        );

        $presidente = Role::query()->updateOrCreate(
            ['nombre' => Role::PRESIDENTE],
            ['descripcion' => 'Revisa los datos ingresados por la secretaria (deudas, cancelaciones y servicios). Único responsable de crear usuarios y roles.']
        );

        $consulta = Role::query()->updateOrCreate(
            ['nombre' => Role::CONSULTA_PAGOS_MULTAS],
            ['descripcion' => 'Consulta de pagos y multas']
        );

        $admin = Role::query()->updateOrCreate(
            ['nombre' => Role::ADMIN],
            ['descripcion' => 'Administrador (desarrollo)']
        );

        $secretaria->syncPermissionsByName(PermissionEnum::valuesForSecretaria());
        $presidente->syncPermissionsByName(PermissionEnum::valuesForPresidente());
        $consulta->syncPermissionsByName(PermissionEnum::valuesForConsultaPagosMultas());
        $admin->syncPermissionsByName(PermissionEnum::valuesForSecretaria());
    }
}
