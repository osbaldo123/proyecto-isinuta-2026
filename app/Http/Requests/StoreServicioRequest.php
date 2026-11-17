<?php

namespace App\Http\Requests;

use App\Models\Servicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServicioRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'string', 'max:50'],
            'lineas' => ['required_without:tarifas', 'array', 'min:1'],
            'lineas.*.categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'lineas.*.categoria_nombre' => ['required_with:lineas', 'string', 'max:100'],
            'lineas.*.monto' => ['required_with:lineas', 'numeric', 'min:0', 'max:99999'],
            'lineas.*.estado' => ['required_with:lineas', Rule::in(['Activa', 'Inactiva'])],
            'tarifas' => ['required_without:lineas', 'array', 'min:1'],
            'tarifas.*' => ['required', 'numeric', 'min:0', 'max:99999'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'categoria_nombre' => ['nullable', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Debe indicar el nombre del servicio.',
            'tipo.required' => 'Debe seleccionar el tipo de servicio.',
            'lineas.required_without' => 'Debe agregar al menos una línea de categoría y tarifa.',
            'lineas.min' => 'Debe agregar al menos una línea de categoría y tarifa.',
            'lineas.*.categoria_nombre.required_with' => 'Debe indicar la categoría en cada línea.',
            'lineas.*.monto.required_with' => 'Debe indicar la tarifa en cada línea.',
            'lineas.*.estado.in' => 'El estado de la línea debe ser Activa o Inactiva.',
            'tarifas.required_without' => 'Indique la tarifa mensual de cada categoría.',
            'categoria_id.exists' => 'La categoría seleccionada no es válida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tipo = trim((string) $this->input('tipo', ''));

        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'tipo' => Servicio::normalizarTipo($tipo !== '' ? $tipo : 'otro'),
            'activo' => filter_var($this->input('activo', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $nombre = trim((string) $this->input('nombre'));

            if ($nombre !== '' && ! $validator->errors()->has('nombre') && Servicio::existeNombre($nombre)) {
                $validator->errors()->add('nombre', 'Ya existe un servicio con ese nombre.');
            }

            $tipo = (string) $this->input('tipo');

            if (
                in_array($tipo, Servicio::TIPOS_BASE, true)
                && ! $validator->errors()->has('tipo')
                && Servicio::query()->where('tipo', $tipo)->exists()
            ) {
                $validator->errors()->add('tipo', 'Ya existe un servicio de ese tipo.');
            }

            if (is_array($this->input('lineas'))) {
                $activas = collect($this->input('lineas', []))
                    ->filter(fn ($linea): bool => is_array($linea) && ($linea['estado'] ?? '') === 'Activa')
                    ->count();

                if ($activas < 1 && ! $validator->errors()->has('lineas')) {
                    $validator->errors()->add('lineas', 'Debe haber al menos una categoría activa.');
                }
            }
        });
    }
}
