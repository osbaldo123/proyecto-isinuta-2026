<?php

namespace App\Http\Requests;

use App\Enums\SouthAmericaPhoneCountry;
use App\Models\Role;
use App\Models\User;
use App\Services\UserManagementService;
use App\Support\UserIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Validación centralizada para edición de usuarios de consulta (HU-01).
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $target = User::query()->find($this->route('id'));

        if (! $user || ! $target) {
            return false;
        }

        $service = app(UserManagementService::class);

        try {
            $service->assertCanManageTarget($user, $target);
        } catch (HttpException) {
            return false;
        }

        return $service->canManageUsers($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = User::query()->with('roles')->findOrFail($this->route('id'));
        $actor = $this->user();
        $service = app(UserManagementService::class);
        $esInstitucional = $service->isInstitutionalAccount($target);
        $rolActual = (string) ($target->rolesNombres()[0] ?? $target->role ?? '');
        $assignableRoles = $actor
            ? $service->assignableRoleValuesFor($actor)
            : [Role::CONSULTA_PAGOS_MULTAS];

        if ($rolActual !== '') {
            $assignableRoles[] = $rolActual;
        }

        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'ci' => [
                'required',
                'digits:8',
                Rule::unique('users', 'ci')->ignore($target->id),
            ],
            'telefono_codigo' => ['required', 'string', Rule::in(array_column(SouthAmericaPhoneCountry::options(), 'value'))],
            'telefono' => [
                'required',
                'string',
                'regex:/^[0-9]{6,12}$/',
                Rule::unique('users', 'telefono')
                    ->where(fn ($query) => $query->where('telefono_codigo', $this->input('telefono_codigo')))
                    ->ignore($target->id),
            ],
            'rol' => $esInstitucional
                ? ['required', 'string', Rule::in([$rolActual])]
                : ['required', 'string', 'max:80', Rule::in([...$assignableRoles, '__otro__'])],
            'rol_nuevo' => ['nullable', 'string', 'max:80', 'required_if:rol,__otro__'],
            'estado' => ['required', Rule::in(['Activo', 'Inactivo'])],
            'fecha_ingreso' => ['nullable', 'date'],
            'fecha_final' => [
                Rule::requiredIf(fn (): bool => $this->input('estado') === 'Inactivo'),
                'nullable',
                'date',
                'after_or_equal:fecha_ingreso',
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombres' => 'nombre',
            'apellidos' => 'apellido',
            'email' => 'correo electrónico',
            'ci' => 'número de CI',
            'telefono_codigo' => 'código de país',
            'telefono' => 'teléfono',
            'rol' => 'rol',
            'estado' => 'estado',
            'fecha_ingreso' => 'fecha de ingreso',
            'fecha_final' => 'fecha final',
            'password' => 'contraseña',
            'password_confirmation' => 'confirmación de contraseña',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ci.required' => 'El número de CI es obligatorio.',
            'ci.digits' => 'El CI debe tener exactamente 8 dígitos.',
            'ci.unique' => 'Ya existe otro usuario con este número de CI.',
            'telefono_codigo.in' => 'Seleccione un código de país válido de Sudamérica.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.regex' => 'El teléfono debe contener entre 6 y 12 dígitos.',
            'telefono.unique' => 'Ya existe otro usuario con este número de teléfono.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $estado = $this->input('estado');
        $target = User::query()->with('roles')->find($this->route('id'));
        $service = app(UserManagementService::class);

        $payload = [
            'ci' => preg_replace('/\D/', '', (string) $this->input('ci')),
            'telefono' => preg_replace('/\D/', '', (string) $this->input('telefono')),
            'fecha_final' => $estado === 'Inactivo' ? $this->input('fecha_final') : null,
        ];

        if ($target && $service->isInstitutionalAccount($target)) {
            $payload['rol'] = (string) ($target->rolesNombres()[0] ?? $target->role ?? '');
        }

        $this->merge($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function safeUserAttributes(): array
    {
        $ci = $this->validated('ci');

        $attributes = [
            'name' => UserIdentity::buildNombreCompleto(
                $this->validated('nombres'),
                $this->validated('apellidos'),
            ),
            'email' => $this->validated('email'),
            'ci' => $ci,
            'telefono_codigo' => $this->validated('telefono_codigo'),
            'telefono' => $this->validated('telefono'),
        ];

        if (! empty($this->validated('password'))) {
            $attributes['password'] = $this->validated('password');
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function privilegedAttributes(): array
    {
        $estadoActivo = $this->validated('estado') === 'Activo';

        $attributes = [
            'role' => $this->validated('rol'),
            'is_active' => $estadoActivo,
        ];

        if ($this->filled('fecha_ingreso')) {
            $attributes['fecha_ingreso'] = $this->validated('fecha_ingreso');
        }

        $attributes['fecha_final'] = $estadoActivo
            ? null
            : ($this->validated('fecha_final') ?? now()->toDateString());

        return $attributes;
    }

    /**
     * @deprecated Use safeUserAttributes() + privilegedAttributes()
     *
     * @return array<string, mixed>
     */
    public function userAttributes(): array
    {
        return array_merge($this->safeUserAttributes(), $this->privilegedAttributes());
    }
}
