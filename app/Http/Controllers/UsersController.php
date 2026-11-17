<?php

namespace App\Http\Controllers;

use App\Enums\SouthAmericaPhoneCountry;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\UserManagementService;
use App\Support\UserIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    public function __construct(
        private UserManagementService $userManagement,
        private ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor && $this->userManagement->canManageUsers($actor), 403);

        $busqueda = trim((string) $request->query('q', ''));

        $usuarios = $this->userManagement->manageableUsersQuery()
            ->with('roles')
            ->when($busqueda !== '', function ($query) use ($busqueda): void {
                $estadoActivo = str_contains(mb_strtolower($busqueda), 'activ')
                    && ! str_contains(mb_strtolower($busqueda), 'inactiv');
                $estadoInactivo = str_contains(mb_strtolower($busqueda), 'inactiv');

                $query->where(function ($q) use ($busqueda, $estadoActivo, $estadoInactivo): void {
                    $q->where('name', 'like', "%{$busqueda}%")
                        ->orWhere('email', 'like', "%{$busqueda}%")
                        ->orWhere('ci', 'like', "%{$busqueda}%")
                        ->orWhere('role', 'like', "%{$busqueda}%")
                        ->orWhereHas('roles', fn ($roles) => $roles->where('nombre', 'like', "%{$busqueda}%"));

                    if ($estadoActivo) {
                        $q->orWhere('is_active', true);
                    }

                    if ($estadoInactivo) {
                        $q->orWhere('is_active', false);
                    }
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => $this->usuarioData($user));

        return Inertia::render('usuarios/Index', [
            'usuarios' => $usuarios,
            'total' => $usuarios->total(),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor && $this->userManagement->canManageUsers($actor), 403);

        return Inertia::render('usuarios/Create', $this->formProps($actor));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $this->userManagement->canManageUsers($actor), 403);

        $rol = $this->userManagement->resolveAssignableRoleName(
            $actor,
            (string) $request->input('rol'),
            $request->input('rol_nuevo'),
        );

        DB::transaction(function () use ($request, $rol): void {
            $user = User::create($request->safeUserAttributes());
            $user->forceFill([
                ...$request->privilegedAttributes(),
                'role' => $rol,
            ])->save();
            $this->syncRole($user, $rol);
        });

        $this->activityLogger->log(
            $request->user(),
            'Usuarios',
            'Creación de usuario',
        );

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function show(Request $request, int $id): Response
    {
        $actor = $request->user();
        abort_unless($actor && $this->userManagement->canManageUsers($actor), 403);

        $usuario = $this->userManagement->manageableUsersQuery()
            ->with('roles')
            ->findOrFail($id);

        $this->userManagement->assertCanManageTarget($actor, $usuario);

        $esInstitucional = $this->userManagement->isInstitutionalAccount($usuario);

        return Inertia::render('usuarios/Show', [
            'usuario' => $this->usuarioDetalleData($usuario),
            'puede_editar' => true,
            'puede_eliminar' => ! $esInstitucional && ! $usuario->is_system_account,
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $actor = $request->user();
        abort_unless($actor && $this->userManagement->canManageUsers($actor), 403);

        $usuario = $this->userManagement->manageableUsersQuery()
            ->with('roles')
            ->findOrFail($id);

        $this->userManagement->assertCanManageTarget($actor, $usuario);

        return Inertia::render('usuarios/Edit', [
            'usuario' => $this->usuarioFormData($usuario),
            ...$this->formProps($actor, $usuario->role),
        ]);
    }

    public function update(UpdateUserRequest $request, int $id): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $this->userManagement->canManageUsers($actor), 403);

        $user = $this->userManagement->manageableUsersQuery()->findOrFail($id);
        $this->userManagement->assertCanManageTarget($actor, $user);

        $rolActual = $user->rolesNombres()[0] ?? $user->role;
        $esInstitucional = $this->userManagement->isInstitutionalAccount($user);

        $rol = $esInstitucional
            ? (string) $rolActual
            : $this->userManagement->resolveAssignableRoleName(
                $actor,
                (string) $request->input('rol'),
                $request->input('rol_nuevo'),
            );

        DB::transaction(function () use ($user, $request, $rol, $esInstitucional): void {
            $user->fill($request->safeUserAttributes());
            $privileged = $request->privilegedAttributes();
            if ($esInstitucional) {
                $privileged['role'] = $rol;
            }
            $user->forceFill([
                ...$privileged,
                'role' => $rol,
            ])->save();
            $this->syncRole($user, $rol);
        });

        $this->activityLogger->log(
            $request->user(),
            'Usuarios',
            "Actualización de usuario: {$user->name}",
        );

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $this->userManagement->canManageUsers($actor), 403);

        $user = $this->userManagement->manageableUsersQuery()->findOrFail($id);
        $this->userManagement->assertCanDeleteTarget($actor, $user);

        $nombre = $user->name;
        $user->delete();

        $this->activityLogger->log(
            $request->user(),
            'Usuarios',
            "Eliminación de usuario: {$nombre}",
        );

        return redirect()->route('usuarios.index')->with('success', 'Usuario eliminado correctamente.');
    }

    public function toggleEstado(Request $request, int $id): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $this->userManagement->canManageUsers($actor), 403);

        $user = $this->userManagement->manageableUsersQuery()->findOrFail($id);
        $this->userManagement->assertCanManageTarget($actor, $user);

        $activo = ! $user->is_active;

        $user->forceFill([
            'is_active' => $activo,
            'fecha_final' => $activo ? null : now()->toDateString(),
        ])->save();

        $this->activityLogger->log(
            $request->user(),
            'Usuarios',
            ($user->is_active ? 'Activación' : 'Desactivación')." de usuario: {$user->name}",
        );

        return redirect()->route('usuarios.index')->with('success', 'Estado del usuario actualizado correctamente.');
    }

    private function syncRole(User $user, string $roleName): void
    {
        $role = $this->userManagement->findRoleByNameOrLabel($roleName)
            ?? Role::query()->firstOrCreate(
                ['nombre' => $roleName],
                [
                    'descripcion' => $this->userManagement->roleLabel($roleName),
                    'activo' => true,
                ]
            );

        $role->ensureDefaultPermissions();

        $user->roles()->sync([$role->id]);
        $user->forceFill(['role' => $role->nombre])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?User $actor = null, ?string $includeRole = null): array
    {
        $actor ??= request()->user();
        $roles = $actor
            ? $this->userManagement->assignableRolesFor($actor)
            : [];

        if (filled($includeRole) && ! collect($roles)->contains('value', $includeRole)) {
            $role = Role::query()->where('nombre', $includeRole)->first();

            if ($role) {
                $roles[] = [
                    'value' => $role->nombre,
                    'label' => $role->displayName(),
                ];
            }
        }

        return [
            'paisesTelefono' => SouthAmericaPhoneCountry::options(),
            'rolesDisponibles' => $roles,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function usuarioData(User $user): array
    {
        $roleName = $user->rolesNombres()[0] ?? $user->role ?? Role::CONSULTA_PAGOS_MULTAS;
        $esInstitucional = $this->userManagement->isInstitutionalAccount($user);
        $rolId = $user->roles->firstWhere('nombre', $roleName)?->id
            ?? Role::query()->where('nombre', $roleName)->value('id');

        return [
            'id' => $user->id,
            'nombre' => $user->name,
            'email' => $user->email,
            'rol' => $roleName,
            'rol_id' => $rolId ? (int) $rolId : null,
            'rol_label' => $this->userManagement->roleLabel($roleName),
            'telefono_completo' => $this->telefonoCompleto($user),
            'estado' => $user->is_active ? 'Activo' : 'Inactivo',
            'ingreso' => $user->fecha_ingreso?->format('Y-m-d') ?? $user->created_at?->format('Y-m-d') ?? '—',
            'fecha_final' => $user->fecha_final?->format('Y-m-d'),
            'es_institucional' => $esInstitucional,
            'puede_eliminar' => ! $esInstitucional && ! $user->is_system_account,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function usuarioDetalleData(User $user): array
    {
        $roleName = $user->rolesNombres()[0] ?? $user->role ?? Role::CONSULTA_PAGOS_MULTAS;
        $esInstitucional = $this->userManagement->isInstitutionalAccount($user);
        $rolId = $user->roles->firstWhere('nombre', $roleName)?->id
            ?? Role::query()->where('nombre', $roleName)->value('id');

        return [
            'id' => $user->id,
            'nombre' => $user->name,
            'email' => $user->email,
            'ci' => $user->ci ?? null,
            'telefono_completo' => $this->telefonoCompleto($user),
            'rol' => $roleName,
            'rol_id' => $rolId ? (int) $rolId : null,
            'rol_label' => $this->userManagement->roleLabel($roleName),
            'estado' => $user->is_active ? 'Activo' : 'Inactivo',
            'activo' => (bool) $user->is_active,
            'ingreso' => $user->fecha_ingreso?->format('Y-m-d') ?? $user->created_at?->format('Y-m-d') ?? '—',
            'fecha_final' => $user->fecha_final?->format('Y-m-d'),
            'es_institucional' => $esInstitucional,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function usuarioFormData(User $user): array
    {
        $partes = UserIdentity::splitNombreCompleto($user->name);

        $roleName = $user->rolesNombres()[0] ?? $user->role ?? Role::CONSULTA_PAGOS_MULTAS;
        $esInstitucional = $this->userManagement->isInstitutionalAccount($user);

        return [
            'id' => $user->id,
            'nombres' => $partes['nombres'],
            'apellidos' => $partes['apellidos'],
            'email' => $user->email,
            'ci' => $user->ci ?? '',
            'telefono_codigo' => $user->telefono_codigo ?? '+591',
            'telefono' => $user->telefono ?? '',
            'rol' => $roleName,
            'rol_label' => $this->userManagement->roleLabel($roleName),
            'estado' => $user->is_active ? 'Activo' : 'Inactivo',
            'fecha_ingreso' => $user->fecha_ingreso?->format('Y-m-d') ?? $user->created_at?->format('Y-m-d') ?? '',
            'fecha_final' => $user->fecha_final?->format('Y-m-d') ?? '',
            'es_institucional' => $esInstitucional,
            'rol_bloqueado' => $esInstitucional,
        ];
    }

    private function telefonoCompleto(User $user): string
    {
        if (! $user->telefono) {
            return '—';
        }

        $codigo = $user->telefono_codigo ?: '+591';

        return trim("{$codigo} {$user->telefono}");
    }
}
