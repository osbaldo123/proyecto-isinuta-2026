<?php

namespace App\Services\Pagos;

use App\Models\Afiliado;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Servicio;
use App\Models\User;
use App\Services\CalculadorMultasService;
use App\Services\ReciboPropositoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registra un cobro: emite un recibo por propósito (ingreso para agua y alcantarillado;
 * caja para multas, afiliación y demás) dentro de una sola transacción, y descuenta lo
 * cobrado de cuotas, multas y afiliación.
 *
 * Una línea de servicio mensual puede pedir `meses`: el servidor elige los meses (del más
 * antiguo al más reciente, con adelantos de hasta 12 meses) y calcula el monto de cada uno.
 */
class CobroService
{
    public function __construct(
        private readonly CalculadorMultasService $calculadorMultas,
        private readonly ReciboPropositoService $reciboProposito,
        private readonly MesesPorCobrarService $mesesPorCobrar,
        private readonly SaldoAfiliacionService $saldoAfiliacion,
    ) {}

    /**
     * @param  array{fecha_pago: string, metodo_pago: string, observaciones?: string|null, detalles: list<array<string, mixed>>}  $datos
     * @return list<Pago>
     */
    public function registrar(Afiliado $afiliado, LoteAfiliado $lote, array $datos, ?User $usuario): array
    {
        $this->calculadorMultas->evaluarYGenerar($afiliado, $usuario);

        return DB::transaction(function () use ($afiliado, $lote, $datos, $usuario): array {
            $detalles = $this->prepararDetalles($datos['detalles'], $lote);
            $grupos = $this->reciboProposito->agruparDetallesParaRecibos($detalles);
            $metaAfiliacion = $this->metaAfiliacion($lote);
            $categoria = $this->nombreCategoria($lote);
            $cobroId = (string) Str::uuid();
            $creados = [];

            foreach ($grupos as $grupo) {
                $tieneAfiliacion = collect($grupo['detalles'])->contains(
                    fn (array $d): bool => PagoDetalle::esConceptoAfiliacion((string) ($d['concepto'] ?? '')),
                );

                $observaciones = $datos['observaciones'] ?? null;
                if ($tieneAfiliacion && $metaAfiliacion !== '' && ! filled($observaciones)) {
                    $observaciones = $metaAfiliacion;
                }

                $propositoLabel = (string) $grupo['label'];
                if (
                    $categoria !== null
                    && $this->reciboProposito->esCuentaServicioMensual((string) $grupo['key'])
                    && ! str_contains(mb_strtolower($propositoLabel), mb_strtolower($categoria))
                ) {
                    // "Consumo de agua · gestión 2026" + Domiciliar → "... Domiciliar · gestión 2026"
                    if (preg_match('/^(.*?)\s*·\s*(gestión\s+\d{4})\s*$/u', $propositoLabel, $partes) === 1) {
                        $propositoLabel = trim($partes[1].' '.$categoria.' · '.$partes[2]);
                    } else {
                        $propositoLabel = trim("{$propositoLabel} {$categoria}");
                    }
                }

                $pago = Pago::create([
                    'numero_recibo' => Pago::generarNumeroRecibo(),
                    'tipo_recibo' => $grupo['tipo'],
                    'proposito' => $grupo['key'],
                    'proposito_label' => $propositoLabel,
                    'afiliado_id' => $afiliado->id,
                    'lote_afiliado_id' => $lote->id,
                    'cobro_id' => $cobroId,
                    'fecha_pago' => $datos['fecha_pago'],
                    'monto_total' => round(collect($grupo['detalles'])->sum('monto'), 2),
                    'metodo_pago' => $datos['metodo_pago'],
                    'estado' => Pago::ESTADO_EMITIDO,
                    'cobrado_por' => $usuario?->id,
                    'observaciones' => $observaciones,
                ]);

                foreach ($grupo['detalles'] as $detalle) {
                    $concepto = (string) ($detalle['concepto'] ?? '');

                    if (PagoDetalle::esConceptoAfiliacion($concepto) && $metaAfiliacion !== '') {
                        $base = trim((string) ($detalle['descripcion'] ?? ''));
                        if ($base === '' || (! str_contains($base, 'Categoría:') && ! str_contains($base, 'Forma de pago:'))) {
                            $detalle['descripcion'] = trim($base === '' ? $metaAfiliacion : "{$base} · {$metaAfiliacion}");
                        }
                    }

                    $detalle = $this->conCategoriaEnDescripcion($detalle, $categoria);
                    $detalle = $this->vincularCuota($detalle, $lote->id);
                    $this->aplicarSaldo($detalle, $afiliado->id, $lote->id);
                    $pago->detalles()->create($detalle);

                    if (PagoDetalle::esConceptoAfiliacion($concepto)) {
                        $this->saldoAfiliacion->descontar($lote, $concepto, (float) $detalle['monto']);
                    }
                }

                $creados[] = $pago;
            }

            $this->calculadorMultas->evaluarLote($lote->fresh(), $usuario);
            $this->calculadorMultas->actualizarEstadoPorMora(
                Afiliado::query()->findOrFail($afiliado->id)->conLote($lote->fresh()),
            );

            return $creados;
        });
    }

