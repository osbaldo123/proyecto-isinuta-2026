<?php

namespace App\Services\Exports;

use App\Models\Afiliado;
use App\Models\Asistencia;
use App\Models\Categoria;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Servicio;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Services\ReciboPropositoService;
use App\Support\TextoPresentacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PagoDocumentExportService
{
    public function __construct(
        private readonly TabularDocumentExporter $exporter,
    ) {}

    public function exportRecibo(Pago $pago, string $formato): StreamedResponse
    {
        $pago->loadMissing(['afiliado.lotes.otb', 'lote.otb', 'lote.categoria', 'cobrador', 'anuladoPor', 'detalles.servicio']);

        $proposito = app(ReciboPropositoService::class);
        $cuenta = $proposito->cuentaDesdePago($pago);

        $titulo = $proposito->tituloDocumento($cuenta['tipo']);
        $esCaja = $cuenta['tipo'] === ReciboPropositoService::TIPO_CAJA;

        $headers = $esCaja
            ? ['Por concepto de', 'Importe (Bs)']
            : ['Por concepto de', 'Bolivianos', 'Dólares'];

        $fechaPago = $pago->fecha_pago?->format('d/m/Y') ?? '';

        $lineas = $pago->detalles->map(fn (PagoDetalle $d): array => [
            'texto' => $proposito->textoConcepto($d, $cuenta, $fechaPago),
            'monto' => (float) $d->monto,
            'periodo' => $d->periodo,
        ])->all();

        $rows = collect(TextoPresentacion::agruparLineasRecibo($lineas))
            ->map(fn (array $l): array => $esCaja
                ? [$l['texto'], number_format((float) $l['monto'], 2, '.', '')]
                : [$l['texto'], number_format((float) $l['monto'], 2, '.', ''), '0.00'])
            ->all();

        if ($esCaja) {
            $rows[] = ['Total', number_format((float) $pago->monto_total, 2, '.', '')];
        } else {
            $rows[] = [
                'Total',
                number_format((float) $pago->monto_total, 2, '.', ''),
                '0.00',
            ];
        }

        $afiliado = $pago->afiliado;
        if ($pago->lote) {
            $afiliado?->conLote($pago->lote);
        }

        $meta = [
            'ASOCIACIÓN DE AGUA POTABLE Y ALCANTARILLADO "ISINUTA"',
            $titulo,
            'N°: '.$pago->numero_recibo,
            ...($pago->estaAnulado() ? [
                '*** RECIBO ANULADO'
                    .($pago->anulado_en ? ' el '.$pago->anulado_en->format('d/m/Y H:i') : '')
                    .($pago->anuladoPor?->name ? ' por '.$pago->anuladoPor->name : '')
                    .' — no tiene valor ***',
                'Motivo de la anulación: '.($pago->motivo_anulacion ?: '—'),
            ] : []),
            'Fecha: '.$pago->fecha_pago->format('d/m/Y'),
            'Recibido de: '.TextoPresentacion::sinDuplicados($afiliado->identidadDocumento()),
            'Codigo: '.$afiliado->codigoPropiedadDisplay(),
            'CI: '.$afiliado->ciCompletoDisplay(),
            'OTB: '.($pago->lote?->otb?->nombre ?? $afiliado->otb?->nombre ?? '—'),
            'Lote: '.($pago->lote?->numero_lote ?: ($afiliado->numero_lote ?: '—')),
            'Categoría: '.(trim((string) ($pago->lote?->categoria?->nombre
                ?? LoteAfiliado::labelCategoria($pago->lote?->categoria_propiedad))) ?: '—'),
            'Rec.Manual: 0',
            'Propósito: '.($cuenta['label'] ?? '—'),
            'Cobrador: '.($pago->cobrador?->name ?? '—'),
            $pago->observaciones ?: '—',
        ];

        if ($esCaja) {
            $meta[] = $proposito->montoEnLetras((float) $pago->monto_total);
        }

        $meta[] = 'Emitido: '.now()->format('d/m/Y H:i:s');

        $base = 'recibo-'.$pago->numero_recibo;

        abort_unless($formato === 'xlsx', 404);

        return $this->exporter->downloadXlsx($base.'.xlsx', 'Recibo', $headers, $rows, $meta);
    }

    /**
     * @param  array{
     *   desde: string,
     *   hasta: string,
     *   grupos: array<int, array{concepto: string, label: string, lineas: array<int, array<string, mixed>>, subtotal: float}>,
     *   total_general: float
     * }  $reporte
     */
    public function exportIngresos(array $reporte, string $formato): StreamedResponse
    {
        $headers = ['Fecha', 'Nombre', 'Detalle', 'Bolivianos'];
        $rows = [];

        foreach ($reporte['grupos'] as $grupo) {
            $rows[] = ['', '', $grupo['label'], ''];

            foreach ($grupo['lineas'] as $linea) {
                $detalle = (string) ($linea['detalle'] ?? '');
                if (! empty($linea['cantidad'])) {
                    $detalle = trim($detalle.' · cantidad: '.(int) $linea['cantidad']);
                }

                $rows[] = [
                    $linea['fecha'],
                    $linea['socio'],
                    $detalle,
                    number_format((float) $linea['bolivianos'], 2, '.', ''),
                ];
            }

            $rows[] = [
                '',
                '',
                'Subtotal',
                number_format((float) $grupo['subtotal'], 2, '.', ''),
            ];
        }

        $rows[] = [
            '',
            '',
            'Total general',
            number_format((float) $reporte['total_general'], 2, '.', ''),
        ];

        $meta = [
            'ASOCIACIÓN DE AGUA POTABLE Y ALCANTARILLADO "ISINUTA"',
            'DETALLE DE PAGOS Y APORTES DE AFILIADOS',
            'REPORTE GENERAL POR CONCEPTO (agua, alcantarillado, afiliación y demás)',
            'DEL: '.Carbon::parse($reporte['desde'])->format('d/m/Y').' AL: '.Carbon::parse($reporte['hasta'])->format('d/m/Y'),
            'Emitido: '.now()->format('d/m/Y H:i:s'),
        ];

        $base = 'ingresos-'.$reporte['desde'].'_'.$reporte['hasta'];

        abort_unless($formato === 'xlsx', 404);

        return $this->exporter->downloadXlsx($base.'.xlsx', 'Ingresos', $headers, $rows, $meta);
    }

    /**
     * @param  array{
     *   grupos: list<array{concepto: string, label: string, lineas: list<array<string, mixed>>, subtotal: float}>,
     *   total_general: float,
     *   emitido: string
     * }  $reporte
     */
    public function exportDeudas(array $reporte, string $formato): StreamedResponse
    {
        abort_unless($formato === 'xlsx', 404);

        $headers = ['Fecha', 'Nombre', 'Detalle', 'Bolivianos'];
        $rows = [];

        foreach ($reporte['grupos'] as $grupo) {
            $rows[] = ['', '', $grupo['label'], ''];

            foreach ($grupo['lineas'] as $linea) {
                $rows[] = [
                    $linea['fecha'] ?? '—',
                    $linea['socio'] ?? '—',
                    $linea['detalle'] ?? '—',
                    number_format((float) ($linea['bolivianos'] ?? 0), 2, '.', ''),
                ];
            }

            $rows[] = [
                '',
                '',
                'Subtotal',
                number_format((float) $grupo['subtotal'], 2, '.', ''),
            ];
        }

        $rows[] = [
            '',
            '',
            'Total general',
            number_format((float) $reporte['total_general'], 2, '.', ''),
        ];

        $meta = [
            'ASOCIACIÓN DE AGUA POTABLE Y ALCANTARILLADO "ISINUTA"',
            'REPORTE DE DEUDAS — SALDOS PENDIENTES POR CONCEPTO',
            'Cuotas mensuales, multas (mora / falta de trabajos / manuales) y afiliación a plazo',
            'Emitido: '.$reporte['emitido'],
        ];

        return $this->exporter->downloadXlsx(
            'deudas-'.now()->format('Y-m-d').'.xlsx',
            'Deudas',
            $headers,
            $rows,
            $meta,
        );
    }

    /**
     * @return array{
     *   desde: string,
     *   hasta: string,
     *   grupos: array<int, array{concepto: string, label: string, lineas: array<int, array<string, mixed>>, subtotal: float}>,
     *   total_general: float
     * }
     */
    public function buildIngresosReport(
        string $desde,
        string $hasta,
        ?int $socioId = null,
        ?string $conceptoFiltro = null,
        ?int $categoriaId = null,
    ): array {
        $detalles = PagoDetalle::query()
            ->with(['pago.afiliado.lotes.otb', 'pago.afiliado.lotes.categoria', 'pago.lote.otb', 'pago.lote.categoria', 'servicio', 'multa'])
            ->whereHas('pago', function ($query) use ($desde, $hasta, $socioId, $categoriaId): void {
                $query->where('estado', Pago::ESTADO_EMITIDO)
                    ->whereDate('fecha_pago', '>=', $desde)
                    ->whereDate('fecha_pago', '<=', $hasta);

                if ($socioId) {
                    $query->where('afiliado_id', $socioId);
                }

                if ($categoriaId) {
                    $query->where(function ($q) use ($categoriaId): void {
                        $q->whereHas('lote', fn ($l) => $l->where('categoria_id', $categoriaId))
                            ->orWhere(function ($sinLote) use ($categoriaId): void {
                                $sinLote->whereNull('lote_afiliado_id')
                                    ->whereHas('afiliado.lotes', fn ($l) => $l->where('categoria_id', $categoriaId));
                            });
                    });
                }
            })
            ->get()
            ->sortBy(fn (PagoDetalle $d) => $d->pago?->fecha_pago?->timestamp.'-'.$d->pago_id)
            ->values();

        $reporte = $this->agruparPorCuentasConcepto($detalles, $desde, $hasta);
        $reporte['grupos'] = $this->unirFaltasDeTrabajo($reporte['grupos'], $desde, $hasta, $socioId, $categoriaId);
        $reporte['grupos'] = $this->unirDeudasAfiliacionPlazo($reporte['grupos'], $desde, $hasta, $socioId, $categoriaId);
        $reporte['total_general'] = round(collect($reporte['grupos'])->sum('subtotal'), 2);
        $reporte['cuentas'] = count($reporte['grupos']);

        if (filled($conceptoFiltro)) {
            $reporte['grupos'] = $this->filtrarGruposPorConcepto($reporte['grupos'], $conceptoFiltro);
            $reporte['total_general'] = round(collect($reporte['grupos'])->sum('subtotal'), 2);
            $reporte['cuentas'] = count($reporte['grupos']);
        }

        return $reporte;
    }

    /**
     * @param  array<int, array{concepto: string, label: string, lineas: array<int, array<string, mixed>>, subtotal: float}>  $grupos
     * @return array<int, array{concepto: string, label: string, lineas: array<int, array<string, mixed>>, subtotal: float}>
     */
    public function filtrarGruposPorConcepto(array $grupos, string $conceptoFiltro): array
    {
        $filtro = mb_strtolower(trim($conceptoFiltro));

        return collect($grupos)
            ->filter(function (array $grupo) use ($filtro): bool {
                if ($filtro === '') {
                    return true;
                }

                $concepto = mb_strtolower((string) $grupo['concepto']);
                $base = explode(':', $concepto, 2)[0];

                return $concepto === $filtro
                    || $base === $filtro
                    || mb_strtolower((string) $grupo['label']) === $filtro;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLineaReporte(PagoDetalle $detalle): array
    {
        $pago = $detalle->pago;
        $afiliado = $pago?->afiliado;
        $lote = $pago?->lote;
        $loteNumero = $lote?->numero_lote;

        if ($afiliado) {
            if ($lote) {
                $afiliado->conLote($lote);
            } else {
                $afiliado->loadMissing('lotes.otb');
            }
            $loteNumero = $loteNumero ?: $afiliado->numero_lote;
            $socio = $this->nombreSocioReporte($afiliado, $lote);
        } else {
            $socio = '—';
        }

        $cuenta = $this->cuentaConcepto($detalle);
        $periodo = $this->formatPeriodo($detalle->periodo);
        $cantidad = $this->cantidadFaltasDesdeDetalle($detalle, $cuenta['key']);

        return [
            'fecha' => $pago?->fecha_pago?->format('d/m/Y') ?? '—',
            'cantidad' => $cantidad,
            'socio' => $socio,
            'ci' => $afiliado?->ciCompletoDisplay() ?? '—',
            'lote' => filled($loteNumero) ? (string) $loteNumero : '—',
            'afiliado_id' => $afiliado?->id,
            'lote_id' => $lote?->id,
            'detalle' => $this->detalleLineaTexto($detalle, $cuenta),
            'periodo' => $periodo,
            'dolares' => 0.0,
            'bolivianos' => (float) $detalle->monto,
            'recibo' => $pago?->numero_recibo ?? '—',
            'pagado' => $pago?->fecha_pago?->format('d/m/Y') ?? '—',
            'cuenta' => $cuenta['label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private function lineaReporteTieneDetalle(array $linea): bool
    {
        $detalle = trim((string) ($linea['detalle'] ?? ''));

        return $detalle !== '' && $detalle !== '—';
    }

    /**
     * Detalle de cambio de nombre: «de Nombre Apellido a Nombre Apellido».
     */
    private function detalleCambioDeNombre(string $raw): string
    {
        $resto = trim((string) preg_replace('/^cambio\s+de\s+nombre\s*/iu', '', $raw));
        if ($resto === '') {
            return 'Sin detalle del nombre anterior y nuevo';
        }

        if (preg_match('/^de\s+(.+?)\s+a\s+(.+)$/iu', $resto, $m)) {
            $desde = $this->formatearNombrePersona(trim($m[1]));
            $hasta = $this->formatearNombrePersona(trim($m[2]));

            if ($desde !== '' && $hasta !== '') {
                return 'de '.$desde.' a '.$hasta;
            }
        }

        return $this->formatearNombrePersona($resto);
    }

    /**
     * Normaliza nombres propios (mayúscula inicial en cada palabra).
     */
    private function formatearNombrePersona(string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? $nombre);
        if ($nombre === '') {
            return '';
        }

        $partes = preg_split('/\s+/u', mb_strtolower($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($partes)
            ->map(fn (string $parte): string => mb_strtoupper(mb_substr($parte, 0, 1)).mb_substr($parte, 1))
            ->implode(' ');
    }

    /**
     * Extrae la cantidad de faltas (trabajos/reuniones) desde la descripción, p. ej. «falta de trabajos 3 2020-2021».
     */
    private function cantidadFaltasDesdeDetalle(PagoDetalle $detalle, string $cuentaKey): ?int
    {
        if (! $this->esCuentaFalta($cuentaKey)) {
            return null;
        }

        $raw = trim((string) ($detalle->descripcion ?? ''));
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($cuentaKey, 'falta_trabajos_')) {
            if (preg_match('/falta\s+de\s+(?:reuniones?\s+y\s+)?trabajos?(?:\s+de\s+(?:agua(?:\s+potable)?|alcantarillado))?\s*[:=]?\s*(\d+)/iu', $raw, $m)) {
                return max(1, (int) $m[1]);
            }
        }

        if (str_starts_with($cuentaKey, 'falta_reuniones_')) {
            if (preg_match('/falta\s+de\s+reuniones?(?:\s+de\s+(?:agua(?:\s+potable)?|alcantarillado))?\s*[:=]?\s*(\d+)/iu', $raw, $m)) {
                return max(1, (int) $m[1]);
            }
        }

        if (preg_match('/\b(\d+)\s*faltas?\b/iu', $raw, $m)) {
            return max(1, (int) $m[1]);
        }

        return null;
    }

    /**
     * Cambio de nombre, cambio de titularidad y faltas de reuniones no se usan.
     */
    private function detalleFueraDeReporte(PagoDetalle $detalle): bool
    {
        $texto = mb_strtolower(trim((string) ($detalle->descripcion ?? '')));

        if (
            str_contains($texto, 'cambio de nombre')
            || str_contains($texto, 'cambio nombre')
            || str_contains($texto, 'cambio de titular')
            || str_contains($texto, 'cambio titularidad')
            || str_contains($texto, 'falta de reunion')
            || str_contains($texto, 'falta de reunión')
        ) {
            return true;
        }

        return in_array($this->cuentaConcepto($detalle)['key'], [
            'cambio_titularidad',
            'cambio_de_nombre',
            'falta_reuniones_agua_potable',
            'falta_reuniones_alcantarillado',
            'falta_de_reuniones',
            'reuniones_agua',
            'reuniones_alcantarillado',
            'otros',
        ], true);
    }

    private function esCuentaFalta(string $cuentaKey): bool
    {
        return in_array($cuentaKey, [
            'falta_trabajos_agua_potable',
            'falta_trabajos_alcantarillado',
            'falta_reuniones_agua_potable',
            'falta_reuniones_alcantarillado',
        ], true);
    }

    /**
     * Nombre del socio: nombre y barrio, sin código ni lote delante.
     * Cada lote sigue siendo una fila aparte (lote_id / afiliado_id).
     */
    private function nombreSocioReporte(Afiliado $afiliado, ?LoteAfiliado $lote): string
    {
        $otb = $lote?->otb?->nombre ?? $afiliado->otb?->nombre;
        $numeroLote = $lote?->numero_lote ?: $afiliado->numero_lote;
        $contexto = trim(collect([$otb, filled($numeroLote) ? 'Lote '.$numeroLote : null])->filter()->implode(', '));

        return TextoPresentacion::componerNombreConOtb(
            (string) $afiliado->nombre_completo,
            $contexto !== '' ? $contexto : null,
        );
    }

    /**
     * Pagos de afiliados x Concepto — todas las cuentas: una sección por concepto/cuenta,
     * varios afiliados debajo, subtotal por cuenta y total general.
     *
     * @param  Collection<int, PagoDetalle>  $detalles
     * @return array{
     *   desde: string,
     *   hasta: string,
     *   grupos: array<int, array{concepto: string, label: string, lineas: array<int, array<string, mixed>>, subtotal: float}>,
     *   total_general: float,
     *   cuentas: int
     * }
     */
    private function agruparPorCuentasConcepto(Collection $detalles, string $desde, string $hasta): array
    {
        $cuotasPagadas = $this->clavesCuotasPagadas();

        $detalles = $this->expandirCuentasCombinadas($detalles)
            ->reject(fn (PagoDetalle $detalle): bool => $this->esAporteOrdinarioOmitible($detalle))
            ->reject(fn (PagoDetalle $detalle): bool => $this->esPagoFaltaDeTrabajo($detalle))
            ->reject(fn (PagoDetalle $detalle): bool => $this->detalleFueraDeReporte($detalle))
            ->reject(fn (PagoDetalle $detalle): bool => $this->mensualidadSinCuotaPagada($detalle, $cuotasPagadas))
            ->reject(fn (PagoDetalle $detalle): bool => $this->esAbonoAfiliacionPlazo($detalle))
            ->values();

        $grouped = $detalles->groupBy(fn (PagoDetalle $detalle): string => $this->claveGrupoReporte($detalle));

        $grupos = $grouped
            ->map(function (Collection $items, string $key): array {
                $baseKey = explode(':', $key, 2)[0];
                $label = $this->etiquetaGrupoReporte($key, $items);
                $lineas = $this->esCuentaServicioConPeriodo($baseKey)
                    ? $this->consolidarLineasServicioPorPeriodo($items, $label)
                    : $items
                        ->sortBy(fn (PagoDetalle $d) => $d->pago?->fecha_pago?->timestamp.'-'.$d->pago_id)
                        ->map(fn (PagoDetalle $detalle): array => $this->mapLineaReporte($detalle))
                        ->values()
                        ->all();

                $lineas = collect($lineas)
                    ->filter(fn (array $linea): bool => $this->lineaReporteTieneDetalle($linea))
                    ->values()
                    ->all();

                $subtotal = round((float) collect($lineas)->sum('bolivianos'), 2);

                return [
                    'concepto' => $key,
                    'label' => $label,
                    'lineas' => $lineas,
                    'subtotal' => $subtotal,
                ];
            })
            ->filter(fn (array $grupo): bool => $grupo['lineas'] !== [])
            ->sortBy(fn (array $grupo): string => $this->ordenGrupoReporte((string) $grupo['concepto']).'-'.mb_strtolower((string) $grupo['label']))
            ->values()
            ->all();

        $totalGeneral = round(collect($grupos)->sum('subtotal'), 2);

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'grupos' => $grupos,
            'total_general' => $totalGeneral,
            'cuentas' => count($grupos),
        ];
    }

    /**
     * Nombre de cuenta/concepto como en el legado (texto del detalle / servicio).
     *
     * @return array{key: string, label: string}
     */
    private function cuentaConcepto(PagoDetalle $detalle): array
    {
        $detalle->loadMissing('servicio', 'multa');

        $texto = mb_strtolower(trim(
            ($detalle->descripcion ?? '').' '.
            ($detalle->servicio?->nombre ?? '').' '.
            ($detalle->servicio?->tipo ?? '').' '.
            ($detalle->servicio?->codigo ?? '')
        ));

        // Faltas por texto primero: evita que «… de alcantarillado» caiga en servicio.
        $falta = $this->cuentaFaltaDesdeTexto($texto);
        if ($falta !== null) {
            return $falta;
        }

        $porConcepto = PagoDetalle::clasificacionCuota((string) $detalle->concepto);
        if ($porConcepto !== null) {
            return $this->cuentaReporteDesdeConceptoCuota($porConcepto, $texto);
        }

        $tipo = mb_strtolower((string) ($detalle->servicio?->tipo ?? ''));
        $codigo = mb_strtoupper((string) ($detalle->servicio?->codigo ?? ''));

        if (
            str_contains($texto, 'cambio de titular')
            || str_contains($texto, 'cambio titularidad')
            || str_contains($texto, 'cambio de afili')
        ) {
            return ['key' => 'cambio_titularidad', 'label' => 'Cambio de titularidad'];
        }

        // Una sola cuenta para cualquier variante de cambio de nombre.
        if (str_contains($texto, 'cambio de nombre') || str_contains($texto, 'cambio nombre')) {
            return ['key' => 'cambio_de_nombre', 'label' => 'Cambio de nombre'];
        }

        if (
            $detalle->concepto === PagoDetalle::CONCEPTO_AFILIACION
            || str_contains($texto, 'afiliaci')
        ) {
            return ['key' => 'afiliacion', 'label' => 'Afiliación'];
        }

        // Cuentas de servicio: títulos fijos como en el legado.
        if (
            $tipo === 'alcantarillado'
            || $codigo === 'ALC'
            || str_contains($texto, 'alcantarillado')
        ) {
            return ['key' => 'servicio_alcantarillado', 'label' => 'Servicio de Alcantarillado'];
        }

        if (
            $tipo === 'agua'
            || $codigo === 'AGUA'
            || str_contains($texto, 'agua potable')
            || str_contains($texto, 'servicio agua')
        ) {
            return ['key' => 'servicio_agua_potable', 'label' => 'Servicio Agua Potable'];
        }

        // Multa / otro / servicio: título fijo. La descripción libre va en Detalle, no como encabezado.
        if (
            $detalle->concepto === PagoDetalle::CONCEPTO_MULTA
            || (bool) preg_match('/\bmultas?\b/u', $texto)
        ) {
            return ['key' => 'multas', 'label' => 'Multas'];
        }

        if ($detalle->concepto === PagoDetalle::CONCEPTO_OTRO) {
            return ['key' => 'otros', 'label' => 'Otros conceptos'];
        }

        if ($detalle->concepto === PagoDetalle::CONCEPTO_SERVICIO_MENSUAL) {
            return ['key' => 'servicio_agua_potable', 'label' => 'Servicio Agua Potable'];
        }

        // Ya no se agrupa como «Aporte ordinario» en reportes.
        if ($detalle->concepto === PagoDetalle::CONCEPTO_APORTE || str_contains($texto, 'aporte')) {
            return ['key' => 'otros', 'label' => 'Otros conceptos'];
        }

        $label = trim((string) ($detalle->descripcion ?: ''));

        if (! $this->esTituloCuentaUtil($label) && $detalle->servicio) {
            $label = trim((string) $detalle->servicio->nombre);
        }

        if (! $this->esTituloCuentaUtil($label) && $detalle->multa) {
            $label = trim((string) ($detalle->multa->descripcion ?: $detalle->multa->motivo));
        }

        if (! $this->esTituloCuentaUtil($label)) {
            $label = $this->conceptoLabel($detalle->concepto);
        }

        return [
            'key' => mb_strtolower($label),
            'label' => $label,
        ];
    }

    /**
     * Evita que textos cortos o códigos basura (p. ej. "d", "ect") se usen como título de cuenta.
     */
    private function esTituloCuentaUtil(string $label): bool
    {
        $t = mb_strtolower(trim($label));

        if ($t === '' || mb_strlen($t) < 4) {
            return false;
        }

        return ! in_array($t, [
            'multa',
            'multas',
            'aporte',
            'aportes',
            'otro',
            'otros',
            'cuota',
            'servicio',
        ], true);
    }

    /**
     * Texto de la columna Detalle: no repetir el título de la cuenta.
     *
     * @param  array{key: string, label: string}  $cuenta
     */
    private function detalleLineaTexto(PagoDetalle $detalle, array $cuenta): string
    {
        $raw = trim((string) ($detalle->descripcion ?: ''));
        $periodo = $this->formatPeriodo($detalle->periodo);
        $periodoOk = $periodo !== '—';

        if (in_array($cuenta['key'], ['servicio_agua_potable', 'servicio_alcantarillado'], true)) {
            $nombreServicio = trim((string) ($detalle->servicio?->nombre ?: ''));

            $texto = $raw;
            if (
                $texto === ''
                || mb_strtolower($texto) === mb_strtolower($cuenta['label'])
                || ($nombreServicio !== '' && mb_strtolower($texto) === mb_strtolower($nombreServicio))
            ) {
                $texto = $cuenta['label'];
            }

            return $this->detalleConRangoMeses($texto, $detalle->periodo, $detalle->periodo);
        }

        if ($cuenta['key'] === 'afiliacion' || str_starts_with($cuenta['key'], 'afiliacion:')) {
            $clave = $this->claveAfiliacion($detalle);
            $saldo = $this->saldoAfiliacionServicio($detalle, $clave);
            if ($saldo > 0) {
                return 'debe Bs '.$this->bolivianosTexto($saldo);
            }

            if (str_contains($clave, ':')) {
                return 'cancelado';
            }

            $resto = trim((string) preg_replace('/^afiliaci[oó]n\s*/iu', '', $raw));

            return $resto !== '' ? $resto : ($periodoOk ? $periodo : 'Cuota de afiliación');
        }

        if ($cuenta['key'] === 'cambio_titularidad') {
            $resto = trim((string) preg_replace(
                '/^(cambio\s+de\s+titularidad|cambio\s+titularidad|cambio\s+de\s+afiliaci[oó]n)\s*/iu',
                '',
                $raw,
            ));

            return $resto !== '' ? $resto : ($raw !== '' ? $raw : '—');
        }

        if ($cuenta['key'] === 'cambio_de_nombre') {
            return $this->detalleCambioDeNombre($raw);
        }

        if ($this->esCuentaFalta($cuenta['key'])) {
            $resto = trim((string) preg_replace(
                '/^falta\s+de\s+(?:reuniones?\s+y\s+)?(?:trabajos?|reuniones?)(?:\s+de\s+(?:agua(?:\s+potable)?|alcantarillado))?\s*/iu',
                '',
                $raw,
            ));
            $resto = trim((string) preg_replace('/^[:=]?\s*\d+\s*/u', '', $resto));

            return $resto !== '' ? $resto : '—';
        }

        // Multas: descripción (mora/manual). Si no hay, fecha completa (d/m/Y), no periodo 08/2026.
        if ($cuenta['key'] === 'multas') {
            return $this->detalleMultaTexto($detalle, $raw);
        }

        if (
            $raw === ''
            || mb_strtolower($raw) === mb_strtolower($cuenta['label'])
            || ! $this->esTituloCuentaUtil($raw)
        ) {
            return $periodoOk ? $periodo : '—';
        }

        return $periodoOk ? $periodo.' · '.$raw : $raw;
    }

    /**
     * Texto de detalle para el grupo Multas del reporte de ingresos.
     */
    private function detalleMultaTexto(PagoDetalle $detalle, string $raw): string
    {
        $detalle->loadMissing('multa');
        $multa = $detalle->multa;

        $descripcion = trim((string) ($multa?->descripcion ?: $raw));
        if (
            $descripcion !== ''
            && mb_strtolower($descripcion) !== 'multas'
            && $this->esTituloCuentaUtil($descripcion)
        ) {
            return $descripcion;
        }

        $fecha = $detalle->pago?->fecha_pago
            ?? $multa?->fecha_generacion;

        return $fecha?->format('d/m/Y') ?? '—';
    }

    /**
     * En reportes, trabajos/reuniones se muestran como faltas separadas por servicio.
     *
     * @param  array{key: string, label: string, tipo?: string}  $porConcepto
     * @return array{key: string, label: string}
     */
    private function cuentaReporteDesdeConceptoCuota(array $porConcepto, string $texto): array
    {
        return match ($porConcepto['key']) {
            'pago_trabajo_agua' => [
                'key' => 'falta_trabajos_agua_potable',
                'label' => 'Falta de trabajos de agua potable',
            ],
            'pago_trabajo_alcantarillado' => [
                'key' => 'falta_trabajos_alcantarillado',
                'label' => 'Falta de trabajos de alcantarillado',
            ],
            'pago_trabajo' => $this->cuentaFaltaTrabajos($texto),
            'reuniones_agua', 'reuniones_alcantarillado' => [
                'key' => 'multas',
                'label' => 'Multas',
            ],
            default => [
                'key' => $porConcepto['key'],
                'label' => $porConcepto['label'],
            ],
        };
    }

    /**
     * @return array{key: string, label: string}|null
     */
    private function cuentaFaltaDesdeTexto(string $texto): ?array
    {
        $tieneReuniones = str_contains($texto, 'reunion') || str_contains($texto, 'reunión');
        $tieneTrabajos = str_contains($texto, 'trabajo');

        // Combinadas: expandirCuentasCombinadas las parte antes de agrupar.
        if ($tieneReuniones && $tieneTrabajos) {
            return null;
        }

        if ($tieneReuniones) {
            return null;
        }

        if ($tieneTrabajos) {
            return $this->cuentaFaltaTrabajos($texto);
        }

        return null;
    }

    /**
     * @return array{key: string, label: string}
     */
    private function cuentaFaltaTrabajos(string $texto): array
    {
        return [
            'key' => 'falta_trabajos',
            'label' => 'Falta de trabajos',
        ];
    }

    /**
     * Los recibos viejos de «falta de trabajos» no son la lista del módulo.
     * Esa sección sale de las multas reales de inasistencia.
     */
    private function esPagoFaltaDeTrabajo(PagoDetalle $detalle): bool
    {
        if (in_array($detalle->concepto, [
            PagoDetalle::CONCEPTO_PAGO_TRABAJO,
            PagoDetalle::CONCEPTO_PAGO_TRABAJO_AGUA,
            PagoDetalle::CONCEPTO_PAGO_TRABAJO_ALCANTARILLADO,
        ], true)) {
            return true;
        }

        $texto = mb_strtolower(trim((string) ($detalle->descripcion ?? '')));

        return str_contains($texto, 'falta de trabajo');
    }

    /**
     * Una fila por lote marcado como falta. El nombre del afiliado se repite;
     * el número de lote y la categoría distinguen cada fila.
     *
     * @param  array<int, array{concepto: string, label: string, lineas: array<int, array<string, mixed>>, subtotal: float}>  $grupos
     * @return array<int, array{concepto: string, label: string, lineas: array<int, array<string, mixed>>, subtotal: float}>
     */
    private function unirFaltasDeTrabajo(array $grupos, string $desde, string $hasta, ?int $socioId, ?int $categoriaId): array
    {
        $lineas = $this->lineasFaltasDeTrabajo($desde, $hasta, $socioId, $categoriaId);
        if ($lineas === []) {
            return $grupos;
        }

        $grupos[] = [
            'concepto' => 'falta_trabajos',
            'label' => 'Falta de trabajos',
            'lineas' => $lineas,
            'subtotal' => round((float) collect($lineas)->sum('bolivianos'), 2),
        ];

        return collect($grupos)
            ->sortBy(fn (array $grupo): string => $this->ordenGrupoReporte((string) $grupo['concepto']).'-'.mb_strtolower((string) $grupo['label']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lineasFaltasDeTrabajo(string $desde, string $hasta, ?int $socioId, ?int $categoriaId): array
    {
        $ausentes = Asistencia::query()
            ->where('presente', false)
            ->whereNotNull('trabajo_id')
            ->whereNotNull('lote_afiliado_id')
            ->get(['trabajo_id', 'lote_afiliado_id'])
            ->mapWithKeys(fn (Asistencia $asistencia): array => [
                $asistencia->trabajo_id.'-'.$asistencia->lote_afiliado_id => true,
            ]);

        return Multa::query()
            ->with(['afiliado', 'lote.otb', 'lote.categoria', 'trabajo'])
            ->where('motivo', Multa::MOTIVO_FALTA_TRABAJO)
            ->where('estado', '!=', Multa::ESTADO_ANULADA)
            ->whereNotNull('trabajo_id')
            ->whereDate('fecha_generacion', '>=', $desde)
            ->whereDate('fecha_generacion', '<=', $hasta)
            ->when($socioId, fn ($query) => $query->where('afiliado_id', $socioId))
            ->when($categoriaId, fn ($query) => $query->whereHas(
                'lote',
                fn ($lote) => $lote->where('categoria_id', $categoriaId),
            ))
            ->get()
            ->filter(fn (Multa $multa): bool => $ausentes->has($multa->trabajo_id.'-'.$multa->lote_afiliado_id))
            ->sortBy(function (Multa $multa): string {
                $numero = str_pad((string) ($multa->lote?->numero_lote ?? ''), 8, '0', STR_PAD_LEFT);

                return ($multa->fecha_generacion?->format('Y-m-d') ?? '')
                    .'-'.mb_strtolower((string) ($multa->afiliado?->apellidos ?? ''))
                    .'-'.mb_strtolower((string) ($multa->afiliado?->nombres ?? ''))
                    .'-'.$numero
                    .'-'.$multa->id;
            })
            ->map(function (Multa $multa): array {
                $afiliado = $multa->afiliado;
                $lote = $multa->lote;
                $tipo = trim((string) ($multa->trabajo?->tipo ?: 'Trabajo'));
                $categoria = trim((string) ($lote?->categoria?->nombre ?? ''));

                return [
                    'fecha' => $multa->fecha_generacion?->format('d/m/Y') ?? '—',
                    'cantidad' => null,
                    'socio' => $afiliado ? $this->nombreSocioReporte($afiliado, $lote) : '—',
                    'ci' => $afiliado?->ciCompletoDisplay() ?? '—',
                    'lote' => filled($lote?->numero_lote) ? (string) $lote->numero_lote : '—',
                    'afiliado_id' => $afiliado?->id,
                    'lote_id' => $lote?->id,
                    'detalle' => $categoria !== '' ? $tipo.', '.$categoria : $tipo,
                    'periodo' => '—',
                    'dolares' => 0.0,
                    'bolivianos' => round((float) $multa->monto, 2),
                    'recibo' => '—',
                    'pagado' => '—',
                    'cuenta' => 'Falta de trabajos',
                ];
            })
            ->filter(fn (array $linea): bool => $this->lineaReporteTieneDetalle($linea))
            ->values()
            ->all();
    }

    private function textoEsAlcantarillado(string $texto): bool
    {
        return str_contains($texto, 'alcantar');
    }

    private function esAporteOrdinarioOmitible(PagoDetalle $detalle): bool
    {
        $descripcion = mb_strtolower(trim((string) ($detalle->descripcion ?? '')));

        if ($descripcion === 'aporte ordinario') {
            return true;
        }

        if ($detalle->concepto !== PagoDetalle::CONCEPTO_APORTE) {
            return false;
        }

        return $descripcion === '' || $descripcion === 'aporte' || $descripcion === 'aporte ordinario';
    }

    /**
     * Separa líneas legacy "reuniones y trabajos" en dos cuentas distintas.
     *
     * @param  Collection<int, PagoDetalle>  $detalles
     * @return Collection<int, PagoDetalle>
     */
    private function expandirCuentasCombinadas(Collection $detalles): Collection
    {
        return $detalles
            ->flatMap(function (PagoDetalle $detalle): array {
                $texto = mb_strtolower(trim((string) ($detalle->descripcion ?? '')));
                $tieneReuniones = str_contains($texto, 'reunion') || str_contains($texto, 'reunión');
                $tieneTrabajos = str_contains($texto, 'trabajo');

                if (! ($tieneReuniones && $tieneTrabajos)) {
                    return [$detalle];
                }

                $periodo = trim((string) preg_replace(
                    '/falta\s+de\s+reuniones?\s+y\s+trabajos?/iu',
                    '',
                    (string) $detalle->descripcion,
                ));
                $periodo = trim($periodo, " \t\n\r\0\x0B-–");

                $sufijoServicio = $this->textoEsAlcantarillado($texto)
                    ? ' de alcantarillado'
                    : ' de agua potable';

                $montoTotal = round((float) $detalle->monto, 2);
                $montoReuniones = round($montoTotal / 2, 2);
                $montoTrabajos = round($montoTotal - $montoReuniones, 2);

                $reuniones = $detalle->replicate();
                $reuniones->id = $detalle->id;
                $reuniones->setRelation('pago', $detalle->pago);
                $reuniones->descripcion = trim('falta de reuniones'.$sufijoServicio.' '.($periodo !== '' ? $periodo : ''));
                $reuniones->monto = $montoReuniones;

                $trabajos = $detalle->replicate();
                $trabajos->id = $detalle->id;
                $trabajos->setRelation('pago', $detalle->pago);
                $trabajos->descripcion = trim('falta de trabajos'.$sufijoServicio.' '.($periodo !== '' ? $periodo : ''));
                $trabajos->monto = $montoTrabajos;

                return [$reuniones, $trabajos];
            })
            ->values();
    }

    public function conceptoLabel(string $concepto): string
    {
        return match ($concepto) {
            PagoDetalle::CONCEPTO_SERVICIO_MENSUAL => 'Servicio Agua Potable',
            PagoDetalle::CONCEPTO_MULTA => 'Multas',
            PagoDetalle::CONCEPTO_AFILIACION => 'Afiliación',
            PagoDetalle::CONCEPTO_APORTE => 'Aportes',
            PagoDetalle::CONCEPTO_OTRO => 'Otros conceptos',
            default => PagoDetalle::label($concepto),
        };
    }

    private function formatPeriodo(?string $periodo): string
    {
        if (! $periodo) {
            return '—';
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $periodo, $m)) {
            return $m[2].'/'.$m[1];
        }

        return $periodo;
    }

    /**
     * Agua / alcantarillado: una fila por lote con los meses pagados
     * (ej. «de enero a febrero»).
     *
     * @param  Collection<int, PagoDetalle>  $items
     * @return array<int, array<string, mixed>>
     */
    private function consolidarLineasServicioPorPeriodo(Collection $items, string $label): array
    {
        return $items
            ->groupBy(function (PagoDetalle $detalle): string {
                $pago = $detalle->pago;
                $loteId = $pago?->lote_afiliado_id;
                $afiliadoId = $pago?->afiliado_id;

                return $loteId ? 'l'.$loteId : 'a'.$afiliadoId;
            })
            ->map(function (Collection $grupo) use ($label): array {
                $ultimo = $grupo->sortByDesc(fn (PagoDetalle $d) => $d->pago?->fecha_pago?->timestamp ?? 0)->first();
                $base = $this->mapLineaReporte($ultimo ?? $grupo->first());

                $periodos = $grupo
                    ->pluck('periodo')
                    ->map(fn ($p) => trim((string) $p))
                    ->filter(fn (string $p): bool => (bool) preg_match('/^\d{4}-\d{2}$/', $p))
                    ->unique()
                    ->sort()
                    ->values();

                $desde = $periodos->first();
                $hasta = $periodos->last();
                $cantidadMeses = $periodos->count();

                $base['fecha'] = $this->rangoFechasDePagos($grupo)
                    ?? ($ultimo?->pago?->fecha_pago?->format('d/m/Y') ?? $base['fecha']);
                $base['detalle'] = $cantidadMeses > 0
                    ? $this->detalleMensualidadPagada($periodos)
                    : $this->detalleConRangoMeses($label, $desde, $hasta, $cantidadMeses);
                $base['periodo'] = $this->formatRangoPeriodos($desde, $hasta);
                $base['bolivianos'] = $this->montoMensualidad($grupo, $cantidadMeses);
                $base['recibo'] = $grupo->count() === 1
                    ? ($ultimo?->pago?->numero_recibo ?? $base['recibo'])
                    : $grupo->map(fn (PagoDetalle $d) => $d->pago?->numero_recibo)->filter()->unique()->implode(', ');

                return $base;
            })
            ->sortBy(fn (array $linea): string => ($linea['fecha'] ?? '').'-'.($linea['socio'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * Agua y alcantarillado se parten por la categoría real del lote.
     */
    private function claveGrupoReporte(PagoDetalle $detalle): string
    {
        $key = $this->cuentaConcepto($detalle)['key'];

        if ($key === 'afiliacion') {
            return $this->claveAfiliacion($detalle);
        }

        if (! in_array($key, ['servicio_agua_potable', 'servicio_alcantarillado'], true)) {
            return $key;
        }

        $slug = $this->categoriaDelDetalle($detalle)?->slug;

        return filled($slug) ? $key.':'.$slug : $key;
    }

    /**
     * Agua o alcantarillado, al contado o a plazo, según el lote registrado.
     */
    private function claveAfiliacion(PagoDetalle $detalle): string
    {
        $servicio = $this->servicioAfiliacion($detalle);
        if ($servicio === null) {
            return 'afiliacion';
        }

        return 'afiliacion:'.$servicio.':'.$this->modalidadAfiliacion($detalle);
    }

    private function servicioAfiliacion(PagoDetalle $detalle): ?string
    {
        $concepto = mb_strtolower((string) $detalle->concepto);
        $texto = mb_strtolower((string) $detalle->descripcion);

        if (str_contains($concepto, 'alcantarillado') || str_contains($texto, 'alcantarillado')) {
            return 'alcantarillado';
        }

        if (
            $concepto === PagoDetalle::CONCEPTO_AFILIACION_AGUA
            || str_contains($concepto, 'agua')
            || str_contains($texto, 'agua')
        ) {
            return 'agua';
        }

        return null;
    }

    private function modalidadAfiliacion(PagoDetalle $detalle): string
    {
        $forma = (string) ($detalle->pago?->lote?->forma_pago_afiliacion ?? '');
        if (in_array($forma, [Afiliado::FORMA_CONTADO, Afiliado::FORMA_PLAZO], true)) {
            return $forma;
        }

        $obs = mb_strtolower((string) ($detalle->pago?->observaciones ?? ''));
        if (str_contains($obs, 'a plazo')) {
            return Afiliado::FORMA_PLAZO;
        }

        return Afiliado::FORMA_CONTADO;
    }

    private function saldoAfiliacionServicio(PagoDetalle $detalle, string $clave): float
    {
        $lote = $detalle->pago?->lote;
        if ($lote === null) {
            return 0.0;
        }

        if (str_contains($clave, ':agua:')) {
            return round((float) $lote->saldo_afiliacion_agua, 2);
        }

        if (str_contains($clave, ':alcantarillado:')) {
            return round((float) $lote->saldo_afiliacion_alcantarillado, 2);
        }

        return round((float) $lote->saldo_afiliacion, 2);
    }

    private function bolivianosTexto(float $monto): string
    {
        return number_format($monto, 2, ',', '.');
    }

    /**
     * El abono de una afiliación a plazo no es el saldo. El saldo sale del lote.
     */
    private function esAbonoAfiliacionPlazo(PagoDetalle $detalle): bool
    {
        if (($this->cuentaConcepto($detalle)['key'] ?? '') !== 'afiliacion') {
            return false;
        }

        return $this->servicioAfiliacion($detalle) !== null
            && $this->modalidadAfiliacion($detalle) === Afiliado::FORMA_PLAZO;
    }

    /**
     * A plazo: una fila por lote que todavía debe, aunque el afiliado se repita.
     * El monto es el saldo guardado en el módulo de afiliados.
     *
     * @param  array<int, array{concepto: string, label: string, lineas: array<int, array<string, mixed>>, subtotal: float}>  $grupos
     * @return array<int, array{concepto: string, label: string, lineas: array<int, array<string, mixed>>, subtotal: float}>
     */
    private function unirDeudasAfiliacionPlazo(array $grupos, string $desde, string $hasta, ?int $socioId, ?int $categoriaId): array
    {
        $lotes = LoteAfiliado::query()
            ->with(['afiliado', 'otb'])
            ->where('forma_pago_afiliacion', Afiliado::FORMA_PLAZO)
            ->where(function ($query): void {
                $query->where('saldo_afiliacion_agua', '>', 0)
                    ->orWhere('saldo_afiliacion_alcantarillado', '>', 0);
            })
            ->whereDate('fecha_afiliacion', '<=', $hasta)
            ->when($socioId, fn ($query) => $query->where('afiliado_id', $socioId))
            ->when($categoriaId, fn ($query) => $query->where('categoria_id', $categoriaId))
            ->get();

        $porClave = [];
        foreach ($lotes as $lote) {
            foreach ([
                'agua' => round((float) $lote->saldo_afiliacion_agua, 2),
                'alcantarillado' => round((float) $lote->saldo_afiliacion_alcantarillado, 2),
            ] as $servicio => $saldo) {
                if ($saldo <= 0) {
                    continue;
                }

                $clave = 'afiliacion:'.$servicio.':plazo';
                $label = $this->etiquetaAfiliacion($clave);
                $porClave[$clave][] = $this->lineaDeudaAfiliacion($lote, $label, $saldo);
            }
        }

        foreach ($porClave as $clave => $lineas) {
            $lineas = collect($lineas)
                ->sortBy(fn (array $linea): string => mb_strtolower((string) ($linea['socio'] ?? '')).'-'.str_pad((string) ($linea['lote'] ?? ''), 8, '0', STR_PAD_LEFT))
                ->values()
                ->all();
            $grupo = [
                'concepto' => $clave,
                'label' => $this->etiquetaAfiliacion($clave),
                'lineas' => $lineas,
                'subtotal' => round((float) collect($lineas)->sum('bolivianos'), 2),
            ];
            $indice = collect($grupos)->search(fn (array $existente): bool => $existente['concepto'] === $clave);
            if ($indice === false) {
                $grupos[] = $grupo;

                continue;
            }

            $grupos[$indice] = $grupo;
        }

        return collect($grupos)
            ->sortBy(fn (array $grupo): string => $this->ordenGrupoReporte((string) $grupo['concepto']).'-'.mb_strtolower((string) $grupo['label']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function lineaDeudaAfiliacion(LoteAfiliado $lote, string $label, float $saldo): array
    {
        $afiliado = $lote->afiliado;

        return [
            'fecha' => $lote->fecha_afiliacion?->format('d/m/Y') ?? '—',
            'cantidad' => null,
            'socio' => $afiliado ? $this->nombreSocioReporte($afiliado, $lote) : '—',
            'ci' => $afiliado?->ciCompletoDisplay() ?? '—',
            'lote' => filled($lote->numero_lote) ? (string) $lote->numero_lote : '—',
            'afiliado_id' => $afiliado?->id,
            'lote_id' => $lote->id,
            'detalle' => 'debe Bs '.$this->bolivianosTexto($saldo),
            'periodo' => '—',
            'dolares' => 0.0,
            'bolivianos' => $saldo,
            'recibo' => '—',
            'pagado' => '—',
            'cuenta' => $label,
        ];
    }

    private function etiquetaAfiliacion(string $clave): string
    {
        return match ($clave) {
            'afiliacion:agua:contado' => 'Afiliación de agua al contado',
            'afiliacion:agua:plazo' => 'Afiliación de agua a plazo',
            'afiliacion:alcantarillado:contado' => 'Afiliación de alcantarillado al contado',
            'afiliacion:alcantarillado:plazo' => 'Afiliación de alcantarillado a plazo',
            default => 'Afiliación',
        };
    }

    private function ordenGrupoReporte(string $concepto): string
    {
        return match ($concepto) {
            'servicio_agua_potable:domiciliaria', 'servicio_agua_potable' => '0',
            'servicio_agua_potable:comercial' => '1',
            'servicio_agua_potable:industrial' => '2',
            'servicio_alcantarillado:domiciliaria', 'servicio_alcantarillado' => '3',
            'servicio_alcantarillado:comercial' => '4',
            'servicio_alcantarillado:industrial' => '5',
            'afiliacion', 'afiliacion:agua:contado' => '60',
            'afiliacion:agua:plazo' => '61',
            'afiliacion:alcantarillado:contado' => '62',
            'afiliacion:alcantarillado:plazo' => '63',
            'falta_trabajos', 'falta_trabajos_agua_potable' => '7',
            'falta_trabajos_alcantarillado' => '8',
            default => '9',
        };
    }

    /**
     * @param  Collection<int, PagoDetalle>  $items
     */
    private function etiquetaGrupoReporte(string $key, Collection $items): string
    {
        if (str_starts_with($key, 'afiliacion:')) {
            return $this->etiquetaAfiliacion($key);
        }

        $base = explode(':', $key, 2)[0];
        $categoria = $this->categoriaDelDetalle($items->first());

        if ($categoria === null || ! in_array($base, ['servicio_agua_potable', 'servicio_alcantarillado'], true)) {
            return $this->cuentaConcepto($items->first())['label'];
        }

        $nombre = mb_strtolower(trim($categoria->nombre));
        $servicio = $base === 'servicio_alcantarillado' ? 'Servicio de alcantarillado' : 'Servicio de agua';

        return trim($servicio.' '.$nombre);
    }

    private function categoriaDelDetalle(PagoDetalle $detalle): ?Categoria
    {
        $pago = $detalle->pago;
        if ($pago === null) {
            return null;
        }

        $pago->loadMissing(['lote.categoria', 'afiliado.lotes.categoria']);

        if ($pago->lote?->categoria) {
            return $pago->lote->categoria;
        }

        $lotes = $pago->afiliado?->lotes;
        if ($lotes !== null && $lotes->count() === 1) {
            return $lotes->first()?->categoria;
        }

        return null;
    }

    /**
     * Primera fecha en que pagó y última fecha en que canceló, tomadas de los recibos.
     *
     * @param  Collection<int, PagoDetalle>  $grupo
     */
    private function rangoFechasDePagos(Collection $grupo): ?string
    {
        $fechas = $grupo
            ->map(fn (PagoDetalle $detalle) => $detalle->pago?->fecha_pago)
            ->filter()
            ->sortBy(fn ($fecha) => $fecha->timestamp)
            ->values();

        if ($fechas->isEmpty()) {
            return null;
        }

        $inicio = $fechas->first()->format('d/m/Y');
        $fin = $fechas->last()->format('d/m/Y');

        return $inicio.' - '.$fin;
    }

    /**
     * Meses de agua o alcantarillado que el módulo ya marcó como pagados.
     *
     * @return array<string, true>
     */
    private function clavesCuotasPagadas(): array
    {
        return Cuota::query()
            ->where(function ($query): void {
                $query->where('estado', Cuota::ESTADO_PAGADA)
                    ->orWhere('saldo', '<=', 0);
            })
            ->get(['lote_afiliado_id', 'servicio_id', 'periodo'])
            ->mapWithKeys(fn (Cuota $cuota): array => [
                $cuota->lote_afiliado_id.'|'.$cuota->servicio_id.'|'.$cuota->periodo => true,
            ])
            ->all();
    }

    /**
     * Un recibo de mensualidad sin la cuota pagada de ese lote y ese mes no entra al reporte.
     *
     * @param  array<string, true>  $cuotasPagadas
     */
    private function mensualidadSinCuotaPagada(PagoDetalle $detalle, array $cuotasPagadas): bool
    {
        $key = $this->cuentaConcepto($detalle)['key'];
        if (! in_array($key, ['servicio_agua_potable', 'servicio_alcantarillado'], true)) {
            return false;
        }

        $loteId = $detalle->pago?->lote_afiliado_id;
        $periodo = trim((string) $detalle->periodo);
        $servicioId = $detalle->servicio_id ?: $this->servicioMensualId(
            $key === 'servicio_alcantarillado' ? 'alcantarillado' : 'agua',
        );

        if (! $loteId || ! $servicioId || ! preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            return true;
        }

        return ! isset($cuotasPagadas[$loteId.'|'.$servicioId.'|'.$periodo]);
    }

    private function servicioMensualId(string $tipo): ?int
    {
        $id = Servicio::query()->where('tipo', $tipo)->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Tarifa de la categoría por cada mes que la cuota figura pagada.
     *
     * @param  Collection<int, PagoDetalle>  $grupo
     */
    private function montoMensualidad(Collection $grupo, int $meses): float
    {
        $suma = round((float) $grupo->sum('monto'), 2);
        if ($meses < 1) {
            return $suma;
        }

        $detalle = $grupo->first();
        $categoria = $detalle ? $this->categoriaDelDetalle($detalle) : null;
        if ($categoria === null) {
            return $suma;
        }

        $tarifa = match ($this->cuentaConcepto($detalle)['key']) {
            'servicio_agua_potable' => (float) $categoria->tarifa_agua,
            'servicio_alcantarillado' => (float) $categoria->tarifa_alcantarillado,
            default => 0.0,
        };

        if ($tarifa <= 0) {
            return $suma;
        }

        return round($tarifa * $meses, 2);
    }

    /**
     * Meses cobrados en palabras. Un tramo seguido del mismo año queda
     * «de enero a febrero». Si cambia de año, el año va en el texto.
     *
     * @param  Collection<int, string>  $periodos
     */
    private function detalleMensualidadPagada(Collection $periodos): string
    {
        $bloques = [];
        $actual = [];
        $anterior = null;

        foreach ($periodos as $periodo) {
            [$anio, $mes] = explode('-', $periodo);
            $fecha = Carbon::create((int) $anio, (int) $mes, 1)->startOfDay();
            if ($anterior !== null && $anterior->copy()->addMonth()->ne($fecha)) {
                $bloques[] = $actual;
                $actual = [];
            }
            $actual[] = $fecha;
            $anterior = $fecha;
        }

        if ($actual !== []) {
            $bloques[] = $actual;
        }

        $anios = collect($bloques)
            ->flatten(1)
            ->map(fn (Carbon $fecha): int => $fecha->year)
            ->unique();
        $cruzaAnio = $anios->count() > 1;

        $textos = array_map(function (array $bloque) use ($cruzaAnio): string {
            /** @var Carbon $inicio */
            $inicio = $bloque[0];
            /** @var Carbon $fin */
            $fin = $bloque[array_key_last($bloque)];
            $mesInicio = $this->nombreMes($inicio->format('m'));
            $mesFin = $this->nombreMes($fin->format('m'));

            if ($inicio->equalTo($fin)) {
                return $cruzaAnio ? $mesInicio.' '.$inicio->year : $mesInicio;
            }

            if ($inicio->year === $fin->year) {
                $rango = 'de '.$mesInicio.' a '.$mesFin;

                return $cruzaAnio ? $rango.' '.$fin->year : $rango;
            }

            return 'de '.$mesInicio.' '.$inicio->year.' a '.$mesFin.' '.$fin->year;
        }, $bloques);

        return implode(' y ', $textos);
    }

    private function esCuentaServicioConPeriodo(string $cuentaKey): bool
    {
        return in_array($cuentaKey, [
            'servicio_agua_potable',
            'servicio_alcantarillado',
            'pago_trabajo',
            'pago_trabajo_alcantarillado',
            'pago_trabajo_agua',
            'reuniones_alcantarillado',
            'reuniones_agua',
        ], true);
    }

    /**
     * Ejemplos:
     * - un mes: «… — agosto 2026»
     * - mismo año: «… — de agosto a diciembre 2026 (5 meses)»
     * - distinto año: «… — de agosto 2025 a marzo 2026 (8 meses)»
     */
    private function detalleConRangoMeses(
        string $texto,
        ?string $periodoDesde,
        ?string $periodoHasta,
        ?int $cantidadMeses = null,
    ): string {
        if (! is_string($periodoDesde) || ! preg_match('/^\d{4}-\d{2}$/', $periodoDesde)) {
            $periodoDesde = is_string($periodoHasta) && preg_match('/^\d{4}-\d{2}$/', $periodoHasta)
                ? $periodoHasta
                : null;
        }
        if (! is_string($periodoHasta) || ! preg_match('/^\d{4}-\d{2}$/', $periodoHasta)) {
            $periodoHasta = $periodoDesde;
        }

        if ($periodoDesde === null || $periodoHasta === null) {
            return $texto;
        }

        $rango = $this->rangoMesesEnPalabras($periodoDesde, $periodoHasta);
        if ($rango === '') {
            return $texto;
        }

        if (str_contains(mb_strtolower($texto), mb_strtolower($rango))) {
            return $texto;
        }

        $inicio = Carbon::createFromFormat('Y-m', $periodoDesde)->startOfMonth();
        $finMes = Carbon::createFromFormat('Y-m', $periodoHasta)->startOfMonth();
        $span = (int) $inicio->diffInMonths($finMes) + 1;
        $meses = $cantidadMeses ?? $span;

        $sufijo = '';
        if ($meses > 1 && $meses === $span) {
            $sufijo = $meses >= 12
                ? ' (año completo)'
                : ($meses === 6 ? ' (medio año)' : ' ('.$meses.' meses)');
        }

        return trim($texto.' — '.$rango.$sufijo);
    }

    /** «agosto 2026» | «de agosto a diciembre 2026» | «de agosto 2025 a marzo 2026» */
    private function rangoMesesEnPalabras(string $periodoDesde, string $periodoHasta): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $periodoDesde, $a)
            || ! preg_match('/^(\d{4})-(\d{2})$/', $periodoHasta, $b)
        ) {
            return '';
        }

        $mesIni = $this->nombreMes($a[2]);
        $mesFin = $this->nombreMes($b[2]);
        $anioIni = $a[1];
        $anioFin = $b[1];

        if ($periodoDesde === $periodoHasta) {
            return $mesIni.' '.$anioIni;
        }

        if ($anioIni === $anioFin) {
            return 'de '.$mesIni.' a '.$mesFin.' '.$anioFin;
        }

        return 'de '.$mesIni.' '.$anioIni.' a '.$mesFin.' '.$anioFin;
    }

    private function nombreMes(string $mm): string
    {
        return match ($mm) {
            '01' => 'enero',
            '02' => 'febrero',
            '03' => 'marzo',
            '04' => 'abril',
            '05' => 'mayo',
            '06' => 'junio',
            '07' => 'julio',
            '08' => 'agosto',
            '09' => 'septiembre',
            '10' => 'octubre',
            '11' => 'noviembre',
            '12' => 'diciembre',
            default => $mm,
        };
    }

    private function formatRangoPeriodos(?string $desde, ?string $hasta): string
    {
        $a = $this->formatPeriodo($desde);
        $b = $this->formatPeriodo($hasta);

        if ($a === '—' && $b === '—') {
            return '—';
        }

        if ($a === $b || $b === '—') {
            return $a;
        }

        if ($a === '—') {
            return $b;
        }

        return $a.' a '.$b;
    }

    private function periodoEnPalabras(?string $periodo): string
    {
        if (! is_string($periodo) || ! preg_match('/^(\d{4})-(\d{2})$/', $periodo, $m)) {
            return '';
        }

        return $this->nombreMes($m[2]).' '.$m[1];
    }

    /**
     * @return array<int, string>
     */
    private function conceptoOrder(): array
    {
        return [
            ...PagoDetalle::conceptosCuota(),
            PagoDetalle::CONCEPTO_SERVICIO_MENSUAL,
            PagoDetalle::CONCEPTO_APORTE,
            PagoDetalle::CONCEPTO_AFILIACION,
            PagoDetalle::CONCEPTO_MULTA,
            PagoDetalle::CONCEPTO_OTRO,
        ];
    }
}
