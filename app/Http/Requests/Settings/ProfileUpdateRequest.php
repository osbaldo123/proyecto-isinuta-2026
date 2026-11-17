<?php

namespace App\Http\Requests\Settings;

use App\Enums\SouthAmericaPhoneCountry;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($userId),
            ],
            'ci' => [
                'required',
                'digits:8',
                Rule::unique('users', 'ci')->ignore($userId),
            ],
            'telefono_codigo' => ['required', 'string', Rule::in(array_column(SouthAmericaPhoneCountry::options(), 'value'))],
            'telefono' => [
                'required',
                'string',
                'regex:/^[0-9]{6,12}$/',
                Rule::unique('users', 'telefono')
                    ->where(fn ($query) => $query->where('telefono_codigo', $this->input('telefono_codigo')))
                    ->ignore($userId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Debe ingresar su nombre.',
            'email.required' => 'Debe ingresar su correo electrónico.',
            'email.unique' => 'Ese correo ya está registrado.',
            'ci.required' => 'Debe ingresar su CI.',
            'ci.digits' => 'El CI debe tener exactamente 8 dígitos.',
            'ci.unique' => 'Ese CI ya está registrado en otro usuario.',
            'telefono.required' => 'Debe ingresar su teléfono.',
            'telefono.regex' => 'El teléfono debe tener entre 6 y 12 dígitos.',
            'telefono.unique' => 'Ese teléfono ya está registrado en otro usuario.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $telefono = preg_replace('/\D/', '', (string) $this->input('telefono'));

        $this->merge([
            'ci' => preg_replace('/\D/', '', (string) $this->input('ci')),
            'telefono' => $telefono !== '' ? $telefono : null,
            'telefono_codigo' => trim((string) $this->input('telefono_codigo', '+591')) ?: '+591',
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
