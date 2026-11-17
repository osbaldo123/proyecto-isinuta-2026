<?php

namespace App\Services;

use App\Enums\Permission as PermissionEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    /**
     * Roles institucionales visibles en Usuarios; se editan datos personales,
     * pero no se asignan a usuarios nuevos ni se eliminan desde el módulo.
     *
     * @return array<int, string>
     */
    public function institutionalAccountRoleNames(): array
    {
        return [
            Role::SECRETARIA,
            Role::PRESIDENTE,
        ];
    }

    /**
     * Roles que no pueden asignarse al crear usuarios nuevos.
     *
     * @return array<int, string>
     */
    public function protectedRoleNames(): array
    {
        return [
            Role::SECRETARIA,
            Role::PRESIDENTE,
            Role::ADMIN,
        ];
    }

    /**
     * Roles ocultos del catálogo de Usuarios (solo desarrollo).
     *
     * @return array<int, string>
     */
    public function hiddenFromCatalogRoleNames(): array
    {
        return [
            Role::ADMIN,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function assignableRolesFor(User $actor): array
    {
        if (! $this->canManageUsers($actor)) {
            return [];
        }

        $roles = Role::query()
            ->whereNotIn('nombre', $this->protectedRoleNames())
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['nombre', 'descripcion']);

        if ($roles->isEmpty()) {
            return [[
                'value' => Role::CONSULTA_PAGOS_MULTAS,
                'label' => $this->roleLabel(Role::CONSULTA_PAGOS_MULTAS),
            ]];
        }

        return $roles
            ->map(fn (Role $role): array => [
                'value' => $role->nombre,
                'label' => $role->displayName(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function assignableRoleValuesFor(User $actor): array
    {
        return array_column($this->assignableRolesFor($actor), 'value');
    }

    public function canManageUsers(User $actor): bool
    {
        return $actor->hasPermission(PermissionEnum::GestionarUsuariosConsulta->value);
    }

    public function canFullyManageUsers(User $actor): bool
    {
        return $this->canManageUsers($actor);
    }

    public function isInstitutionalAccount(User $target): bool
    {
        if ($target->hasAnyRole($this->institutionalAccountRoleNames())) {
            return true;
        }

        return in_array((string) $target->role, $this->institutionalAccountRoleNames(), true);
    }

    public function isHiddenFromCatalog(User $target): bool
    {
        if ($target->hasAnyRole($this->hiddenFromCatalogRoleNames())) {
            return true;
        }

        return in_array((string) $target->role, $this->hiddenFromCatalogRoleNames(), true);
    }

    /**
     * @return Builder<User>
     */
    public function manageableUsersQuery(): Builder
    {
        $hidden = $this->hiddenFromCatalogRoleNames();

        return User::query()
            ->where(function (Builder $query) use ($hidden): void {
                $query->whereNull('role')->orWhereNotIn('role', $hidden);
            })
            ->whereDoesntHave('roles', function (Builder $roleQuery) use ($hidden): void {
                $roleQuery->whereIn('nombre', $hidden);
            })
            // Mientras los usuarios de afiliados estén desactivados, sus cuentas no se muestran.
            ->when(
                ! config('sistema.usuarios_afiliados'),
                fn (Builder $query) => $query->whereNull('afiliado_id'),
            );
    }

    public function assertCanManageTarget(User $actor, User $target): void
    {
        if (! $this->canManageUsers($actor)) {
            abort(403);
        }

        if ($this->isHiddenFromCatalog($target)) {
            abort(403);
        }
    }

    public function assertCanDeleteTarget(User $actor, User $target): void
    {
        $this->assertCanManageTarget($actor, $target);

        if ($this->isInstitutionalAccount($target) || $target->is_system_account) {
            abort(403, 'Las cuentas de Presidente y Secretaria no pueden eliminarse.');
        }
    }

    public function assertCanAssignRole(User $actor, string $roleName): void
    {
        $asignables = array_map(
            static fn (string $nombre): string => mb_strtolower($nombre),
            $this->assignableRoleValuesFor($actor),
        );

        if (! in_array(mb_strtolower($roleName), $asignables, true)) {
            throw ValidationException::withMessages([
                'rol' => 'No tiene permiso para asignar ese rol. Cree el rol en el módulo Roles o elija uno de la lista.',
            ]);
        }
    }

    public function normalizeCustomRoleName(string $label): string
    {
        $nombre = Str::slug(trim($label), '_');

        if ($nombre === '') {
            throw ValidationException::withMessages([
                'rol_nuevo' => 'Escriba un nombre válido para el nuevo rol.',
            ]);
        }

        return $nombre;
    }

    /**
     * Busca un rol existente por nombre técnico (case-insensitive) o por etiqueta visible.
     */
    public function findRoleByNameOrLabel(string $value): ?Role
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $slug = Str::slug($value, '_');

        return Role::query()
            ->where(function ($query) use ($value, $slug): void {
                $query->whereRaw('LOWER(nombre) = ?', [mb_strtolower($value)]);

                if ($slug !== '') {
                    $query->orWhereRaw('LOWER(nombre) = ?', [mb_strtolower($slug)]);
                }
            })
            ->first();
    }

    public function resolveAssignableRoleName(User $actor, string $rol, ?string $rolNuevo = null): string
    {
        if (! $this->canManageUsers($actor)) {
            abort(403);
        }

        if ($rol === '__otro__' || filled($rolNuevo)) {
            $etiqueta = (string) ($rolNuevo ?: '');
            $existente = $this->findRoleByNameOrLabel($etiqueta);

            if ($existente) {
                if (in_array($existente->nombre, $this->protectedRoleNames(), true)) {
                    throw ValidationException::withMessages([
                        'rol_nuevo' => 'Ese nombre de rol está reservado del sistema.',
                    ]);
                }

                return $existente->nombre;
            }

            $nombre = $this->normalizeCustomRoleName($etiqueta);

            if (in_array($nombre, $this->protectedRoleNames(), true)) {
                throw ValidationException::withMessages([
                    'rol_nuevo' => 'Ese nombre de rol está reservado del sistema.',
                ]);
            }

            return $nombre;
        }

        $existente = $this->findRoleByNameOrLabel($rol);

        if ($existente) {
            $this->assertCanAssignRole($actor, $existente->nombre);

            return $existente->nombre;
        }

        $this->assertCanAssignRole($actor, $rol);

        return $rol;
    }

    public function roleLabel(string $roleName): string
    {
        return match ($roleName) {
            Role::SECRETARIA => 'Secretaria',
            Role::PRESIDENTE => 'Presidente administrador',
            Role::CONSULTA_PAGOS_MULTAS => 'Consulta (pagos y multas)',
            Role::ADMIN => 'Administrador (desarrollo)',
            Role::CAJERA => 'Cajera',
            Role::OPERADOR => 'Operador',
            default => Role::labelFor($roleName),
        };
    }
}
