<?php

namespace App\Http\Requests;

use App\Models\Afiliado;
use App\Models\LoteAfiliado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validación estricta para edición de afiliados (HU-02).
 */
class UpdateAfiliadoRequest extends FormRequest
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
            'nombres' => ['required', 'string', 'max:32'],
            'apellidos' => ['required', 'string', 'max:32'],
            'ci' => ['required', 'digits:8'],
            'telefono' => [
                'nullable',
                'string',
                'regex:/^[0-9]{6,15}$/',
            ],
            'direccion' => ['nullable', 'string', 'max:255'],
            'otb_id' => ['required', 'integer', Rule::exists('otbs', 'id')->where(fn ($q) => $q->where('activa', true))],
            'numero_lote' => ['required', 'string', 'max:30', 'regex:/^\d+$/'],
            'fecha_afiliacion' => ['required', 'date'],
            'forma_pago_afiliacion' => ['required', Rule::in([Afiliado::FORMA_CONTADO, Afiliado::FORMA_PLAZO])],
            'tipos_afiliacion' => ['nullable', 'array'],
            'tipos_afiliacion.*' => ['integer', 'distinct', Rule::exists('tipos_afiliacion', 'id')->where(fn ($q) => $q->where('activo', true))],
            'monto_afiliacion_agua' => ['nullable', 'numeric', 'min:0'],
            'monto_afiliacion_alcantarillado' => ['nullable', 'numeric', 'min:0'],
            'monto_afiliacion' => ['nullable', 'numeric', 'min:0'],
            'saldo_afiliacion_agua' => ['nullable', 'numeric', 'min:0'],
            'saldo_afiliacion_alcantarillado' => ['nullable', 'numeric', 'min:0'],
            'saldo_afiliacion' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', Rule::in([
                Afiliado::ESTADO_ACTIVO,
                Afiliado::ESTADO_CORTADO,
                Afiliado::ESTADO_BAJA,
            ])],
            'observaciones' => ['nullable', 'string'],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->where(fn ($q) => $q->where('activo', true))],
            'servicios' => ['nullable', 'array'],
            'servicios.*' => ['integer', 'distinct', Rule::exists('servicios', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombres.required' => 'Debe ingresar los nombres del afiliado.',
            'apellidos.required' => 'Debe ingresar los apellidos del afiliado.',
            'ci.required' => 'Debe ingresar el número de CI.',
            'ci.digits' => 'El CI debe tener exactamente 8 dígitos.',
            'numero_lote.duplicado' => 'Este afiliado ya tiene una propiedad con ese número de lote. Use un lote distinto.',
            'numero_lote.duplicado_global' => 'Ya existe un lote con ese número. Use otro número distinto.',
            'direccion.duplicado' => 'Ya existe un lote con la misma OTB, dirección y número. Use otra combinación.',
            'nombres.duplicado' => 'Ya existe un afiliado con ese nombre y otro CI. Si es la misma persona, use el CI existente y agregue otro lote.',
            'telefono.regex' => 'El teléfono debe contener solo dígitos (6 a 15).',
            'telefono.ocupado' => 'Ese teléfono ya está registrado en otro afiliado. En los lotes de esta misma persona puede repetir el número o usar uno distinto.',
            'otb_id.required' => 'Debe seleccionar la OTB o sindicato.',
            'otb_id.exists' => 'La OTB seleccionada no existe.',
            'numero_lote.required' => 'Debe ingresar el número de lote de la propiedad.',
            'numero_lote.regex' => 'El número de lote debe ser numérico (sin letras ni guiones).',
            'servicios.*.distinct' => 'No se puede asignar el mismo servicio más de una vez a este lote.',
            'fecha_afiliacion.required' => 'Debe indicar la fecha de afiliación.',
            'fecha_afiliacion.date' => 'La fecha de afiliación no es válida.',
            'forma_pago_afiliacion.required' => 'Debe indicar la forma de pago.',
            'monto_afiliacion_agua.required' => 'Debe indicar el monto de afiliación de agua potable.',
            'monto_afiliacion_alcantarillado.required' => 'Debe indicar el monto de afiliación de alcantarillado.',
            'categoria_id.required' => 'Debe seleccionar la categoría de la propiedad.',
            'categoria_id.exists' => 'La categoría seleccionada no es válida.',
            'estado.required' => 'Debe indicar el estado del afiliado.',
            'estado.in' => 'El estado debe ser activo, cortado o baja.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombres' => 'nombres',
            'apellidos' => 'apellidos',
            'ci' => 'número de CI',
            'telefono' => 'teléfono',
            'direccion' => 'dirección',
            'otb_id' => 'OTB',
            'numero_lote' => 'número de lote',
            'fecha_afiliacion' => 'fecha de afiliación',
            'forma_pago_afiliacion' => 'forma de pago',
            'monto_afiliacion_agua' => 'afiliación de agua potable',
            'monto_afiliacion_alcantarillado' => 'afiliación de alcantarillado',
            'monto_afiliacion' => 'monto de afiliación',
            'saldo_afiliacion' => 'saldo pendiente',
            'categoria_id' => 'categoría de propiedad',
            'estado' => 'estado',
        ];
    }

    protected function prepareForValidation(): void
    {
        $telefono = preg_replace('/\D/', '', (string) $this->input('telefono'));
        $otbId = $this->input('otb_id');

        $this->merge([
            'ci' => preg_replace('/\D/', '', (string) $this->input('ci')),
            'telefono' => $telefono !== '' ? $telefono : null,
            'otb_id' => ($otbId === '' || $otbId === null) ? null : (int) $otbId,
            'numero_lote' => $this->normalizedLote($this->input('numero_lote')),
            'direccion' => trim((string) $this->input('direccion')) ?: null,
            'observaciones' => trim((string) $this->input('observaciones')) ?: null,
            'categoria_id' => ($this->input('categoria_id') === '' || $this->input('categoria_id') === null)
                ? null
                : (int) $this->input('categoria_id'),
            'tipos_afiliacion' => collect($this->input('tipos_afiliacion', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
        ]);

        $montoAgua = (float) ($this->input('monto_afiliacion_agua') ?? 0);
        $montoAlc = (float) ($this->input('monto_afiliacion_alcantarillado') ?? 0);
        $montoAfiliacion = round($montoAgua + $montoAlc, 2);

        $formaPago = (string) $this->input('forma_pago_afiliacion');
        if ($formaPago === Afiliado::FORMA_CONTADO) {
            $saldoAgua = 0.0;
            $saldoAlc = 0.0;
        } else {
            $saldoAgua = min(max(0, (float) ($this->input('saldo_afiliacion_agua') ?? 0)), max(0, $montoAgua));
            $saldoAlc = min(max(0, (float) ($this->input('saldo_afiliacion_alcantarillado') ?? 0)), max(0, $montoAlc));

            // Compatibilidad: si solo llega saldo_afiliacion total, repartir.
            $saldoTotalInput = $this->input('saldo_afiliacion');
            if ($saldoTotalInput !== null && ! $this->filled('saldo_afiliacion_agua') && ! $this->filled('saldo_afiliacion_alcantarillado')) {
                $saldo = min(max(0, (float) $saldoTotalInput), $montoAfiliacion);
                if ($montoAgua > 0 && $montoAlc <= 0) {
                    $saldoAgua = $saldo;
                    $saldoAlc = 0.0;
                } elseif ($montoAlc > 0 && $montoAgua <= 0) {
                    $saldoAgua = 0.0;
                    $saldoAlc = $saldo;
                } elseif ($montoAfiliacion > 0) {
                    $pagado = round($montoAfiliacion - $saldo, 2);
                    $ratio = $pagado / $montoAfiliacion;
                    $abonoAgua = round($montoAgua * $ratio, 2);
                    $saldoAgua = round($montoAgua - $abonoAgua, 2);
                    $saldoAlc = round($saldo - $saldoAgua, 2);
                }
            }
        }

        $this->merge([
            'monto_afiliacion_agua' => $montoAgua,
            'monto_afiliacion_alcantarillado' => $montoAlc,
            'monto_afiliacion' => $montoAfiliacion,
            'saldo_afiliacion_agua' => round($saldoAgua, 2),
            'saldo_afiliacion_alcantarillado' => round($saldoAlc, 2),
            'saldo_afiliacion' => round($saldoAgua + $saldoAlc, 2),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $routeId = (int) $this->route('id');
            [$afiliadoActual, $loteActual] = $this->resolveEditingContext($routeId);

            if (! $afiliadoActual) {
                return;
            }

            $ci = (string) $this->input('ci');
            $numeroLote = $this->normalizedLote($this->input('numero_lote'));
            $otbId = (int) $this->input('otb_id');
            $direccion = $this->input('direccion');

            if (Afiliado::query()->where('ci', $ci)->where('id', '!=', $afiliadoActual->id)->exists()) {
                $validator->errors()->add(
                    'ci',
                    'Ya existe otro afiliado registrado con ese número de CI.'
                );
            }

            $duplicado = LoteAfiliado::query()
                ->where('afiliado_id', $afiliadoActual->id)
                ->where('numero_lote', $numeroLote)
                ->when($loteActual, fn ($q) => $q->where('id', '!=', $loteActual->id))
                ->exists();

            if ($duplicado) {
                $validator->errors()->add(
                    'numero_lote',
                    'Este afiliado ya tiene una propiedad con ese número de lote. Use un lote distinto (1, 2, 3…).'
                );
            }

            $numeroLoteCambiado = ! $loteActual
                || (string) $loteActual->numero_lote !== $numeroLote;

            if (
                $numeroLoteCambiado
                && LoteAfiliado::existeNumeroLote($numeroLote, $loteActual?->id)
            ) {
                $validator->errors()->add(
                    'numero_lote',
                    "Ya existe un lote con el número {$numeroLote}. Use otro número distinto."
                );
            }

            $direccionTexto = is_string($direccion) ? $direccion : null;
            $ubicacionCambiada = ! $loteActual
                || ! $loteActual->mismaUbicacion($otbId, $numeroLote, $direccionTexto);

            if (
                $ubicacionCambiada
                && $otbId > 0
                && filled($direccion)
                && LoteAfiliado::existeUbicacionDuplicada(
                    $otbId,
                    $numeroLote,
                    $direccionTexto,
                    $loteActual?->id,
                )
            ) {
                $validator->errors()->add(
                    'direccion',
                    'Ya existe un lote con la misma OTB, dirección y número. Use otra combinación.'
                );
            }

            if (LoteAfiliado::telefonoUsadoPorOtraPersona(
                $this->input('telefono'),
                $afiliadoActual->id,
                $ci,
            )) {
                $validator->errors()->add(
                    'telefono',
                    'Ese teléfono ya está registrado en otro afiliado. En los lotes de esta misma persona puede repetir el número o usar uno distinto.'
                );
            }

            $conflicto = Afiliado::conflictoNombreDistintoCi(
                (string) $this->input('nombres'),
                (string) $this->input('apellidos'),
                (string) $this->input('ci'),
            );

            if ($conflicto && (int) $conflicto->id !== (int) ($afiliadoActual?->id)) {
                $validator->errors()->add(
                    'nombres',
                    "Ya existe el afiliado {$conflicto->nombre_completo} con CI {$conflicto->ciCompletoDisplay()}. Si es la misma persona, use ese CI y registre otro lote."
                );
            }
        });
    }

    private function normalizedLote(mixed $value): string
    {
        $lote = preg_replace('/\D/', '', trim((string) $value));

        return $lote !== '' ? $lote : '1';
    }

    /**
     * @return array{0: Afiliado|null, 1: LoteAfiliado|null}
     */
    private function resolveEditingContext(int $routeId): array
    {
        $lote = LoteAfiliado::query()->with('afiliado')->find($routeId);

        if ($lote) {
            return [$lote->afiliado, $lote];
        }

        $afiliado = Afiliado::query()->with('lotes')->find($routeId);

        if (! $afiliado) {
            return [null, null];
        }

        $numeroLote = $this->normalizedLote($this->input('numero_lote'));
        $lotePorNumero = $afiliado->lotes->first(
            fn (LoteAfiliado $item): bool => (string) $item->numero_lote === $numeroLote,
        );

        return [$afiliado, $lotePorNumero ?? $afiliado->lotes->first()];
    }
}
