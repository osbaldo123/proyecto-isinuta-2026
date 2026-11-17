<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUser([
            'email' => 'lorenaMamani@gmail.com',
            'name' => 'Lorena Mamani Huerta',
            'password' => 'lorena1234',
            'role' => Role::SECRETARIA,
            'ci' => '10000001',
            'telefono' => '70000001',
            'is_system_account' => true,
        ]);

        $this->seedUser([
            'email' => 'sergioNina@gmail.com',
            'name' => 'Enrique Sergio Nina',
            'password' => 'nina1234',
            'role' => Role::PRESIDENTE,
            'ci' => '10000002',
            'telefono' => '70000002',
            'is_system_account' => false,
        ]);

        $this->seedUser([
            'email' => 'consulta@isinuta.test',
            'name' => 'Ana Consulta Rojas',
            'password' => 'consulta1234',
            'role' => Role::CONSULTA_PAGOS_MULTAS,
            'ci' => '10000003',
            'telefono' => '70000003',
            'is_system_account' => false,
        ]);
    }

    /**
     * @param  array{
     *     email: string,
     *     name: string,
     *     password: string,
     *     role: string,
     *     ci: string,
     *     telefono: string,
     *     is_system_account: bool
     * }  $data
     */
    private function seedUser(array $data): void
    {
        // Secretaria/presidente: actualizar la cuenta del rol (aunque cambie el correo).
        $user = in_array($data['role'], [Role::SECRETARIA, Role::PRESIDENTE], true)
            ? User::query()->where('role', $data['role'])->orderByDesc('is_system_account')->first()
            : null;

        $user ??= User::query()->firstOrNew(['email' => $data['email']]);

        $user->fill([
            'email' => $data['email'],
            'name' => $data['name'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
            'ci' => $user->ci ?: $data['ci'],
            'telefono_codigo' => $user->telefono_codigo ?: '+591',
            'telefono' => $user->telefono ?: $data['telefono'],
        ]);

        $user->forceFill([
            'role' => $data['role'],
            'is_active' => true,
            'is_system_account' => $data['is_system_account'],
        ])->save();

        $user->assignRole($data['role']);
    }
}