    /**
     * Convierte las líneas de `meses` en una línea por mes y reparte las de monto libre.
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return list<array<string, mixed>>
     */
    private function prepararDetalles(array $detalles, LoteAfiliado $lote): array
    {
        $porMeses = [];
        $libres = [];

        foreach ($detalles as $detalle) {
            if (empty($detalle['meses'])) {
                unset($detalle['meses']);
                $libres[] = $detalle;

                continue;
            }

            $servicio = Servicio::query()->find((int) ($detalle['servicio_id'] ?? 0));
            $meses = $servicio ? $this->mesesPorCobrar->primeros($lote, $servicio, (int) $detalle['meses']) : null;

            if ($meses === null) {
                throw ValidationException::withMessages([
                    'detalles' => 'Los meses elegidos ya no están disponibles. Vuelva a abrir el cobro.',
                ]);
            }

            foreach ($meses as $mes) {
                $linea = $detalle;
                unset($linea['meses']);
                $linea['servicio_id'] = $servicio->id;
                $linea['monto'] = $mes['monto'];
                $linea['periodo'] = $mes['periodo'];
                $linea['cuota_id'] = $mes['cuota_id'];
                $porMeses[] = $linea;
            }
        }

        return [...$porMeses, ...$this->repartirMensualidad($libres, $lote->id)];
    }

    /**
     * Una línea de monto libre de agua o alcantarillado se aplica a las cuotas del servicio,
     * de la más antigua a la más reciente. Lo que sobra queda como pago del mes indicado
     * (mes en curso o adelanto): la cuota de ese mes nace pagada cuando se emite.
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return list<array<string, mixed>>
     */
    private function repartirMensualidad(array $detalles, int $loteId): array
    {
        $repartidos = [];

        foreach ($detalles as $detalle) {
            $concepto = (string) ($detalle['concepto'] ?? '');
            $servicioId = (int) ($detalle['servicio_id'] ?? 0);

            if ($servicioId <= 0 || ! in_array($concepto, PagoDetalle::conceptosServicioMensual(), true)) {
                $repartidos[] = $detalle;

                continue;
            }

            $cuotas = Cuota::query()
                ->where('lote_afiliado_id', $loteId)
                ->where('servicio_id', $servicioId)
                ->where('saldo', '>', 0)
                ->orderBy('periodo')
                ->get();

            $restante = round((float) ($detalle['monto'] ?? 0), 2);
            $aplico = false;

            foreach ($cuotas as $cuota) {
                if ($restante <= 0.001) {
                    break;
                }

                $tomar = round(min((float) $cuota->saldo, $restante), 2);
                if ($tomar <= 0) {
                    continue;
                }

                $repartidos[] = [...$detalle, 'monto' => $tomar, 'cuota_id' => $cuota->id, 'periodo' => $cuota->periodo, 'servicio_id' => $cuota->servicio_id];
                $restante = round($restante - $tomar, 2);
                $aplico = true;
            }

            $periodoPedido = trim((string) ($detalle['periodo'] ?? ''));
            $esAnticipo = $periodoPedido !== '' && $periodoPedido >= now()->format('Y-m');

            if (! $aplico) {
                $repartidos[] = $detalle;
            } elseif ($restante > 0.001 && $esAnticipo) {
                $repartidos[] = [...$detalle, 'monto' => $restante, 'cuota_id' => null, 'periodo' => $periodoPedido];
            }
        }

        return $repartidos;
    }

    /**
     * @param  array<string, mixed>  $detalle
     * @return array<string, mixed>
     */
    private function vincularCuota(array $detalle, int $loteId): array
    {
        if (! empty($detalle['cuota_id']) || empty($detalle['periodo']) || empty($detalle['servicio_id'])) {
            return $detalle;
        }

        if (! in_array((string) ($detalle['concepto'] ?? ''), PagoDetalle::conceptosServicioMensual(), true)) {
            return $detalle;
        }

        $cuotaId = Cuota::query()
            ->where('lote_afiliado_id', $loteId)
            ->where('servicio_id', (int) $detalle['servicio_id'])
            ->where('periodo', (string) $detalle['periodo'])
            ->value('id');

        if ($cuotaId) {
            $detalle['cuota_id'] = $cuotaId;
        }

        return $detalle;
    }

