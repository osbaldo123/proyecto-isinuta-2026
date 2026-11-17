<?php

namespace App\Services;

use App\Models\Afiliado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AfiliadoUserService
{
    /**
     * @return array{user: User, password: string}|null
     */
    public function createForAfiliado(Afiliado $afiliado): ?array
    {
        if ($this->findUserByCi($afiliado)) {
            // Misma persona con otro lote: no se crea otro usuario de consulta.
            return null;
        }

        $email = $this->generarEmailSintetico($afiliado);
        $password = Str::password(10, letters: true, numbers: true, symbols: false);

        $user = User::create([
            'name' => $afiliado->nombre_completo,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => null,
            'afiliado_id' => $afiliado->id,
            'ci' => $afiliado->ci,
            'telefono_codigo' => '+591',
            'telefono' => preg_replace('/\D/', '', (string) $afiliado->telefono) ?: null,
        ]);

        $user->forceFill([
            'role' => Role::CONSULTA_PAGOS_MULTAS,
            'is_active' => $afiliado->estado === Afiliado::ESTADO_ACTIVO,
            'is_system_account' => false,
        ])->save();

        $user->assignRole(Role::CONSULTA_PAGOS_MULTAS);

        return ['user' => $user, 'password' => $password];
    }

    public function syncMissingUsers(): int
    {
        $created = 0;

        Afiliado::query()
            ->whereDoesntHave('user')
            ->orderBy('id')
            ->each(function (Afiliado $afiliado) use (&$created): void {
                if ($this->createForAfiliado($afiliado)) {
                    $created++;
                }
            });

        return $created;
    }

    public function syncFromAfiliado(Afiliado $afiliado): void
    {
        $user = $afiliado->user ?? $this->findUserByCi($afiliado);

        if (! $user) {
            return;
        }

        $user->fill([
            'name' => $afiliado->nombre_completo,
            'ci' => $afiliado->ci,
            'telefono' => preg_replace('/\D/', '', (string) $afiliado->telefono) ?: $user->telefono,
        ]);

        $user->forceFill([
            'is_active' => $afiliado->estado === Afiliado::ESTADO_ACTIVO,
        ])->save();
    }

    private function findUserByCi(Afiliado $afiliado): ?User
    {
        return User::query()
            ->where('ci', $afiliado->ci)
            ->first();
    }

    private function generarEmailSintetico(Afiliado $afiliado): string
    {
        $base = 'afiliado.'.$afiliado->ci.'@isinuta.test';
        $email = $base;
        $suffix = 1;

        while (User::query()->where('email', $email)->exists()) {
            $email = 'afiliado.'.$afiliado->ci.'.'.$suffix.'@isinuta.test';
            $suffix++;
        }

        return $email;
    }
}
