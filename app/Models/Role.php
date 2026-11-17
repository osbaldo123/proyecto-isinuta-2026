<?php

namespace App\Models;

use App\Enums\Permission as PermissionEnum;
use App\Models\Concerns\HasSpanishTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasSpanishTimestamps;

    protected $fillable = ['nombre', 'descripcion', 'activo', 'user_id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'activo' => true,
    ];

    public const SECRETARIA = 'secretaria';

    public const PRESIDENTE = 'presidente';

    public const CONSULTA_PAGOS_MULTAS = 'consulta_pagos_multas';

    public const ADMIN = 'admin';

    public const CAJERA = 'cajera';

    public const OPERADOR = 'operador';

    /**
     * @return array<int, string>
     */
    public static function institutionalRoleNames(): array
    {
        return [
            self::SECRETARIA,
            self::PRESIDENTE,
            self::ADMIN,
        ];
    }

    public function displayName(): string
    {
        return match ($this->nombre) {
            self::SECRETARIA => 'Secretaria',
            self::PRESIDENTE => 'Presidente administrador',
            self::CONSULTA_PAGOS_MULTAS => 'Consulta pagos y multas',
            self::ADMIN => 'Administrador (desarrollo)',
            self::CAJERA => 'Cajera',
            self::OPERADOR => 'Operador',
            default => str($this->nombre)->headline()->toString(),
        };
    }

    /**
     * Rol técnico oculto al presidente en el catálogo (solo desarrollo).
     *
     * @return array<int, string>
     */
    public static function hiddenFromPresidenteCatalog(): array
    {
        return [
            self::ADMIN,
            self::CAJERA,
            self::OPERADOR,
        ];
    }

    public static function reservedRoleNames(): array
    {
        return [
            self::SECRETARIA,
            self::PRESIDENTE,
            self::CONSULTA_PAGOS_MULTAS,
            self::ADMIN,
            self::CAJERA,
            self::OPERADOR,
        ];
    }

    public static function labelFor(?string $nombre): string
    {
        if (! filled($nombre)) {
            return 'Usuario';
        }

        return (new self(['nombre' => $nombre]))->displayName();
    }

    public function isInstitutional(): bool
    {
        return in_array($this->nombre, [
            self::SECRETARIA,
            self::PRESIDENTE,
            self::CONSULTA_PAGOS_MULTAS,
            self::ADMIN,
            self::CAJERA,
            self::OPERADOR,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * El rol de presidente se identifica como protegido ante eliminación.
     */
    public function isProtectedRole(): bool
    {
        return $this->nombre === self::PRESIDENTE;
    }

    public function isLockedFromEdit(): bool
    {
        return false;
    }

    /**
     * Todos los roles (incluido presidente) pueden darse de baja.
     */
    public function isLockedFromDeactivation(): bool
    {
        return false;
    }

    /**
     * Los roles institucionales (incluido presidente) no se eliminan: se dan de baja.
     */
    public function isLockedFromDeletion(): bool
    {
        return $this->isInstitutional();
    }

    /**
     * @deprecated Prefer isLockedFromEdit() / isLockedFromDeletion(); se mantiene por compatibilidad.
     */
    public function isLockedFromManagement(): bool
    {
        return false;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps('fecha_creacion', 'fecha_actualizacion');
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role')->withTimestamps('fecha_creacion', 'fecha_actualizacion');
    }

    /**
     * @param  array<string>  $names
     */
    public function syncPermissionsByName(array $names): void
    {
        $permissionIds = Permission::query()
            ->whereIn('nombre', $names)
            ->pluck('id')
            ->all();

        $this->permissions()->sync($permissionIds);
    }

    public function ensureDefaultPermissions(): void
    {
        $permissions = match ($this->nombre) {
            self::SECRETARIA, self::ADMIN => PermissionEnum::valuesForSecretaria(),
            self::PRESIDENTE => PermissionEnum::valuesForPresidente(),
            self::CONSULTA_PAGOS_MULTAS => PermissionEnum::valuesForConsultaPagosMultas(),
            self::CAJERA, self::OPERADOR => PermissionEnum::valuesForCajera(),
            default => [],
        };

        if ($permissions === []) {
            return;
        }

        $existing = $this->permissions()->pluck('nombre')->all();
        $missing = array_values(array_diff($permissions, $existing));

        if ($missing === [] && $existing !== []) {
            return;
        }

        foreach ($missing as $name) {
            $case = PermissionEnum::tryFrom($name);

            if (! $case) {
                continue;
            }

            Permission::query()->updateOrCreate(
                ['nombre' => $case->value],
                [
                    'descripcion' => $case->label(),
                    'grupo' => $case->group(),
                ]
            );
        }

        if ($existing === []) {
            $this->syncPermissionsByName($permissions);

            return;
        }

        $ids = Permission::query()
            ->whereIn('nombre', $missing)
            ->pluck('id')
            ->all();

        if ($ids !== []) {
            $this->permissions()->syncWithoutDetaching($ids);
        }
    }
}
