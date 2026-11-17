<?php

namespace App\Http\Requests;

use App\Models\Afiliado;
use App\Models\LoteAfiliado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validación de la transferencia (cesión) de un lote a otra persona.
 *
 * Si el CI ya está registrado, el lote se asigna a esa persona y no se piden sus nombres.
 * Si el CI es nuevo, se registra a la persona con nombres, apellidos y teléfono.
 */
class TransferirLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $esPersonaNueva = ! $this->personaExistente();

        return [
            'ci' => ['required', 'digits:8'],
            'nombres' => [$esPersonaNueva ? 'required' : 'nullable', 'string', 'max:32'],
            'apellidos' => [$esPersonaNueva ? 'required' : 'nullable', 'string', 'max:32'],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9]{6,15}$/'],
            'fecha_cesion' => ['required', 'date', 'before_or_equal:today'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
            'documento_respaldo' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ci.required' => 'Debe ingresar el CI del nuevo titular.',
            'ci.digits' => 'El CI debe tener exactamente 8 dígitos.',
            'nombres.required' => 'Debe ingresar los nombres del nuevo titular.',
            'apellidos.required' => 'Debe ingresar los apellidos del nuevo titular.',
            'telefono.regex' => 'El teléfono debe contener solo dígitos (6 a 15).',
            'fecha_cesion.required' => 'Debe indicar la fecha de la transferencia.',
            'fecha_cesion.date' => 'La fecha de la transferencia no es válida.',
            'fecha_cesion.before_or_equal' => 'La fecha de la transferencia no puede ser futura.',
            'motivo.required' => 'Debe indicar el motivo de la transferencia.',
            'motivo.min' => 'Describa el motivo con al menos 5 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
            'documento_respaldo.max' => 'El documento de respaldo no puede superar los 120 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $telefono = preg_replace('/\D/', '', (string) $this->input('telefono'));

        $this->merge([
            'ci' => preg_replace('/\D/', '', (string) $this->input('ci')),
            'telefono' => $telefono !== '' ? $telefono : null,
            'nombres' => trim((string) $this->input('nombres')) ?: null,
            'apellidos' => trim((string) $this->input('apellidos')) ?: null,
            'motivo' => trim((string) $this->input('motivo')),
            'documento_respaldo' => trim((string) $this->input('documento_respaldo')) ?: null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lote = LoteAfiliado::query()->find((int) $this->route('id'));
            $ci = (string) $this->input('ci');
            $persona = $this->personaExistente();

            if ($lote && $persona && $persona->id === $lote->afiliado_id) {
                $validator->errors()->add('ci', 'Esa persona ya es la titular de este lote. Ingrese el CI de otra persona.');

                return;
            }

            if (LoteAfiliado::telefonoUsadoPorOtraPersona($this->input('telefono'), $persona?->id, $ci)) {
                $validator->errors()->add('telefono', 'Ese teléfono ya está registrado en otro afiliado.');
            }

            if (! $persona) {
                $conflicto = Afiliado::conflictoNombreDistintoCi(
                    (string) $this->input('nombres'),
                    (string) $this->input('apellidos'),
                    $ci,
                );

                if ($conflicto) {
                    $validator->errors()->add(
                        'nombres',
                        "Ya existe el afiliado {$conflicto->nombre_completo} con CI {$conflicto->ciCompletoDisplay()}. Si es la misma persona, use ese CI.",
                    );
                }
            }
        });
    }

    private function personaExistente(): ?Afiliado
    {
        $ci = preg_replace('/\D/', '', (string) $this->input('ci'));

        if (strlen($ci) !== 8) {
            return null;
        }

        return Afiliado::query()->where('ci', $ci)->orderBy('id')->first();
    }
}
