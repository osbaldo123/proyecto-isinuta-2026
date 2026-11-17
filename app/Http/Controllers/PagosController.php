<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\StorePagoRequest;
use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Servicio;
use App\Models\TipoAfiliacion;
use App\Services\ActivityLogger;
use App\Services\CalculadorMultasService;
use App\Services\DeudaAfiliadoService;
use App\Services\EmitirReciboAfiliacionService;
use App\Services\Exports\PagoDocumentExportService;
use App\Services\MultasConsultaService;
use App\Services\Pagos\CobroService;
use App\Services\Pagos\MesesPorCobrarService;
use App\Services\ReciboPropositoService;
use App\Support\MultaTexto;
use App\Support\TextoPresentacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PagosController extends Controller
{
    public function __construct(
        private readonly DeudaAfiliadoService $deudaAfiliado,
        private readonly CalculadorMultasService $calculadorMultas,
        private readonly MultasConsultaService $consulta,
        private readonly ActivityLogger $activityLogger,
        private readonly PagoDocumentExportService $documentExport,
        private readonly ReciboPropositoService $reciboProposito,
        private readonly EmitirReciboAfiliacionService $reciboAfiliacion,
        private readonly CobroService $cobro,
        private readonly MesesPorCobrarService $mesesPorCobrar,
    ) {}

    public function index(Request $request): Response
    {
        if ($this->consulta->puedeVerListadoPagos($request->user())) {
            return $this->indexGestion($request);
        }

        return $this->indexConsulta($request);
    }

    public function create(Request $request): Response
    {
        $selectorId = $request->integer('afiliado_id') ?: null;
        [$afiliado, $lote] = $selectorId
            ? $this->resolverAfiliadoDesdeSelector($selectorId)
            : [null, null];

        $selectorParaForm = $lote?->id ?? $afiliado?->id;

        $afiliados = LoteAfiliado::query()
            ->with('afiliado:id,nombres,apellidos,ci')
            ->where('estado', '!=', Afiliado::ESTADO_BAJA)
            ->whereHas('afiliado', fn ($query) => $query->where('estado', '!=', Afiliado::ESTADO_BAJA))
            ->orderByRaw('CAST(numero_lote AS UNSIGNED), numero_lote')
            ->get()
            ->map(fn (LoteAfiliado $item): array => [
                'id' => $item->id,
                'codigo' => 'Lote '.($item->numero_lote ?: '—'),
                'numero_lote' => (string) ($item->numero_lote ?: ''),
                'nombre_completo' => $item->afiliado?->nombre_completo ?? '',
                'ci' => $item->afiliado?->ci ?? '',
            ])
            ->values();

        return Inertia::render('pagos/Create', [
            'afiliados' => $afiliados,
            'afiliadoSeleccionado' => ($afiliado && $lote)
                ? $this->datosAfiliadoParaCobro($afiliado, $request, $selectorParaForm)
                : null,
            'metodosPago' => [
                ['value' => Pago::METODO_EFECTIVO, 'label' => 'Efectivo'],
                ['value' => Pago::METODO_QR, 'label' => 'QR'],
            ],
            'conceptosServicio' => $this->conceptosDeServicio(),
            'conceptosAfiliacion' => $this->conceptosDeAfiliacion(),
            'conceptosLibres' => [
                ['value' => PagoDetalle::CONCEPTO_APORTE, 'label' => 'Aporte'],
                ['value' => PagoDetalle::CONCEPTO_OTRO, 'label' => 'Otro concepto'],
            ],
            'multaId' => $request->integer('multa_id') ?: null,
        ]);
    }

    /**
     * Agua domiciliar, agua comercial, alcantarillado domiciliar y alcantarillado comercial.
     *
     * @return list<array{key: string, concepto: string, label: string, descripcion: string, monto: float, servicio_id: int}>
     */
    private function conceptosDeServicio(): array
    {
        $orden = [
            Categoria::SLUG_DOMICILIARIA => 0,
            Categoria::SLUG_COMERCIAL => 1,
        ];

        $categorias = Categoria::query()
            ->where('activo', true)
            ->whereIn('slug', array_keys($orden))
            ->get()
            ->sortBy(fn (Categoria $categoria): int => $orden[$categoria->slug] ?? 9)
            ->values();

        $servicios = collect([
            Servicio::query()->where('activo', true)->where('tipo', 'agua')->orderBy('id')->first(),
            Servicio::query()->where('activo', true)->where('tipo', 'alcantarillado')->orderBy('id')->first(),
        ])->filter();

        $opciones = [];

        foreach ($servicios as $servicio) {
            $nombreServicio = $servicio->esAgua() ? 'Agua' : 'Alcantarillado';
            $concepto = $servicio->esAgua()
                ? PagoDetalle::CONCEPTO_CONSUMO_AGUA
                : PagoDetalle::CONCEPTO_PAGO_ALCANTARILLADO;

            foreach ($categorias as $categoria) {
                $categoriaNombre = mb_convert_case(trim((string) $categoria->nombre), MB_CASE_TITLE, 'UTF-8');
                $etiqueta = "{$nombreServicio} {$categoriaNombre}";

                $opciones[] = [
                    'key' => $servicio->tipo.'-'.$categoria->id,
                    'concepto' => $concepto,
                    'label' => $etiqueta,
                    'descripcion' => $etiqueta,
                    'monto' => round($servicio->tarifaParaCategoria($categoria), 2),
                    'servicio_id' => $servicio->id,
                ];
            }
        }

        return $opciones;
    }

    /**
     * Afiliación de agua y de alcantarillado, al contado y a plazo.
     *
     * @return list<array{key: string, concepto: string, label: string, descripcion: string, monto: float, modalidad: string, codigo: string}>
     */
    private function conceptosDeAfiliacion(): array
    {
        $orden = [
            TipoAfiliacion::CODIGO_AGUA => 0,
            TipoAfiliacion::CODIGO_ALCANTARILLADO => 1,
        ];

        $tipos = TipoAfiliacion::query()
            ->where('activo', true)
            ->whereIn('codigo', array_keys($orden))
            ->get()
            ->sortBy(fn (TipoAfiliacion $tipo): int => $orden[$tipo->codigo] ?? 9)
            ->values();

        if ($tipos->isEmpty()) {
            $tipos = collect([
                new TipoAfiliacion([
                    'codigo' => TipoAfiliacion::CODIGO_AGUA,
                    'nombre' => 'Afiliación de servicio de agua potable',
                    'monto' => PagoDetalle::MONTO_AFILIACION_AGUA,
                    'monto_plazo' => PagoDetalle::MONTO_AFILIACION_AGUA,
                ]),
                new TipoAfiliacion([
                    'codigo' => TipoAfiliacion::CODIGO_ALCANTARILLADO,
                    'nombre' => 'Afiliación de servicio de alcantarillado',
                    'monto' => PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO,
                    'monto_plazo' => PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO,
                ]),
            ]);
        }

        $opciones = [];

        foreach ($tipos as $tipo) {
            $nombre = $tipo->codigo === TipoAfiliacion::CODIGO_AGUA ? 'agua' : 'alcantarillado';

            $opciones[] = [
                'key' => 'afiliacion-'.$tipo->codigo.'-contado',
                'concepto' => $tipo->conceptoCobro(),
                'label' => "Afiliación {$nombre} al contado",
                'descripcion' => "Afiliación {$nombre} al contado",
                'monto' => round($tipo->montoContado(), 2),
                'modalidad' => Afiliado::FORMA_CONTADO,
                'codigo' => $tipo->codigo,
            ];
            $opciones[] = [
                'key' => 'afiliacion-'.$tipo->codigo.'-plazo',
                'concepto' => $tipo->conceptoCobro(),
                'label' => "Afiliación {$nombre} a plazo",
                'descripcion' => "Afiliación {$nombre} a plazo",
                'monto' => round($tipo->montoPlazo(), 2),
                'modalidad' => Afiliado::FORMA_PLAZO,
                'codigo' => $tipo->codigo,
            ];
        }

        return $opciones;
    }

    public function store(StorePagoRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        [$afiliado, $lote] = $this->resolverAfiliadoDesdeSelector((int) $validated['afiliado_id']);
        abort_unless($afiliado && $lote, 404);

        $pagos = $this->cobro->registrar($afiliado, $lote, $validated, $request->user());

        $numeros = collect($pagos)->pluck('numero_recibo')->implode(', ');
        $this->activityLogger->log(
            $request->user(),
            'Pagos',
            count($pagos) > 1
                ? 'Registro de '.count($pagos).' recibos por propósito: '.$numeros
                : "Registro de pago {$pagos[0]->numero_recibo}",
        );

        $mensaje = count($pagos) > 1
            ? 'Se emitieron '.count($pagos).' recibos separados por propósito: '.$numeros
            : 'Pago registrado correctamente.';

        return redirect()
            ->route('pagos.show', $pagos[0]->id)
            ->with('success', $mensaje)
            ->with('recibos_emitidos', collect($pagos)->map(fn (Pago $p): array => [
                'id' => $p->id,
                'numero_recibo' => $p->numero_recibo,
                'tipo_recibo' => $p->tipo_recibo,
                'proposito_label' => $p->proposito_label,
            ])->all());
    }

    public function show(Request $request, int $id): Response
    {
        $pago = Pago::query()
            ->with([
                'afiliado',
                'lote.otb',
                'lote.categoria',
                'cobrador',
                'anuladoPor',
                'detalles.servicio',
                'detalles.multa',
            ])
            ->findOrFail($id);

        $this->consulta->assertPuedeVerPago($request, $pago);

        return Inertia::render('pagos/Show', [
            'pago' => $this->datosRecibo($pago),
            'recibos_emitidos' => $request->session()->get('recibos_emitidos'),
            'filtros_retorno' => $this->parametrosListado($request),
        ]);
    }

    /**
     * Todos los recibos de un cobro (uno por propósito), listos para imprimir juntos.
     * Sin tope: si el cobro tiene 4, 10 o N recibos, se envían todos a la vista de impresión.
     */
    public function imprimirCobro(Request $request, string $cobro): Response
    {
        $pagos = Pago::query()
            ->with(['afiliado', 'lote.otb', 'lote.categoria', 'cobrador', 'anuladoPor', 'detalles.servicio', 'detalles.multa'])
            ->where('cobro_id', $cobro)
            ->orderBy('id')
            ->get();

        abort_if($pagos->isEmpty(), 404);
        $pagos->each(fn (Pago $pago) => $this->consulta->assertPuedeVerPago($request, $pago));

        return Inertia::render('pagos/ImprimirCobro', [
            'recibos' => $pagos->map(fn (Pago $pago): array => $this->datosRecibo($pago))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosRecibo(Pago $pago): array
    {
        $cuenta = $this->reciboProposito->cuentaDesdePago($pago);

        $fechaPagoDisplay = $pago->fecha_pago->format('d/m/Y');
        $lineas = $pago->detalles->map(function (PagoDetalle $d) use ($cuenta, $fechaPagoDisplay): array {
            return [
                'concepto' => $d->concepto,
                'descripcion' => $d->descripcion,
                'texto' => $this->reciboProposito->textoConcepto($d, $cuenta, $fechaPagoDisplay),
                'monto' => (float) $d->monto,
                'monto_usd' => 0.0,
                'periodo' => $d->periodo,
            ];
        })->all();
        $contexto = $this->afiliadoDePago($pago);

        return [
            'id' => $pago->id,
            'numero_recibo' => $pago->numero_recibo,
            'tipo_recibo' => $cuenta['tipo'],
            'titulo_recibo' => $this->reciboProposito->tituloDocumento($cuenta['tipo']),
            'proposito' => $cuenta['key'],
            'proposito_label' => TextoPresentacion::sinDuplicados((string) $cuenta['label']),
            'fecha_pago' => $pago->fecha_pago->format('Y-m-d'),
            'fecha_pago_display' => $fechaPagoDisplay,
            'monto_total' => (float) $pago->monto_total,
            'monto_en_letras' => $this->reciboProposito->montoEnLetras((float) $pago->monto_total),
            'metodo_pago' => $pago->metodo_pago,
            'metodo_pago_label' => $pago->metodo_pago === Pago::METODO_QR ? 'QR' : 'Efectivo',
            'estado' => $pago->estado,
            'observaciones' => $pago->observaciones,
            'cobrador' => $pago->cobrador?->name,
            'rec_manual' => 0,
            'lote_afiliado_id' => $pago->lote_afiliado_id,
            'afiliado' => [
                'nombre_completo' => $pago->afiliado->nombre_completo,
                'identidad' => $contexto->identidadDocumento(),
                'numero_lote' => $contexto->numero_lote,
                'codigo_propiedad' => $contexto->codigoPropiedadDisplay(),
                'ci' => $pago->afiliado->ci,
                'otb' => $pago->lote?->otb?->nombre ?? $contexto->otb?->nombre,
                'categoria' => $this->categoriaDelPago($pago),
            ],
            'detalles' => $this->lineasReciboSinDuplicados($lineas),
            'emitido_en' => $pago->{$pago->getCreatedAtColumn()}?->format('d/m/Y H:i'),
            'anulacion' => $pago->estaAnulado() ? [
                'fecha' => $pago->anulado_en?->format('d/m/Y H:i'),
                'usuario' => $pago->anuladoPor?->name,
                'motivo' => $pago->motivo_anulacion,
            ] : null,
            'cobro_id' => $pago->cobro_id,
            'recibos_del_cobro' => $pago->cobro_id
                ? $pago->recibosDelCobro()
                    ->orderBy('id')
                    ->get(['id', 'numero_recibo', 'tipo_recibo', 'proposito_label', 'monto_total', 'estado'])
                    ->map(fn (Pago $recibo): array => [
                        'id' => $recibo->id,
                        'numero_recibo' => $recibo->numero_recibo,
                        'tipo_recibo' => $recibo->tipo_recibo,
                        'proposito_label' => TextoPresentacion::sinDuplicados((string) $recibo->proposito_label),
                        'monto_total' => (float) $recibo->monto_total,
                        'estado' => $recibo->estado,
                    ])
                    ->all()
                : [],
        ];
    }

    public function export(Request $request, int $id, string $formato): StreamedResponse
    {
        abort_unless($formato === 'xlsx', 404);

        $pago = Pago::query()
            ->with(['afiliado.lotes.otb', 'lote.otb', 'cobrador', 'detalles'])
            ->findOrFail($id);

        $this->consulta->assertPuedeVerPago($request, $pago);

        return $this->documentExport->exportRecibo($pago, $formato);
    }

    private function indexGestion(Request $request): Response
    {
        $busqueda = trim((string) $request->query('q', ''));
        $fechaDesde = trim((string) $request->query('fecha_desde', ''));
        $fechaHasta = trim((string) $request->query('fecha_hasta', ''));
        $concepto = trim((string) $request->query('concepto', ''));
        $afiliadoId = $request->integer('afiliado_id') ?: null;

        $query = Pago::query()
            ->with(['afiliado:id,nombres,apellidos,ci', 'lote.otb:id,nombre', 'cobrador:id,name', 'detalles.servicio'])
            ->buscar($busqueda)
            ->latest('fecha_pago')
            ->latest('id');

        if ($fechaDesde !== '') {
            $query->whereDate('fecha_pago', '>=', $fechaDesde);
        }

        if ($fechaHasta !== '') {
            $query->whereDate('fecha_pago', '<=', $fechaHasta);
        }

        if ($afiliadoId) {
            $loteFiltro = LoteAfiliado::query()->with(['afiliado', 'categoria'])->find($afiliadoId);
            if ($loteFiltro?->afiliado) {
                $this->reciboAfiliacion->asegurarRecibosFaltantes(
                    $loteFiltro->afiliado,
                    $loteFiltro,
                    $request->user(),
                );
                $query->where('lote_afiliado_id', $loteFiltro->id);
            } else {
                $query->where('afiliado_id', $afiliadoId);
            }
        }

        if ($concepto !== '') {
            $query->whereHas('detalles', function ($q) use ($concepto): void {
                if ($concepto === PagoDetalle::CONCEPTO_AFILIACION) {
                    $q->where(function ($inner): void {
                        $inner->where('concepto', PagoDetalle::CONCEPTO_AFILIACION)
                            ->orWhere('concepto', 'like', 'afiliacion_%');
                    });

                    return;
                }

                if ($concepto === PagoDetalle::CONCEPTO_MULTA) {
                    $q->whereIn('concepto', PagoDetalle::conceptosMultaOFalta());

                    return;
                }

                $q->where('concepto', $concepto);
            });
        }

        $pagos = $query->paginate(20)->withQueryString();

        $afiliadoFiltro = null;
        if ($afiliadoId) {
            [$persona, $loteCtx] = $this->resolverAfiliadoDesdeSelector($afiliadoId);
            $afiliadoFiltro = $persona;
            if ($loteCtx) {
                $afiliadoFiltro?->conLote($loteCtx);
            }
        }

        return Inertia::render('pagos/Index', [
            'modo' => 'gestion',
            'pagos' => $pagos->through(fn (Pago $p): array => $this->mapPago($p)),
            'filtros' => [
                'q' => $busqueda,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'concepto' => $concepto,
                'afiliado_id' => $afiliadoId,
            ],
            'conceptos' => $this->conceptosFiltro(),
            'afiliado' => $afiliadoFiltro ? [
                'id' => $afiliadoFiltro->id,
                'lote_id' => $afiliadoFiltro->loteEnContexto()?->id,
                'nombre_completo' => $afiliadoFiltro->nombre_completo,
                'ci' => $afiliadoFiltro->ci,
                'numero_lote' => $afiliadoFiltro->numero_lote,
                'otb' => $afiliadoFiltro->otb?->nombre,
                'estado' => $afiliadoFiltro->estado,
                'total_multas_pendientes' => 0,
            ] : null,
            'mensaje' => null,
            'busqueda' => ['ci' => ''],
            'consultaVinculada' => false,
        ]);
    }

    private function indexConsulta(Request $request): Response
    {
        ['afiliado' => $afiliado, 'mensaje' => $mensaje] = $this->consulta->resolverAfiliadoConsulta($request);

        $pagos = $afiliado
            ? $this->consulta->pagosDeAfiliado($afiliado, $request)->through(fn (Pago $p): array => $this->mapPago($p))
            : $this->pagosVacios();

        return Inertia::render('pagos/Index', [
            'modo' => 'consulta',
            'pagos' => $pagos,
            'filtros' => [
                'q' => '',
                'fecha_desde' => '',
                'fecha_hasta' => '',
                'concepto' => '',
                'afiliado_id' => null,
            ],
            'conceptos' => $this->conceptosFiltro(),
            'afiliado' => $afiliado ? $this->consulta->resumenAfiliado($afiliado) : null,
            'mensaje' => $mensaje,
            'busqueda' => [
                'ci' => (string) $request->query('ci', ''),
            ],
            'consultaVinculada' => $this->consulta->esConsultaVinculada($request->user()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPago(Pago $p): array
    {
        $p->loadMissing(['detalles.servicio', 'afiliado', 'lote.otb']);
        $ctx = $this->afiliadoDePago($p);

        $cuenta = $this->reciboProposito->cuentaDesdePago($p);
        $tipo = $cuenta['tipo'];
        $label = $p->proposito_label ?: $cuenta['label'];

        return [
            'id' => $p->id,
            'numero_recibo' => $p->numero_recibo,
            'tipo_recibo' => $tipo ?: 'ingreso',
            'proposito_label' => $label ? TextoPresentacion::sinDuplicados((string) $label) : $label,
            'fecha_pago' => $p->fecha_pago->format('Y-m-d'),
            'afiliado' => [
                'id' => $p->afiliado->id,
                'nombre_completo' => $p->afiliado->nombre_completo,
                'ci' => $p->afiliado->ci,
                'numero_lote' => $ctx->numero_lote,
                'otb' => $p->lote?->otb?->nombre ?? $ctx->otb?->nombre,
            ],
            'monto_total' => (float) $p->monto_total,
            'metodo_pago' => $p->metodo_pago,
            'estado' => $p->estado,
            'cobrador' => $p->cobrador?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pagosVacios(): array
    {
        return [
            'data' => [],
            'links' => [],
            'current_page' => 1,
            'last_page' => 1,
            'total' => 0,
        ];
    }

    /**
     * Filtro de recibos: solo conceptos útiles en caja (sin microvariantes que nadie usa).
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function conceptosFiltro(): array
    {
        return [
            ['value' => PagoDetalle::CONCEPTO_CONSUMO_AGUA, 'label' => 'Consumo de agua'],
            ['value' => PagoDetalle::CONCEPTO_PAGO_ALCANTARILLADO, 'label' => 'Pago de alcantarillado'],
            ['value' => PagoDetalle::CONCEPTO_MULTA, 'label' => 'Multas'],
            ...collect(TipoAfiliacion::opcionesActivas())
                ->map(fn (array $tipo): array => [
                    'value' => $tipo['concepto'],
                    'label' => $tipo['nombre'],
                ])
                ->all(),
            ['value' => PagoDetalle::CONCEPTO_APORTE, 'label' => 'Aporte'],
            ['value' => PagoDetalle::CONCEPTO_OTRO, 'label' => 'Otro'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosAfiliadoParaCobro(Afiliado $afiliado, Request $request, ?int $selectorId = null): array
    {
        $loteSeleccionado = $afiliado->loteEnContexto();
        $this->calculadorMultas->evaluarYGenerar($afiliado, $request->user());
        $this->calculadorMultas->actualizarEstadoPorMora($afiliado->fresh()->conLote($loteSeleccionado));

        $afiliado = $afiliado->fresh()->conLote($loteSeleccionado?->fresh());
        $lote = $afiliado->loteEnContexto();
        $lote?->loadMissing(['otb', 'serviciosActivos', 'categoria']);
        if ($lote) {
            $this->calculadorMultas->evaluarLote($lote, $request->user());
        }
        $estado = $this->deudaAfiliado->calcular($afiliado);
        $cuotasDelLote = $lote
            ? Cuota::query()
                ->with('servicio:id,nombre,tipo')
                ->where('lote_afiliado_id', $lote->id)
                ->where('saldo', '>', 0)
                ->orderBy('periodo')
                ->orderBy('servicio_id')
                ->get()
            : collect();
        $multasDelLote = $lote
            ? Multa::query()
                ->with(['trabajo.otb', 'lote.otb'])
                ->where('lote_afiliado_id', $lote->id)
                ->where('estado', Multa::ESTADO_PENDIENTE)
                ->orderByDesc('fecha_generacion')
                ->get()
                ->unique(fn (Multa $multa): string => ($multa->trabajo_id ?: 'mora-'.$multa->id).'-'.$multa->motivo)
                ->values()
            : collect();

        return [
            'id' => $selectorId ?? $lote?->id ?? $afiliado->id,
            'afiliado_id' => $afiliado->id,
            'lote_afiliado_id' => $lote?->id,
            'codigo' => 'Lote '.($lote?->numero_lote ?: '—'),
            'nombre_completo' => $afiliado->nombre_completo,
            'identidad' => $afiliado->identidadDocumento(),
            'numero_lote' => $lote?->numero_lote,
            'codigo_propiedad' => $afiliado->codigoPropiedadDisplay(),
            'otb' => $lote?->otb?->nombre,
            'ci' => $afiliado->ci,
            'estado' => $lote?->estado ?? $afiliado->estado,
            'categoria' => $lote?->categoria?->nombre
                ?? LoteAfiliado::labelCategoria($lote?->categoria_propiedad),
            'forma_pago_afiliacion' => $lote?->forma_pago_afiliacion,
            'forma_pago_afiliacion_label' => match ((string) ($lote?->forma_pago_afiliacion ?? '')) {
                Afiliado::FORMA_CONTADO => 'Al contado',
                Afiliado::FORMA_PLAZO => 'A plazo',
                default => null,
            },
            'meses_pendientes' => $estado['meses_pendientes'],
            'meses_pagados' => $estado['meses_pagados'],
            'tarifa_mensual' => $estado['tarifa_mensual'],
            'tarifa_agua' => $lote?->categoria ? (float) $lote->categoria->tarifa_agua : null,
            'tarifa_alcantarillado' => $lote?->categoria ? (float) $lote->categoria->tarifa_alcantarillado : null,
            'montos_afiliacion' => [
                'agua' => (float) ($lote?->monto_afiliacion_agua
                    ?: (TipoAfiliacion::query()->where('codigo', TipoAfiliacion::CODIGO_AGUA)->value('monto')
                        ?? PagoDetalle::MONTO_AFILIACION_AGUA)),
                'alcantarillado' => (float) ($lote?->monto_afiliacion_alcantarillado
                    ?: (TipoAfiliacion::query()->where('codigo', TipoAfiliacion::CODIGO_ALCANTARILLADO)->value('monto')
                        ?? PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO)),
            ],
            'tipos_afiliacion' => TipoAfiliacion::opcionesActivas(),
            'total_deuda' => $estado['total_deuda'],
            'total_cuotas_pendientes' => round((float) $cuotasDelLote->sum(fn (Cuota $cuota): float => (float) $cuota->saldo), 2),
            'total_multas_pendientes' => round((float) $multasDelLote->sum(fn (Multa $multa): float => (float) ($multa->saldo ?? $multa->monto)), 2),
            'saldo_afiliacion' => $estado['saldo_afiliacion'],
            'saldo_afiliacion_agua' => (float) ($lote?->saldo_afiliacion_agua ?? 0),
            'saldo_afiliacion_alcantarillado' => (float) ($lote?->saldo_afiliacion_alcantarillado ?? 0),
            'multas_pendientes' => $multasDelLote
                ->map(function (Multa $multa): array {
                    $etiqueta = match ($multa->motivo) {
                        Multa::MOTIVO_MORA_TRIMESTRAL, Multa::MOTIVO_MORA_ANUAL => MultaTexto::titulo($multa),
                        Multa::MOTIVO_FALTA_TRABAJO => trim(implode(' ', array_filter([
                            $multa->trabajo?->tipo,
                            $multa->trabajo?->otb?->nombre,
                        ]))) ?: ($multa->descripcion ?: 'Trabajo'),
                        default => $multa->descripcion ?: 'Multa',
                    };
                    $concepto = $this->deudaAfiliado->conceptoCobroMulta($multa);
                    $descripcion = $multa->descripcion ?: $etiqueta;
                    $recibo = $this->reciboProposito->clasificar([
                        'concepto' => $concepto,
                        'descripcion' => $descripcion,
                        'multa_id' => $multa->id,
                    ]);

                    return [
                        'id' => $multa->id,
                        'fecha_generacion' => $multa->fecha_generacion->format('Y-m-d'),
                        'motivo' => $multa->motivo,
                        'gestion' => $multa->gestion,
                        'periodo' => $multa->periodo,
                        'descripcion' => $multa->descripcion,
                        'descripcion_cobro' => $descripcion,
                        'monto' => (float) ($multa->saldo ?? $multa->monto),
                        'concepto' => $concepto,
                        'etiqueta' => $etiqueta,
                        'titulo' => MultaTexto::titulo($multa),
                        'detalle' => MultaTexto::detalle($multa),
                        'recibo' => $recibo,
                        'texto' => $this->reciboProposito->textoConcepto(['descripcion' => $descripcion], $recibo),
                    ];
                })
                ->all(),
            'cuotas_pendientes' => $cuotasDelLote
                ->map(fn (Cuota $cuota): array => [
                    'id' => $cuota->id,
                    'periodo' => $cuota->periodo,
                    'fecha_vencimiento' => $cuota->fecha_vencimiento->format('Y-m-d'),
                    'vencida' => $cuota->vencida(),
                    'monto' => (float) $cuota->saldo,
                    'servicio_id' => $cuota->servicio_id,
                    'servicio' => $cuota->servicio?->nombre,
                    'concepto' => PagoDetalle::conceptoDesdeServicio($cuota->servicio),
                    'cuenta' => trim('Lote '.($lote->numero_lote ?: '—').' ('.$afiliado->nombre_completo.')'),
                ])
                ->all(),
            'servicios' => ($lote?->serviciosActivos ?? collect())
                ->map(fn (Servicio $s): array => [
                    'id' => $s->id,
                    'codigo' => $s->codigo,
                    'nombre' => $s->nombre,
                    'tipo' => $s->tipo,
                    'tarifa_mensual' => (float) ($s->pivot->tarifa_acordada ?? $s->tarifa_mensual),
                    'concepto' => PagoDetalle::conceptoDesdeServicio($s),
                ])
                ->all(),
            'servicios_cobro' => $lote ? $this->serviciosParaCobro($lote) : [],
            'afiliaciones_cobro' => $lote ? $this->afiliacionesParaCobro($lote) : [],
        ];
    }

    /**
     * Lo que falta pagar de la afiliación de agua y de alcantarillado. Al contado se cobra el
     * monto completo; a plazo, un abono de hasta lo que falta.
     *
     * @return list<array<string, mixed>>
     */
    private function afiliacionesParaCobro(LoteAfiliado $lote): array
    {
        $tipos = TipoAfiliacion::query()
            ->whereIn('codigo', [TipoAfiliacion::CODIGO_AGUA, TipoAfiliacion::CODIGO_ALCANTARILLADO])
            ->get()
            ->keyBy('codigo');
        $opciones = [];

        foreach ([
            TipoAfiliacion::CODIGO_AGUA => [(float) $lote->saldo_afiliacion_agua, PagoDetalle::CONCEPTO_AFILIACION_AGUA, 'agua'],
            TipoAfiliacion::CODIGO_ALCANTARILLADO => [(float) $lote->saldo_afiliacion_alcantarillado, PagoDetalle::CONCEPTO_AFILIACION_ALCANTARILLADO, 'alcantarillado'],
        ] as $codigo => [$saldo, $conceptoBase, $nombre]) {
            $saldo = round($saldo, 2);
            if ($saldo <= 0) {
                continue;
            }

            $tipo = $tipos->get($codigo);
            $concepto = $tipo?->conceptoCobro() ?? $conceptoBase;
            $contado = round((float) ($tipo?->montoContado() ?? PagoDetalle::montoAfiliacionPorConcepto($concepto) ?? 0), 2);
            $alContado = $lote->forma_pago_afiliacion === Afiliado::FORMA_CONTADO && abs($saldo - $contado) < 0.01;
            $descripcion = $alContado ? "Afiliación {$nombre} al contado" : "Afiliación {$nombre} a plazo";

            $opciones[] = $this->opcionAfiliacion($concepto, $descripcion, $alContado ? Afiliado::FORMA_CONTADO : Afiliado::FORMA_PLAZO, $saldo, ! $alContado);
        }

        // Lotes antiguos: solo tienen el saldo total, sin separar agua y alcantarillado.
        $saldoTotal = round((float) $lote->saldo_afiliacion, 2);
        if ($opciones === [] && $saldoTotal > 0) {
            $opciones[] = $this->opcionAfiliacion(PagoDetalle::CONCEPTO_AFILIACION, 'Saldo de afiliación', null, $saldoTotal, true);
        }

        return $opciones;
    }

    /**
     * @return array<string, mixed>
     */
    private function opcionAfiliacion(string $concepto, string $descripcion, ?string $modalidad, float $saldo, bool $editable): array
    {
        $recibo = $this->reciboProposito->clasificar(['concepto' => $concepto, 'descripcion' => $descripcion]);

        return [
            'concepto' => $concepto,
            'descripcion' => $descripcion,
            'modalidad' => $modalidad,
            'saldo' => $saldo,
            'editable' => $editable,
            'recibo' => $recibo,
            'texto' => $this->reciboProposito->textoConcepto(['descripcion' => $descripcion], $recibo),
        ];
    }

    /**
     * Agua y alcantarillado del lote con los meses que se pueden cobrar, del más antiguo al más reciente.
     *
     * @return list<array<string, mixed>>
     */
    private function serviciosParaCobro(LoteAfiliado $lote): array
    {
        return $lote->servicios()
            ->where('servicios.activo', true)
            ->whereIn('servicios.tipo', ['agua', 'alcantarillado'])
            ->orderBy('servicios.id')
            ->get()
            ->sortBy(fn (Servicio $servicio): int => $servicio->esAgua() ? 0 : 1)
            ->map(function (Servicio $servicio) use ($lote): array {
                $meses = $this->mesesPorCobrar->paraServicio($lote, $servicio);
                $mesesPagados = $this->mesesPorCobrar->pagadosRecientes($lote, $servicio);
                $concepto = PagoDetalle::conceptoDesdeServicio($servicio);
                $categoria = trim((string) ($lote->categoria?->nombre
                    ?? LoteAfiliado::labelCategoria($lote->categoria_propiedad)));
                $base = $servicio->esAgua() ? 'Consumo de agua' : 'Pago de alcantarillado';
                $descripcion = ($categoria !== '' && $categoria !== '—')
                    ? trim("{$base} {$categoria}")
                    : $base;
                $recibo = $this->reciboProposito->clasificar([
                    'concepto' => $concepto,
                    'descripcion' => $descripcion,
                    'servicio_id' => $servicio->id,
                ]);

                return [
                    'servicio_id' => $servicio->id,
                    'nombre' => $servicio->esAgua() ? 'Agua potable' : 'Alcantarillado',
                    'tipo' => $servicio->tipo,
                    'concepto' => $concepto,
                    'descripcion' => $descripcion,
                    'tarifa' => round($this->mesesPorCobrar->tarifa($lote, $servicio), 2),
                    'vencidos' => count(array_filter($meses, fn (array $mes): bool => $mes['estado'] === MesesPorCobrarService::ESTADO_VENCIDO)),
                    'meses' => $meses,
                    'meses_pagados' => $mesesPagados,
                    'recibo' => $recibo,
                    'texto' => $this->reciboProposito->textoConcepto(['descripcion' => $descripcion], $recibo),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{0: Afiliado|null, 1: LoteAfiliado|null}
     */
    private function resolverAfiliadoDesdeSelector(int $id): array
    {
        $lote = LoteAfiliado::query()->with(['afiliado', 'otb'])->find($id);

        if ($lote) {
            return [$lote->afiliado->conLote($lote), $lote];
        }

        $afiliado = Afiliado::query()->with(['lotes.otb'])->find($id);

        if (! $afiliado) {
            return [null, null];
        }

        $lote = $afiliado->lotes->first();

        return [$afiliado->conLote($lote), $lote];
    }

    private function afiliadoDePago(Pago $pago): Afiliado
    {
        $afiliado = $pago->afiliado;
        $lote = $pago->lote;

        if ($lote) {
            return $afiliado->conLote($lote);
        }

        return $afiliado;
    }

    private function categoriaDelPago(Pago $pago): ?string
    {
        $pago->loadMissing(['lote.categoria']);
        $lote = $pago->lote;

        if (! $lote) {
            return null;
        }

        $categoria = trim((string) ($lote->categoria?->nombre
            ?? LoteAfiliado::labelCategoria($lote->categoria_propiedad)));

        return ($categoria !== '' && $categoria !== '—') ? $categoria : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     * @return array<int, array<string, mixed>>
     */
    private function lineasReciboSinDuplicados(array $lineas): array
    {
        return TextoPresentacion::agruparLineasRecibo($lineas);
    }

    /**
     * Conserva la búsqueda del listado (CI, nombre, fechas) al volver o al anular.
     *
     * @return array<string, string>
     */
    private function parametrosListado(Request $request): array
    {
        $params = [];

        foreach (['q', 'fecha_desde', 'fecha_hasta', 'concepto', 'ci'] as $key) {
            $valor = trim((string) $request->input($key, $request->query($key, '')));

            if ($valor !== '') {
                $params[$key] = $valor;
            }
        }

        $afiliadoId = (int) $request->input('afiliado_id', $request->query('afiliado_id', 0));

        if ($afiliadoId > 0) {
            $params['afiliado_id'] = (string) $afiliadoId;
        }

        return $params;
    }
}