    /**
     * Descuenta lo cobrado. Si otra persona cobró el mismo mes o la misma multa mientras
     * tanto, se cancela todo el cobro en lugar de cobrar dos veces.
     *
     * @param  array<string, mixed>  $detalle
     */
    private function aplicarSaldo(array $detalle, int $afiliadoId, int $loteId): void
    {
        $monto = round((float) ($detalle['monto'] ?? 0), 2);

        if (! empty($detalle['cuota_id'])) {
            $cuota = Cuota::query()->lockForUpdate()->find((int) $detalle['cuota_id']);

            if ($cuota) {
                if ((float) $cuota->saldo + 0.001 < $monto) {
                    throw ValidationException::withMessages([
                        'detalles' => 'El mes '.$cuota->periodo.' ya fue cobrado en otro recibo. Vuelva a abrir el cobro.',
                    ]);
                }

                $saldo = round(max(0, (float) $cuota->saldo - $monto), 2);
                $cuota->update([
                    'saldo' => $saldo,
                    'estado' => $saldo <= 0 ? Cuota::ESTADO_PAGADA : Cuota::ESTADO_PENDIENTE,
                ]);
            }
        } elseif (
            ! empty($detalle['periodo'])
            && ! empty($detalle['servicio_id'])
            && in_array((string) ($detalle['concepto'] ?? ''), PagoDetalle::conceptosServicioMensual(), true)
            && $this->adelantoYaCobrado($loteId, (int) $detalle['servicio_id'], (string) $detalle['periodo'])
        ) {
            throw ValidationException::withMessages([
                'detalles' => 'El mes '.$detalle['periodo'].' ya está pagado por adelantado. Vuelva a abrir el cobro.',
            ]);
        }

        if (! empty($detalle['multa_id'])) {
            $multa = Multa::query()
                ->lockForUpdate()
                ->whereKey((int) $detalle['multa_id'])
                ->where('afiliado_id', $afiliadoId)
                ->first();

            if ($multa) {
                $actual = $multa->saldo !== null ? (float) $multa->saldo : (float) $multa->monto;

                if ($multa->estado !== Multa::ESTADO_PENDIENTE || $actual + 0.001 < $monto) {
                    throw ValidationException::withMessages([
                        'detalles' => 'La multa «'.$multa->descripcion.'» ya fue cobrada o anulada. Vuelva a abrir el cobro.',
                    ]);
                }

                $saldo = round(max(0, $actual - $monto), 2);
                $multa->update([
                    'saldo' => $saldo,
                    'estado' => $saldo <= 0 ? Multa::ESTADO_PAGADA : Multa::ESTADO_PENDIENTE,
                ]);
            }
        }
    }

    private function adelantoYaCobrado(int $loteId, int $servicioId, string $periodo): bool
    {
        $servicio = Servicio::query()->find($servicioId);
        $lote = LoteAfiliado::query()->find($loteId);

        if (! $servicio || ! $lote) {
            return false;
        }

        $mes = collect($this->mesesPorCobrar->paraServicio($lote, $servicio))->firstWhere('periodo', $periodo);

        return $mes === null && $periodo >= now()->format('Y-m');
    }

    private function metaAfiliacion(LoteAfiliado $lote): string
    {
        $categoria = $this->nombreCategoria($lote);
        $forma = match ((string) $lote->forma_pago_afiliacion) {
            Afiliado::FORMA_CONTADO => 'Al contado',
            Afiliado::FORMA_PLAZO => 'A plazo',
            default => null,
        };

        return trim(implode(' · ', array_filter([
            $categoria !== null ? "Categoría: {$categoria}" : null,
            $forma ? "Forma de pago: {$forma}" : null,
        ])));
    }

    /**
     * En agua/alcantarillado el recibo debe decir si el lote es domiciliar o comercial.
     *
     * @param  array<string, mixed>  $detalle
     * @return array<string, mixed>
     */
    private function conCategoriaEnDescripcion(array $detalle, ?string $categoria): array
    {
        $concepto = (string) ($detalle['concepto'] ?? '');

        if ($categoria === null || ! in_array($concepto, PagoDetalle::conceptosServicioMensual(), true)) {
            return $detalle;
        }

        $base = trim((string) ($detalle['descripcion'] ?? ''));
        if ($base === '') {
            $base = $concepto === PagoDetalle::CONCEPTO_CONSUMO_AGUA
                ? 'Consumo de agua'
                : 'Pago de alcantarillado';
        }

        if (str_contains(mb_strtolower($base), mb_strtolower($categoria))) {
            return $detalle;
        }

        $detalle['descripcion'] = trim("{$base} {$categoria}");

        return $detalle;
    }

    private function nombreCategoria(LoteAfiliado $lote): ?string
    {
        $lote->loadMissing('categoria');

        $categoria = trim((string) ($lote->categoria?->nombre ?? LoteAfiliado::labelCategoria($lote->categoria_propiedad)));

        return ($categoria !== '' && $categoria !== '—') ? $categoria : null;
    }
}
