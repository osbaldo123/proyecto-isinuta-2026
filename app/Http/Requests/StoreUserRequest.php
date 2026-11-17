<?php

namespace App\Http\Requests;

use App\Enums\SouthAmericaPhoneCountry;
use App\Models\Role;
use App\Services\UserManagementService;
use App\Support\UserIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación centralizada para alta de usuarios de consulta (HU-01).
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && app(UserManagementService::class)->canManageUsers($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $actor = $this->user();
        $assignableRoles = $actor
            ? app(UserManagementService::class)->assignableRoleValuesFor($actor)
            : [Role::CONSULTA_PAGOS_MULTAS];

        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'ci' => ['required', 'digits:8', 'unique:users,ci'],
            'telefono_codigo' => ['required', 'string', Rule::in(array_column(SouthAmericaPhoneCountry::options(), 'value'))],
            'telefono' => [
                'required',
                'string',
                'regex:/^[0-9]{6,12}$/',
                Rule::unique('users', 'telefono')->where(
                    fn ($query) => $query->where('telefono_codigo', $this->input('telefono_codigo'))
                ),
            ],
            'rol' => ['required', 'string', 'max:80', Rule::in([...$assignableRoles, '__otro__'])],
            'rol_nuevo' => ['nullable', 'string', 'max:80', 'required_if:rol,__otro__'],
            'estado' => ['required', Rule::in(['Activo', 'Inactivo'])],
            'fecha_ingreso' => ['nullable', 'date'],
            'fecha_final' => [
                Rule::requiredIf(fn (): bool => $this->input('estado') === 'Inactivo'),
                'nullable',
                'date',
                'after_or_equal:fecha_ingreso',
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
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
            'ci.unique' => 'Ya existe un usuario registrado con este número de CI.',
            'telefono_codigo.in' => 'Seleccione un código de país válido de Sudamérica.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.regex' => 'El teléfono debe contener entre 6 y 12 dígitos.',
            'telefono.unique' => 'Ya existe un usuario con este número de teléfono.',
            'rol.in' => 'El rol seleccionado no está disponible para asignar.',
            'rol_nuevo.required_if' => 'Escriba el nombre del nuevo rol.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $estado = $this->input('estado');

        $this->merge([
            'ci' => preg_replace('/\D/', '', (string) $this->input('ci')),
            'telefono' => preg_replace('/\D/', '', (string) $this->input('telefono')),
            'fecha_final' => $estado === 'Inactivo' ? $this->input('fecha_final') : null,
        ]);
    }

    /**
     * Atributos seguros para mass assignment (sin role / is_active / is_system_account).
     *
     * @return array<string, mixed>
     */
    public function safeUserAttributes(): array
    {
        $ci = $this->validated('ci');

        return [
            'name' => UserIdentity::buildNombreCompleto(
                $this->validated('nombres'),
                $this->validated('apellidos'),
            ),
            'email' => $this->validated('email'),
            'ci' => $ci,
            'telefono_codigo' => $this->validated('telefono_codigo'),
            'telefono' => $this->validated('telefono'),
            'password' => $this->validated('password'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function privilegedAttributes(): array
    {
        $estadoActivo = $this->validated('estado') === 'Activo';
        $fechaIngreso = $this->validated('fecha_ingreso') ?? now()->toDateString();

        return [
            'role' => $this->validated('rol'),
            'is_active' => $estadoActivo,
            'is_system_account' => false,
            'fecha_ingreso' => $fechaIngreso,
            'fecha_final' => $estadoActivo ? null : ($this->validated('fecha_final') ?? now()->toDateString()),
        ];
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
