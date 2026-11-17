<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrabajoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tipo' => trim((string) $this->input('tipo', '')),
            'descripcion' => trim((string) $this->input('descripcion', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'otb_id' => ['required', 'integer', 'exists:otbs,id'],
            'tipo' => ['required', 'string', 'max:100'],
            'descripcion' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'monto_multa' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'otb_id.required' => 'Seleccione la OTB.',
            'tipo.required' => 'Escriba el tipo de trabajo.',
            'descripcion.required' => 'Escriba el motivo del trabajo.',
            'fecha_inicio.required' => 'Indique la fecha de inicio.',
            'monto_multa.required' => 'Indique el monto de la multa.',
            'monto_multa.min' => 'El monto de la multa debe ser mayor a cero.',
        ];
    }
}
