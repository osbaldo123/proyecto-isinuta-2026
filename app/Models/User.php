<?php

namespace App\Models;

use App\Enums\Permission as PermissionEnum;
use App\Models\Concerns\HasSpanishTimestamps;
use App\Services\EmailVerificationCodeService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasSpanishTimestamps, Notifiable, TwoFactorAuthenticatable;

    /**
     * Campos privilegiados (role, is_active, is_system_account, códigos de verificación)
     * se asignan solo con forceFill() desde servicios/controladores controlados.
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'afiliado_id',
        'ci',
        'telefono_codigo',
        'telefono',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'email_verification_code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_code_expires_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'is_system_account' => 'boolean',
            'fecha_ingreso' => 'date',
            'fecha_final' => 'date',
        ];
    }

    /**
     * Envía un código de verificación (una sola vez hasta cambiar el correo).
     */
    public function sendEmailVerificationNotification(): void
    {
        app(EmailVerificationCodeService::class)->issue($this);
    }

    /**
     * @return BelongsTo<Afiliado, $this>
     */
    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(Afiliado::class);
    }

    public function isAfiliadoVinculado(): bool
    {
        return $this->afiliado_id !== null;
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps('fecha_creacion', 'fecha_actualizacion');
    }

    public function assignRole(string $roleName): void
    {
        $role = Role::query()->firstOrCreate(
            ['nombre' => $roleName],
            ['descripcion' => str($roleName)->headline()->toString()]
        );

        $this->roles()->syncWithoutDetaching([$role->id]);
        $this->forceFill(['role' => $roleName])->save();
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->rolesNombres(), true);
    }

    /**
     * @param  array<string>|string  $roles
     */
    public function hasAnyRole(array|string $roles): bool
    {
        $roles = is_array($roles) ? $roles : func_get_args();

        return collect($roles)->contains(fn (string $role): bool => $this->hasRole($role));
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN) || $this->role === Role::ADMIN;
    }

    public function isInstitutionalOperator(): bool
    {
        return $this->is_system_account && $this->hasRole(Role::SECRETARIA);
    }

    public function isPresidente(): bool
    {
        return $this->hasRole(Role::PRESIDENTE) || $this->role === Role::PRESIDENTE;
    }

    /**
     * @return array<string>
     */
    public function rolesNombres(): array
    {
        $roles = $this->relationLoaded('roles')
            ? $this->roles
            : $this->roles()->get();

        $names = $roles->pluck('nombre');

        if ($this->role) {
            $names->push($this->role);
        }

        return $names->filter()->unique()->values()->all();
    }

    public function roleDisplayLabel(): string
    {
        $primary = $this->rolesNombres()[0] ?? $this->role;

        return Role::labelFor(is_string($primary) ? $primary : null);
    }

    /**
     * @return array<string>
     */
    public function permissionsNombres(): array
    {
        if ($this->isAdmin()) {
            return PermissionEnum::valuesForAdministrador();
        }

        $this->loadMissing('roles.permissions');

        $permissions = $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('nombre'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $permissions !== [] ? $permissions : $this->defaultPermissionsForAssignedRoles();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissionsNombres(), true);
    }

    public function canAccessPanel(): bool
    {
        return $this->hasPermission(PermissionEnum::VerPanel->value);
    }

    public function syncRolesFromLegacyColumn(): void
    {
        if (! $this->role) {
            return;
        }

        $this->assignRole($this->role);
    }

    public function ensureRolesHavePermissions(): void
    {
        $this->loadMissing('roles');

        $this->roles->each->ensureDefaultPermissions();
    }

    /**
     * @return array<string>
     */
    private function defaultPermissionsForAssignedRoles(): array
    {
        return collect($this->rolesNombres())
            ->flatMap(fn (string $role): array => match ($role) {
                Role::SECRETARIA, Role::ADMIN => PermissionEnum::valuesForSecretaria(),
                Role::PRESIDENTE => PermissionEnum::valuesForPresidente(),
                Role::CONSULTA_PAGOS_MULTAS => PermissionEnum::valuesForConsultaPagosMultas(),
                Role::CAJERA, Role::OPERADOR => PermissionEnum::valuesForCajera(),
                default => [],
            })
            ->unique()
            ->values()
            ->all();
    }
}
