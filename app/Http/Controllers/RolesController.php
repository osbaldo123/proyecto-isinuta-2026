<?php

namespace App\Http\Controllers;

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RolesController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = trim((string) $request->query('q', ''));
        $actor = $request->user();

        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->when($actor?->hasRole(Role::PRESIDENTE), function ($query): void {
                $query->whereNotIn('nombre', Role::hiddenFromPresidenteCatalog());
            })
            ->when($busqueda !== '', function ($query) use ($busqueda): void {
                $query->where(function ($q) use ($busqueda): void {
                    $q->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('descripcion', 'like', "%{$busqueda}%")
                        ->orWhereHas('permissions', function ($permissions) use ($busqueda): void {
                            $permissions->where('nombre', 'like', "%{$busqueda}%")
                                ->orWhere('descripcion', 'like', "%{$busqueda}%")
                                ->orWhere('grupo', 'like', "%{$busqueda}%");
                        });
                });
            })
            ->orderBy('nombre')
            ->get()
            ->map(function (Role $role) {
                return [
                    'id' => $role->id,
                    'nombre' => $role->nombre,
                    'nombre_label' => $role->displayName(),
                    'descripcion' => $role->descripcion,
                    'usuarios_count' => $role->users_count,
                    'activo' => (bool) $role->activo,
                    'es_sistema' => $role->isInstitutional(),
                    'puede_editar' => ! $role->isLockedFromEdit(),
                    'puede_dar_baja' => ! $role->isLockedFromDeactivation(),
                    'puede_eliminar' => ! $role->isLockedFromDeletion(),
                    'funciones_count' => $role->permissions->unique('id')->count(),
                ];
            });

        return Inertia::render('roles/Index', [
            'roles' => $roles,
            'total' => $roles->count(),
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(): Response
    {
        $permissions = Permission::all()->map(function ($perm) {
            return [
                'id' => $perm->id,
                'nombre' => $perm->nombre,
                'descripcion' => $perm->descripcion,
                'grupo' => $perm->grupo,
            ];
        });

        return Inertia::render('roles/Create', ['permissions' => $permissions]);
    }

    public function store(Request $request): RedirectResponse
    {
        $nombreTecnico = Str::slug((string) $request->input('nombre'), '_');

        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:50',
            ],
            'descripcion' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($nombreTecnico === '') {
            return back()->withErrors(['nombre' => 'Escriba un nombre válido para el rol.'])->withInput();
        }

        if (in_array($nombreTecnico, Role::reservedRoleNames(), true)) {
            return back()->withErrors(['nombre' => 'Ese nombre de rol está reservado del sistema.'])->withInput();
        }

        if (Role::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombreTecnico)])->exists()) {
            return back()->withErrors(['nombre' => 'Ya existe un rol con ese nombre.'])->withInput();
        }

        $role = Role::create([
            'nombre' => $nombreTecnico,
            'descripcion' => $validated['descripcion'] ?? null,
            'activo' => true,
        ]);

        if (! empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return redirect()->route('roles.show', $role->id)->with('success', 'Rol creado. Ya puede asignarlo al crear o editar un usuario.');
    }

    public function show(Request $request, int $id): Response
    {
        $role = Role::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('grupo')->orderBy('descripcion')])
            ->withCount('users')
            ->findOrFail($id);

        $this->assertRoleVisibleToActor($request->user(), $role);

        $actor = $request->user();
        $puedeGestionar = $actor?->hasPermission(PermissionEnum::GestionarRoles->value) === true;
        $puedeEditar = $puedeGestionar && ! $role->isLockedFromEdit();
        $puedeDarBaja = $puedeGestionar && ! $role->isLockedFromDeactivation();
        $puedeEliminar = $puedeGestionar && ! $role->isLockedFromDeletion();

        $usuarios = User::query()
            ->where(function ($query) use ($role): void {
                $query->where('role', $role->nombre)
                    ->orWhereHas('roles', fn ($roles) => $roles->where('roles.id', $role->id));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'activo' => (bool) $user->is_active,
            ])
            ->values()
            ->all();

        return Inertia::render('roles/Show', [
            'role' => $this->roleDetailPayload($role),
            'usuarios' => $usuarios,
            'puede_editar' => $puedeEditar,
            'puede_dar_baja' => $puedeDarBaja,
            'puede_eliminar' => $puedeEliminar,
        ]);
    }

    public function edit(Request $request, int $id): Response|RedirectResponse
    {
        $role = Role::with('permissions')->findOrFail($id);

        $this->assertRoleVisibleToActor($request->user(), $role);

        if ($role->isLockedFromEdit()) {
            return redirect()->route('roles.show', $role->id)->with(
                'error',
                'Este rol no puede editarse.'
            );
        }

        $permissions = Permission::all()->map(function ($perm) {
            return [
                'id' => $perm->id,
                'nombre' => $perm->nombre,
                'descripcion' => $perm->descripcion,
                'grupo' => $perm->grupo,
            ];
        });

        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return Inertia::render('roles/Edit', [
            'role' => [
                'id' => $role->id,
                'nombre' => $role->nombre,
                'nombre_label' => $role->displayName(),
                'descripcion' => $role->descripcion,
                'es_sistema' => $role->isInstitutional(),
            ],
            'permissions' => $permissions,
            'role_permissions' => $rolePermissions,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $role = Role::findOrFail($id);

        $this->assertRoleVisibleToActor($request->user(), $role);

        if ($role->isLockedFromEdit()) {
            return redirect()->route('roles.show', $role->id)->with(
                'error',
                'Este rol no puede editarse.'
            );
        }

        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:50',
                Rule::unique('roles', 'nombre')->ignore($id),
                Rule::when(
                    ! $role->isInstitutional(),
                    Rule::notIn(Role::reservedRoleNames()),
                ),
            ],
            'descripcion' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $updateData = [
            'descripcion' => $validated['descripcion'] ?? null,
        ];

        if ($role->isInstitutional()) {
            $updateData['nombre'] = $role->nombre;
        } else {
            $nombreTecnico = Str::slug((string) $validated['nombre'], '_');

            if ($nombreTecnico === '' || in_array($nombreTecnico, Role::reservedRoleNames(), true)) {
                return back()->withErrors(['nombre' => 'Nombre de rol no válido o reservado.'])->withInput();
            }

            $duplicado = Role::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombreTecnico)])
                ->where('id', '!=', $role->id)
                ->exists();

            if ($duplicado) {
                return back()->withErrors(['nombre' => 'Ya existe un rol con ese nombre.'])->withInput();
            }

            $updateData['nombre'] = $nombreTecnico;
        }

        $role->update($updateData);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('roles.show', $role->id)->with('success', 'Rol actualizado exitosamente');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $role = Role::findOrFail($id);

        $this->assertRoleVisibleToActor($request->user(), $role);

        if ($role->isLockedFromDeletion()) {
            return redirect()->route('roles.show', $role->id)->with(
                'error',
                'Los roles del sistema no se eliminan. Puede editarlos o darlos de baja.'
            );
        }

        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Rol eliminado exitosamente');
    }

    public function toggleEstado(Request $request, int $id): RedirectResponse
    {
        $role = Role::query()->findOrFail($id);

        $this->assertRoleVisibleToActor($request->user(), $role);

        if ($role->isLockedFromDeactivation()) {
            return redirect()->back()->with(
                'error',
                'Este rol no puede darse de baja.'
            );
        }

        $role->update([
            'activo' => ! $role->activo,
        ]);

        $mensaje = $role->activo
            ? 'Rol reactivado exitosamente'
            : 'Rol dado de baja exitosamente';

        return redirect()->back()->with('success', $mensaje);
    }

    private function assertRoleVisibleToActor(?User $actor, Role $role): void
    {
        if ($actor?->hasRole(Role::PRESIDENTE)
            && in_array($role->nombre, Role::hiddenFromPresidenteCatalog(), true)) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function roleDetailPayload(Role $role): array
    {
        $permisos = $role->permissions
            ->unique('id')
            ->values()
            ->map(fn (Permission $perm): array => [
                'id' => $perm->id,
                'nombre' => $perm->nombre,
                'descripcion' => $perm->descripcion,
                'grupo' => $perm->grupo,
            ])
            ->all();

        return [
            'id' => $role->id,
            'nombre' => $role->nombre,
            'nombre_label' => $role->displayName(),
            'descripcion' => $role->descripcion,
            'usuarios_count' => $role->users_count,
            'activo' => (bool) $role->activo,
            'es_sistema' => $role->isInstitutional(),
            'permisos' => $permisos,
            'protegido' => $role->isProtectedRole(),
            'protegido_eliminacion' => $role->isLockedFromDeletion(),
        ];
    }
}
