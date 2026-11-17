<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarListaTrabajoRequest extends FormRequest
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
        return [
            'asistencias' => ['present', 'array'],
            'asistencias.*.lote_afiliado_id' => ['required', 'integer', 'exists:lote_afiliados,id'],
            'asistencias.*.presente' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asistencias.present' => 'Envíe la lista de lotes.',
        ];
    }
}
