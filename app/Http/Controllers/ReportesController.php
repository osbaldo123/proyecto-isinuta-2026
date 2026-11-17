<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Services\Exports\PagoDocumentExportService;
use App\Support\MultaTexto;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportesController extends Controller
{
    /** Clave de grupo del reporte => nombre en el filtro. */
    private const CONCEPTOS_BASE = [
        'servicio_agua_potable' => 'Consumo de agua',
        'servicio_alcantarillado' => 'Alcantarillado',
        'afiliacion' => 'Afiliación',
        'falta_trabajos' => 'Falta de trabajos',
        'multas' => 'Multas',
        'otros' => 'Otros conceptos',
    ];

    /** Nombres antiguos o alternativos que llegan por URL. */
    private const CONCEPTOS_ALIAS = [
        'servicio agua potable' => 'servicio_agua_potable',
        'agua potable' => 'servicio_agua_potable',
        'pago de alcantarillado' => 'servicio_alcantarillado',
        'servicio de alcantarillado' => 'servicio_alcantarillado',
        'falta_trabajos_agua_potable' => 'falta_trabajos',
        'falta_trabajos_alcantarillado' => 'falta_trabajos',
        'falta de trabajos de agua potable' => 'falta_trabajos',
        'falta de trabajos de alcantarillado' => 'falta_trabajos',
    ];

    public function __construct(
        private readonly PagoDocumentExportService $documentExport,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $reportes = [];

        // Ingresos: lo ya cobrado. Deudas: saldos pendientes. Cada uno con su permiso.
        if (
            $user?->hasPermission(Permission::VerReporteIngresos->value)
            || $user?->hasPermission(Permission::VerReportes->value)
        ) {
            $reportes[] = [
                'id' => 1,
                'titulo' => 'Ingresos',
                'descripcion' => 'Todo lo cobrado recientemente: pagos por concepto (servicios, afiliación, multas pagadas, etc.). Filtra por fechas y concepto; imprime o Excel.',
                'tipo' => 'Ingresos',
                'fecha' => now()->toDateString(),
                'estado' => 'Disponible',
                'href' => route('reportes.ingresos'),
            ];
        }

        if ($user?->hasPermission(Permission::VerReporteDeudas->value)) {
            $reportes[] = [
                'id' => 2,
                'titulo' => 'Deudas',
                'descripcion' => 'Todo lo que los afiliados deben hoy: cuotas, multas (mora, falta de trabajos, manuales) y afiliación a plazo. Filtra por concepto; imprime o Excel.',
                'tipo' => 'Deudas',
                'fecha' => now()->toDateString(),
                'estado' => 'Disponible',
                'href' => route('reportes.deudas'),
            ];
        }

        return Inertia::render('reportes/Index', [
            'reportes' => $reportes,
        ]);
    }

    public function deudas(Request $request): Response
    {
        $concepto = $this->claveConcepto($request->query('concepto'));
        $armado = $this->armarReporteDeudas($concepto);

        return Inertia::render('reportes/Deudas', [
            'reporte' => $armado['reporte'],
            'totalGeneral' => $armado['reporte']['total_general'],
            'totales' => $armado['reporte']['totales'],
            'emitido' => $armado['reporte']['emitido'],
            'filtros' => [
                'concepto' => $concepto,
            ],
            'opciones' => [
                'conceptos' => $armado['conceptos'],
            ],
        ]);
    }

    public function exportDeudas(Request $request, string $formato): StreamedResponse
    {
        abort_unless($formato === 'xlsx', 404);

        $concepto = $this->claveConcepto($request->query('concepto'));
        $armado = $this->armarReporteDeudas($concepto);

        return $this->documentExport->exportDeudas($armado['reporte'], $formato);
    }

    /**
     * Deudas por concepto: todo lo que los afiliados aún deben (cuotas, multas, afiliación a plazo).
     *
     * @return array{
     *   reporte: array{
     *     grupos: list<array{concepto: string, label: string, lineas: list<array<string, mixed>>, subtotal: float}>,
     *     total_general: float,
     *     totales: array{cuotas: float, multas: float, afiliacion: float, lotes: int},
     *     emitido: string
     *   },
     *   conceptos: list<array{value: string, label: string}>
     * }
     */
    private function armarReporteDeudas(?string $conceptoFiltro = null): array
    {
        // No emitir cuotas aquí: con muchos lotes alarga/timeout la página.
        // Las cuotas se generan al cobrar o al abrir estado de cuenta.

        /** @var array<string, array{concepto: string, label: string, lineas: list<array<string, mixed>>}> $bag */
        $bag = [];

        $push = function (string $concepto, string $label, array $linea) use (&$bag): void {
            if (! isset($bag[$concepto])) {
                $bag[$concepto] = [
                    'concepto' => $concepto,
                    'label' => $label,
                    'lineas' => [],
                ];
            }
            $bag[$concepto]['lineas'][] = $linea;
        };

        $lotesActivos = LoteAfiliado::query()
            ->where('estado', '!=', Afiliado::ESTADO_BAJA)
            ->whereHas('afiliado', fn ($q) => $q->where('estado', '!=', Afiliado::ESTADO_BAJA));

        $loteIds = (clone $lotesActivos)->pluck('id');

        Cuota::query()
            ->with(['servicio', 'lote.afiliado', 'lote.otb', 'lote.categoria'])
            ->whereIn('lote_afiliado_id', $loteIds)
            ->where('saldo', '>', 0)
            ->orderBy('periodo')
            ->orderBy('id')
            ->get()
            ->each(function (Cuota $cuota) use ($push): void {
                $lote = $cuota->lote;
                $afiliado = $lote?->afiliado;
                if (! $lote || ! $afiliado || $afiliado->estado === Afiliado::ESTADO_BAJA) {
                    return;
                }

                [$concepto, $label] = $this->cuentaDeudaDesdeCuota($cuota, $lote);
                $push($concepto, $label, [
                    'fecha' => $cuota->fecha_vencimiento?->format('d/m/Y') ?? '—',
                    'socio' => $this->nombreSocioDeuda($afiliado, $lote),
                    'detalle' => $this->detalleDeudaCuota($cuota),
                    'bolivianos' => round((float) $cuota->saldo, 2),
                    'afiliado_id' => $afiliado->id,
                    'lote_id' => $lote->id,
                    'tipo' => 'cuota',
                ]);
            });

        Multa::query()
            ->porCobrar()
            ->with(['afiliado', 'lote.otb', 'lote.categoria', 'trabajo.otb'])
            ->where(function ($q) use ($loteIds): void {
                $q->whereIn('lote_afiliado_id', $loteIds)
                    ->orWhere(function ($sinLote) use ($loteIds): void {
                        $sinLote->whereNull('lote_afiliado_id')
                            ->whereIn('afiliado_id', Afiliado::query()
                                ->where('estado', '!=', Afiliado::ESTADO_BAJA)
                                ->whereHas('lotes', fn ($l) => $l->whereIn('id', $loteIds))
                                ->select('id'));
                    });
            })
            ->orderBy('fecha_generacion')
            ->orderBy('id')
            ->get()
            ->each(function (Multa $multa) use ($push): void {
                $afiliado = $multa->afiliado;
                $lote = $multa->lote;
                if (! $afiliado || $afiliado->estado === Afiliado::ESTADO_BAJA) {
                    return;
                }

                [$concepto, $label] = $this->cuentaDeudaDesdeMulta($multa);
                $push($concepto, $label, [
                    'fecha' => $multa->fecha_generacion?->format('d/m/Y') ?? '—',
                    'socio' => $this->nombreSocioDeuda($afiliado, $lote),
                    'detalle' => $this->detalleDeudaMulta($multa),
                    'bolivianos' => round((float) ($multa->saldo ?? $multa->monto), 2),
                    'afiliado_id' => $afiliado->id,
                    'lote_id' => $lote?->id,
                    'tipo' => 'multa',
                ]);
            });

        (clone $lotesActivos)
            ->with(['afiliado', 'otb', 'categoria'])
            ->where(function ($q): void {
                $q->where('saldo_afiliacion_agua', '>', 0)
                    ->orWhere('saldo_afiliacion_alcantarillado', '>', 0);
            })
            ->orderBy('id')
            ->get()
            ->each(function (LoteAfiliado $lote) use ($push): void {
                $afiliado = $lote->afiliado;
                if (! $afiliado || $afiliado->estado === Afiliado::ESTADO_BAJA) {
                    return;
                }

                $agua = round((float) $lote->saldo_afiliacion_agua, 2);
                $alc = round((float) $lote->saldo_afiliacion_alcantarillado, 2);
                $fecha = $lote->fecha_afiliacion?->format('d/m/Y') ?? '—';

                if ($agua > 0) {
                    $push('afiliacion:agua:plazo', 'Afiliación de agua a plazo', [
                        'fecha' => $fecha,
                        'socio' => $this->nombreSocioDeuda($afiliado, $lote),
                        'detalle' => 'Saldo pendiente de afiliación de agua',
                        'bolivianos' => $agua,
                        'afiliado_id' => $afiliado->id,
                        'lote_id' => $lote->id,
                        'tipo' => 'afiliacion',
                    ]);
                }

                if ($alc > 0) {
                    $push('afiliacion:alcantarillado:plazo', 'Afiliación de alcantarillado a plazo', [
                        'fecha' => $fecha,
                        'socio' => $this->nombreSocioDeuda($afiliado, $lote),
                        'detalle' => 'Saldo pendiente de afiliación de alcantarillado',
                        'bolivianos' => $alc,
                        'afiliado_id' => $afiliado->id,
                        'lote_id' => $lote->id,
                        'tipo' => 'afiliacion',
                    ]);
                }
            });

        $orden = [
            'servicio_agua_potable:domiciliar' => '01',
            'servicio_agua_potable:domiciliaria' => '01',
            'servicio_agua_potable:comercial' => '02',
            'servicio_agua_potable' => '03',
            'servicio_alcantarillado:domiciliar' => '04',
            'servicio_alcantarillado:domiciliaria' => '04',
            'servicio_alcantarillado:comercial' => '05',
            'servicio_alcantarillado' => '06',
            'multas' => '07',
            'falta_trabajos' => '08',
            'afiliacion:agua:plazo' => '09',
            'afiliacion:alcantarillado:plazo' => '10',
        ];

        $grupos = collect($bag)
            ->map(function (array $grupo): array {
                $lineas = collect($grupo['lineas'])
                    ->sortBy(fn (array $l): string => ($l['socio'] ?? '').'|'.($l['fecha'] ?? ''))
                    ->values()
                    ->all();
                $subtotal = round((float) collect($lineas)->sum('bolivianos'), 2);

                return [
                    'concepto' => $grupo['concepto'],
                    'label' => $grupo['label'],
                    'lineas' => $lineas,
                    'subtotal' => $subtotal,
                ];
            })
            ->filter(fn (array $g): bool => $g['subtotal'] > 0 && $g['lineas'] !== [])
            ->sortBy(fn (array $g): string => ($orden[$g['concepto']] ?? '50').'-'.mb_strtolower($g['label']))
            ->values();

        $conceptos = $grupos
            ->map(fn (array $g): array => [
                'value' => (string) $g['concepto'],
                'label' => (string) $g['label'],
            ])
            ->sortBy('label')
            ->values()
            ->all();

        if (filled($conceptoFiltro)) {
            $grupos = $grupos
                ->filter(fn (array $g): bool => $this->grupoCoincideConcepto($g, $conceptoFiltro))
                ->values();
        }

        $grupos = $grupos->all();
        $todas = collect($grupos)->flatMap(fn (array $g) => $g['lineas']);
        $lotesDeudores = $todas->pluck('lote_id')->filter()->unique()->count();

        return [
            'reporte' => [
                'grupos' => $grupos,
                'total_general' => round((float) $todas->sum('bolivianos'), 2),
                'totales' => [
                    'cuotas' => round((float) $todas->where('tipo', 'cuota')->sum('bolivianos'), 2),
                    'multas' => round((float) $todas->where('tipo', 'multa')->sum('bolivianos'), 2),
                    'afiliacion' => round((float) $todas->where('tipo', 'afiliacion')->sum('bolivianos'), 2),
                    'lotes' => $lotesDeudores,
                ],
                'emitido' => now()->format('d/m/Y H:i'),
            ],
            'conceptos' => $conceptos,
        ];
    }

    /**
     * @param  array{concepto: string, label: string}  $grupo
     */
    private function grupoCoincideConcepto(array $grupo, string $filtro): bool
    {
        $filtro = mb_strtolower(trim($filtro));
        $concepto = mb_strtolower((string) $grupo['concepto']);
        $label = mb_strtolower((string) $grupo['label']);
        $base = explode(':', $concepto, 2)[0];

        return $concepto === $filtro
            || $label === $filtro
            || $base === $filtro
            || str_starts_with($concepto, $filtro.':');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function cuentaDeudaDesdeCuota(Cuota $cuota, LoteAfiliado $lote): array
    {
        $esAlc = (bool) $cuota->servicio?->esAlcantarillado();
        $base = $esAlc ? 'servicio_alcantarillado' : 'servicio_agua_potable';
        $categoria = mb_strtolower(trim((string) ($lote->categoria?->nombre ?? '')));
        $concepto = $categoria !== '' ? $base.':'.$this->slugConcepto($categoria) : $base;
        $servicio = $esAlc ? 'Servicio de alcantarillado' : 'Servicio de agua';
        $label = $categoria !== ''
            ? trim($servicio.' '.$categoria)
            : ($esAlc ? 'Servicio de Alcantarillado' : 'Servicio Agua Potable');

        return [$concepto, $label];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function cuentaDeudaDesdeMulta(Multa $multa): array
    {
        if ($multa->motivo === Multa::MOTIVO_FALTA_TRABAJO) {
            return ['falta_trabajos', 'Falta de trabajos'];
        }

        return ['multas', 'Multas'];
    }

    private function detalleDeudaCuota(Cuota $cuota): string
    {
        $periodo = trim((string) $cuota->periodo);
        $texto = $periodo !== '' ? $periodo : 'Cuota pendiente';
        if ($cuota->vencida()) {
            $texto .= ' · vencida';
        }
        if ($cuota->servicio?->nombre) {
            $texto .= ' · '.$cuota->servicio->nombre;
        }

        return $texto;
    }

    private function detalleDeudaMulta(Multa $multa): string
    {
        $titulo = MultaTexto::titulo($multa);
        $extra = MultaTexto::detalle($multa);
        $desc = trim((string) ($multa->descripcion ?? ''));

        if ($multa->motivo === Multa::MOTIVO_INDIVIDUAL && $desc !== '') {
            return $desc;
        }

        if ($extra !== '') {
            return trim($titulo.' · '.$extra);
        }

        return $titulo !== '' ? $titulo : ($desc !== '' ? $desc : 'Multa pendiente');
    }

    private function nombreSocioDeuda(Afiliado $afiliado, ?LoteAfiliado $lote): string
    {
        $otb = $lote?->otb?->nombre;
        $numero = $lote?->numero_lote;
        $contexto = trim(collect([$otb, filled($numero) ? 'Lote '.$numero : null])->filter()->implode(', '));

        return $contexto !== ''
            ? $afiliado->nombre_completo.' ('.$contexto.')'
            : (string) $afiliado->nombre_completo;
    }

    private function slugConcepto(string $texto): string
    {
        $slug = mb_strtolower(trim($texto));
        $slug = strtr($slug, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]+/', '_', $slug) ?: 'otro';
    }

    public function ingresos(Request $request): Response
    {
        [$desde, $hasta] = $this->rangoFechas($request);
        $socioId = $request->integer('socio') ?: null;
        $concepto = $this->claveConcepto($request->query('concepto'));
        $categoriaId = $request->integer('categoria') ?: null;
        [$reporte, $desde, $hasta] = $this->reporteIngresos($desde, $hasta, $socioId, $concepto, $categoriaId);

        $gruposDelRango = $socioId || $concepto || $categoriaId
            ? $this->documentExport->buildIngresosReport($desde, $hasta)['grupos']
            : $reporte['grupos'];

        $omitidos = [
            'cambio_titularidad',
            'cambio_de_nombre',
            'falta_reuniones_agua_potable',
            'falta_reuniones_alcantarillado',
            'falta_de_reuniones',
            'reuniones_agua',
            'reuniones_alcantarillado',
            'otros',
        ];

        $conceptos = collect();
        foreach ($gruposDelRango as $grupo) {
            if (in_array($grupo['concepto'], $omitidos, true)) {
                continue;
            }
            $conceptos->put($grupo['concepto'], (string) $grupo['label']);
        }
        $conceptos = $conceptos
            ->map(fn (string $label, string $clave): array => ['value' => $clave, 'label' => $label])
            ->sortBy('label')
            ->values()
            ->all();

        $conPagos = Pago::query()
            ->where('estado', Pago::ESTADO_EMITIDO)
            ->whereDate('fecha_pago', '>=', $desde)
            ->whereDate('fecha_pago', '<=', $hasta)
            ->distinct()
            ->pluck('afiliado_id')
            ->flip();

        $rangoPagos = Pago::query()
            ->where('estado', Pago::ESTADO_EMITIDO)
            ->whereNotNull('afiliado_id')
            ->groupBy('afiliado_id')
            ->selectRaw('afiliado_id, MIN(DATE(fecha_pago)) as primer_pago, MAX(DATE(fecha_pago)) as ultimo_pago')
            ->get()
            ->keyBy('afiliado_id');

        $categoriasPorAfiliado = LoteAfiliado::query()
            ->whereNotNull('categoria_id')
            ->get(['afiliado_id', 'categoria_id'])
            ->groupBy('afiliado_id')
            ->map(fn ($lotes) => $lotes->pluck('categoria_id')->unique()->values()->all());

        $afiliados = Afiliado::query()
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'ci', 'nombres', 'apellidos'])
            ->map(fn (Afiliado $afiliado): array => [
                'id' => $afiliado->id,
                'ci' => $afiliado->ci,
                'nombre' => $afiliado->nombre_completo,
                'categorias' => $categoriasPorAfiliado->get($afiliado->id, []),
                'con_pagos' => $conPagos->has($afiliado->id),
                'primer_pago' => $rangoPagos->get($afiliado->id)?->primer_pago,
                'ultimo_pago' => $rangoPagos->get($afiliado->id)?->ultimo_pago,
            ])
            ->values()
            ->all();

        return Inertia::render('reportes/Ingresos', [
            'reporte' => $reporte,
            'filtros' => [
                'desde' => $desde,
                'hasta' => $hasta,
                'socio' => $socioId,
                'concepto' => $concepto,
                'categoria' => $categoriaId,
            ],
            'opciones' => [
                'afiliados' => $afiliados,
                'conceptos' => $conceptos,
                'categorias' => Categoria::query()
                    ->where('activo', true)
                    ->orderBy('orden')
                    ->orderBy('nombre')
                    ->get(['id', 'nombre'])
                    ->map(fn (Categoria $c): array => [
                        'id' => $c->id,
                        'nombre' => $c->nombre,
                        'total' => $categoriasPorAfiliado->filter(fn ($ids) => in_array($c->id, $ids, true))->count(),
                    ])
                    ->values()
                    ->all(),
                'primer_pago' => substr((string) Pago::query()->where('estado', Pago::ESTADO_EMITIDO)->min('fecha_pago'), 0, 10) ?: null,
            ],
        ]);
    }

    public function exportIngresos(Request $request, string $formato): StreamedResponse
    {
        abort_unless($formato === 'xlsx', 404);

        [$desde, $hasta] = $this->rangoFechas($request);
        $socioId = $request->integer('socio') ?: null;
        $concepto = $this->claveConcepto($request->query('concepto'));
        $categoriaId = $request->integer('categoria') ?: null;
        [$reporte] = $this->reporteIngresos($desde, $hasta, $socioId, $concepto, $categoriaId);

        return $this->documentExport->exportIngresos($reporte, $formato);
    }

    private function claveConcepto(mixed $valor): ?string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        $minusculas = mb_strtolower($texto);

        if (isset(self::CONCEPTOS_ALIAS[$minusculas])) {
            return self::CONCEPTOS_ALIAS[$minusculas];
        }

        foreach (self::CONCEPTOS_BASE as $clave => $label) {
            if (mb_strtolower($label) === $minusculas) {
                return $clave;
            }
        }

        return $texto;
    }

    /**
     * Respeta el rango desde/hasta elegido. Con afiliado o concepto, solo filtra dentro de ese rango
     * (ya no se amplía solo al historial completo).
     *
     * @return array{0: array<string, mixed>, 1: string, 2: string}
     */
    private function reporteIngresos(string $desde, string $hasta, ?int $socioId, ?string $concepto, ?int $categoriaId = null): array
    {
        $reporte = $this->documentExport->buildIngresosReport($desde, $hasta, $socioId, $concepto, $categoriaId);

        return [$reporte, $desde, $hasta];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function rangoFechas(Request $request): array
    {
        $hasta = trim((string) $request->query('hasta', now()->toDateString()));
        $desde = trim((string) $request->query('desde', now()->startOfYear()->toDateString()));

        if ($hasta === '') {
            $hasta = now()->toDateString();
        }

        if ($desde === '') {
            $desde = now()->startOfMonth()->toDateString();
        }

        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }
}
