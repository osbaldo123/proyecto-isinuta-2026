<?php

namespace App\Http\Requests;

use App\Models\Afiliado;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Servicio;
use App\Services\Pagos\MesesPorCobrarService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePagoRequest extends FormRequest
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
            'afiliado_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $id = (int) $value;
                    $existe = LoteAfiliado::query()->whereKey($id)->exists()
                        || Afiliado::query()->whereKey($id)->exists();

                    if (! $existe) {
                        $fail('El afiliado o lote seleccionado no existe.');
                    }
                },
            ],
            'fecha_pago' => ['required', 'date', 'before_or_equal:today'],
            'metodo_pago' => ['required', Rule::in([
                Pago::METODO_EFECTIVO,
                Pago::METODO_QR,
            ])],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.concepto' => ['required', Rule::in(PagoDetalle::conceptosPermitidos())],
            'detalles.*.descripcion' => ['nullable', 'string', 'max:255'],
            'detalles.*.monto' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.servicio_id' => ['nullable', 'integer', Rule::exists('servicios', 'id')],
            'detalles.*.multa_id' => ['nullable', 'integer', Rule::exists('multas', 'id')],
            'detalles.*.cuota_id' => ['nullable', 'integer', Rule::exists('cuotas', 'id')],
            'detalles.*.periodo' => ['nullable', 'string', 'max:20'],
            'detalles.*.meses' => ['nullable', 'integer', 'min:1', 'max:240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'detalles.required' => 'Debe agregar al menos una línea de cobro.',
            'detalles.*.monto.min' => 'Cada monto debe ser mayor a cero.',
            'detalles.*.monto.numeric' => 'El monto debe ser numérico.',
            'fecha_pago.before_or_equal' => 'La fecha de pago no puede ser posterior a hoy.',
            'detalles.*.meses.min' => 'Elija al menos un mes.',
            'detalles.*.meses.integer' => 'La cantidad de meses debe ser un número entero.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $selectorId = $this->integer('afiliado_id');
            $lote = LoteAfiliado::query()->with('afiliado')->find($selectorId);
            $afiliado = $lote?->afiliado?->conLote($lote) ?? Afiliado::query()->find($selectorId)?->conLote(
                Afiliado::query()->find($selectorId)?->lotes()->first()
            );

            if (! $afiliado) {
                return;
            }

            if ($afiliado->estado === Afiliado::ESTADO_BAJA) {
                $validator->errors()->add('afiliado_id', 'No se pueden registrar pagos para afiliados dados de baja.');

                return;
            }

            $totalAfiliacion = 0.0;
            $cuotasVistas = [];
            $multasVistas = [];
            $serviciosPorMeses = [];
            $lineasLibres = [];
            $loteId = $lote?->id;

            foreach ($this->input('detalles', []) as $index => $detalle) {
                $concepto = $detalle['concepto'] ?? null;

                if (! empty($detalle['meses'])) {
                    $this->validarLineaDeMeses($validator, $index, $detalle, $lote, $serviciosPorMeses);

                    continue;
                }

                // Dos líneas iguales irían al mismo recibo y una se perdería: se pide distinguirlas.
                if (empty($detalle['periodo']) && empty($detalle['multa_id']) && empty($detalle['cuota_id'])) {
                    $claveLibre = $concepto.'|'.mb_strtolower(trim((string) ($detalle['descripcion'] ?? '')));

                    if (isset($lineasLibres[$claveLibre])) {
                        $validator->errors()->add(
                            "detalles.{$index}.descripcion",
                            'Esa línea está repetida en este cobro. Si son dos cobros distintos, cambie la descripción.',
                        );
                    }

                    $lineasLibres[$claveLibre] = true;
                }

                if (is_string($concepto) && PagoDetalle::requierePeriodo($concepto) && empty($detalle['periodo'])) {
                    $validator->errors()->add("detalles.{$index}.periodo", 'Indique el mes correspondiente (periodo) de la cuota.');
                }

                if (is_string($concepto) && PagoDetalle::requierePeriodo($concepto) && ! empty($detalle['periodo'])) {
                    $periodo = trim((string) $detalle['periodo']);
                    $servicioId = (int) ($detalle['servicio_id'] ?? 0);
                    $claveCuota = $concepto.'|'.$servicioId.'|'.$periodo;

                    if (isset($cuotasVistas[$claveCuota])) {
                        $validator->errors()->add(
                            "detalles.{$index}.periodo",
                            'Esa cuota del mismo mes ya está en este cobro. Quite la línea duplicada.',
                        );
                    } else {
                        $cuotasVistas[$claveCuota] = true;

                        $yaPagada = PagoDetalle::query()
                            ->where('concepto', $concepto)
                            ->where('periodo', $periodo)
                            ->when($servicioId > 0, fn ($q) => $q->where('servicio_id', $servicioId))
                            ->whereHas('pago', function ($q) use ($loteId, $afiliado): void {
                                $q->where('estado', Pago::ESTADO_EMITIDO);
                                if ($loteId) {
                                    $q->where('lote_afiliado_id', $loteId);
                                } else {
                                    $q->where('afiliado_id', $afiliado->id);
                                }
                            })
                            ->exists();

                        if ($yaPagada) {
                            $validator->errors()->add(
                                "detalles.{$index}.periodo",
                                'Ese mes ya tiene un recibo emitido para este lote y servicio.',
                            );
                        }
                    }
                }

                if (! empty($detalle['multa_id'])) {
                    $multaId = (int) $detalle['multa_id'];

                    if (isset($multasVistas[$multaId])) {
                        $validator->errors()->add(
                            "detalles.{$index}.multa_id",
                            'Esa multa ya está en este cobro.',
                        );
                    } else {
                        $multasVistas[$multaId] = true;
                    }

                    $multa = Multa::query()->find($multaId);

                    if (
                        ! $multa
                        || $multa->afiliado_id !== $afiliado->id
                        || $multa->estado !== Multa::ESTADO_PENDIENTE
                    ) {
                        $validator->errors()->add(
                            "detalles.{$index}.multa_id",
                            'La multa seleccionada no está pendiente o no pertenece al afiliado.',
                        );
                    } elseif ($loteId && $multa->lote_afiliado_id && (int) $multa->lote_afiliado_id !== (int) $loteId) {
                        $validator->errors()->add(
                            "detalles.{$index}.multa_id",
                            'La multa corresponde a otro lote. Cóbrela en el recibo de ese lote.',
                        );
                    } else {
                        $saldoMulta = $multa->saldo !== null ? (float) $multa->saldo : (float) $multa->monto;
                        if ((float) ($detalle['monto'] ?? 0) > $saldoMulta + 0.001) {
                            $validator->errors()->add(
                                "detalles.{$index}.monto",
                                'El monto cobrado no puede superar el saldo de la multa seleccionada.',
                            );
                        }
                    }
                }

                $cuota = null;
                if (! empty($detalle['cuota_id'])) {
                    $cuota = Cuota::query()->find((int) $detalle['cuota_id']);
                    if (! $cuota || ($loteId && (int) $cuota->lote_afiliado_id !== (int) $loteId) || (float) $cuota->saldo <= 0) {
                        $validator->errors()->add(
                            "detalles.{$index}.cuota_id",
                            'La cuota seleccionada no está pendiente o no pertenece a este lote.',
                        );
                        $cuota = null;
                    }
                } elseif (
                    $loteId
                    && is_string($concepto)
                    && in_array($concepto, PagoDetalle::conceptosServicioMensual(), true)
                    && ! empty($detalle['periodo'])
                    && ! empty($detalle['servicio_id'])
                ) {
                    $cuota = Cuota::query()
                        ->where('lote_afiliado_id', $loteId)
                        ->where('servicio_id', (int) $detalle['servicio_id'])
                        ->where('periodo', trim((string) $detalle['periodo']))
                        ->first();
                }

                if (
                    $loteId
                    && is_string($concepto)
                    && in_array($concepto, PagoDetalle::conceptosServicioMensual(), true)
                    && ! empty($detalle['servicio_id'])
                ) {
                    $pendienteServicio = (float) Cuota::query()
                        ->where('lote_afiliado_id', $loteId)
                        ->where('servicio_id', (int) $detalle['servicio_id'])
                        ->where('saldo', '>', 0)
                        ->sum('saldo');

                    // Mes en curso o adelantado (hasta 12 meses): la cuota aún no existe,
                    // se permite cobrar una mensualidad y se aplica cuando se emita la cuota.
                    $periodoLinea = trim((string) ($detalle['periodo'] ?? ''));
                    $mesActual = now()->format('Y-m');
                    $anticipo = 0.0;

                    if (preg_match('/^\d{4}-\d{2}$/', $periodoLinea) === 1 && $periodoLinea >= $mesActual) {
                        if ($periodoLinea > now()->addMonths(12)->format('Y-m')) {
                            $validator->errors()->add("detalles.{$index}.periodo", 'Solo se puede adelantar hasta 12 meses.');
                        } else {
                            $anticipo = app(\App\Services\CuotaMensualService::class)
                                ->tarifaMensual($lote, (int) $detalle['servicio_id']);
                        }
                    }

                    if ((float) ($detalle['monto'] ?? 0) > $pendienteServicio + $anticipo + 0.001) {
                        $validator->errors()->add(
                            "detalles.{$index}.monto",
                            'El monto no puede superar lo pendiente de ese servicio más la mensualidad del mes indicado.',
                        );
                    }
                } elseif ($cuota && (float) ($detalle['monto'] ?? 0) > (float) $cuota->saldo + 0.001) {
                    $validator->errors()->add(
                        "detalles.{$index}.monto",
                        'El monto cobrado no puede superar el saldo de la cuota seleccionada.',
                    );
                }

                if (is_string($concepto) && PagoDetalle::esConceptoAfiliacion($concepto)) {
                    $modalidad = (string) ($detalle['modalidad'] ?? '');
                    $montoLinea = (float) ($detalle['monto'] ?? 0);

                    if ($modalidad === Afiliado::FORMA_PLAZO) {
                        $saldoServicio = $this->saldoAfiliacionDeConcepto($lote, $concepto);

                        if ($montoLinea > $saldoServicio + 0.001) {
                            $validator->errors()->add(
                                "detalles.{$index}.monto",
                                'El monto a plazo no puede superar lo que le falta pagar de esa afiliación.',
                            );
                        }
                    } elseif ($modalidad === Afiliado::FORMA_CONTADO) {
                        $this->validarAfiliacionAlContado($validator, $index, $concepto, $montoLinea, $lote);
                    } else {
                        $totalAfiliacion += $montoLinea;
                    }
                }
            }

            if ($totalAfiliacion > (float) $afiliado->saldo_afiliacion + 0.001) {
                $validator->errors()->add('detalles', 'El monto de afiliación excede el saldo pendiente del afiliado.');
            }
        });
    }

    /**
     * Una línea por meses solo vale para agua o alcantarillado del lote, una vez por servicio,
     * con tantos meses como haya por cobrar y con el monto que esos meses suman.
     *
     * @param  array<string, mixed>  $detalle
     * @param  array<int, true>  $vistos
     */
    private function validarLineaDeMeses(Validator $validator, int|string $index, array $detalle, ?LoteAfiliado $lote, array &$vistos): void
    {
        $concepto = (string) ($detalle['concepto'] ?? '');
        $servicio = Servicio::query()->find((int) ($detalle['servicio_id'] ?? 0));

        if (! $lote || ! $servicio || ! in_array($concepto, PagoDetalle::conceptosServicioMensual(), true)) {
            $validator->errors()->add("detalles.{$index}.meses", 'Solo las mensualidades de agua o alcantarillado se cobran por meses.');

            return;
        }

        if (isset($vistos[$servicio->id])) {
            $validator->errors()->add("detalles.{$index}.meses", 'Ese servicio ya está en este cobro. Junte los meses en una sola línea.');

            return;
        }

        $vistos[$servicio->id] = true;
        $disponibles = app(MesesPorCobrarService::class)->paraServicio($lote, $servicio);
        $cantidad = (int) $detalle['meses'];
        $nombre = mb_strtolower((string) $servicio->nombre);

        if ($cantidad > count($disponibles)) {
            $validator->errors()->add(
                "detalles.{$index}.meses",
                count($disponibles) === 0
                    ? "No hay meses de {$nombre} por cobrar."
                    : 'Se pueden cobrar hasta '.count($disponibles)." meses de {$nombre}: los que debe y hasta ".MesesPorCobrarService::MESES_ADELANTO.' adelantados.',
            );

            return;
        }

        $esperado = round(array_sum(array_column(array_slice($disponibles, 0, $cantidad), 'monto')), 2);

        if (abs((float) ($detalle['monto'] ?? 0) - $esperado) > 0.009) {
            $validator->errors()->add(
                "detalles.{$index}.monto",
                "{$cantidad} ".($cantidad === 1 ? 'mes' : 'meses')." de {$nombre} suman Bs ".number_format($esperado, 2, ',', '.').'. Vuelva a abrir el cobro.',
            );
        }
    }

    /**
     * Al contado se paga el monto del tipo de afiliación, una sola vez por lote.
     */
    private function validarAfiliacionAlContado(Validator $validator, int|string $index, string $concepto, float $monto, ?LoteAfiliado $lote): void
    {
        $oficial = PagoDetalle::montoAfiliacionPorConcepto($concepto);

        if ($oficial !== null && abs($monto - $oficial) > 0.009) {
            $validator->errors()->add(
                "detalles.{$index}.monto",
                'La afiliación al contado cuesta Bs '.number_format($oficial, 2, ',', '.').'. Si paga una parte, elija «a plazo».',
            );

            return;
        }

        if (! $lote) {
            return;
        }

        $previo = PagoDetalle::query()
            ->where('concepto', $concepto)
            ->whereHas('pago', fn ($q) => $q
                ->where('lote_afiliado_id', $lote->id)
                ->where('estado', Pago::ESTADO_EMITIDO))
            ->with('pago:id,numero_recibo')
            ->first();

        if ($previo) {
            $validator->errors()->add(
                "detalles.{$index}.concepto",
                'Este lote ya tiene esa afiliación registrada en el recibo '.$previo->pago?->numero_recibo.'.',
            );
        }
    }

    private function saldoAfiliacionDeConcepto(?LoteAfiliado $lote, string $concepto): float
    {
        if (! $lote) {
            return 0;
        }

        if (str_contains($concepto, 'alcantarillado')) {
            return round((float) $lote->saldo_afiliacion_alcantarillado, 2);
        }

        if (str_contains($concepto, 'agua')) {
            return round((float) $lote->saldo_afiliacion_agua, 2);
        }

        return round((float) $lote->saldo_afiliacion, 2);
    }
}
