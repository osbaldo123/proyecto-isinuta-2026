<?php

namespace App\Http\Requests;

use App\Enums\Permission as PermissionEnum;
use App\Models\Configuracion;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConfiguracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionEnum::GestionarConfiguracion->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            Configuracion::MULTA_TRIMESTRAL => ['required', 'numeric', 'min:0'],
            Configuracion::MESES_MULTA_TRIMESTRAL => ['required', 'integer', 'min:1', 'max:12'],
            Configuracion::MESES_CORTE_SERVICIO => ['required', 'integer', 'min:1', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            Configuracion::MULTA_TRIMESTRAL => 'monto de la multa trimestral',
            Configuracion::MESES_MULTA_TRIMESTRAL => 'meses sin pagar de la multa trimestral',
            Configuracion::MESES_CORTE_SERVICIO => 'meses para el corte del servicio',
        ];
    }
}
