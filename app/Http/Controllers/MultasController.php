<?php

namespace App\Http\Controllers;

use App\Models\Afiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Services\ActivityLogger;
use App\Services\CalculadorMultasService;
use App\Services\MultasConsultaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MultasController extends Controller
{
    public function __construct(
        private readonly CalculadorMultasService $calculadorMultas,
        private readonly MultasConsultaService $consulta,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): Response
    {
        if ($this->consulta->puedeVerListadoMultas($request->user())) {
            return $this->indexGestion($request);
        }

        return $this->indexConsulta($request);
    }

    public function show(Request $request, int $id): Response
    {
        $multa = Multa::query()
            ->with([
                'afiliado:id,nombres,apellidos,ci',
                'lote.otb:id,nombre',
                'trabajo.otb:id,nombre',
                'generadaPor:id,name',
            ])
            ->findOrFail($id);

        $this->consulta->assertPuedeVerMulta($request, $multa);

        $recibos = PagoDetalle::query()
            ->where('multa_id', $multa->id)
            ->with('pago:id,numero_recibo,fecha_pago,estado,monto_total')
            ->get()
            ->map(function (PagoDetalle $detalle): ?array {
                $pago = $detalle->pago;

                if (! $pago) {
                    return null;
                }

                return [
                    'id' => $pago->id,
                    'numero_recibo' => $pago->numero_recibo,
                    'fecha_pago' => $pago->fecha_pago?->format('Y-m-d'),
                    'estado' => $pago->estado,
                    'monto' => (float) $detalle->monto,
                ];
            })
            ->filter()
            ->unique('id')
            ->values()
            ->all();

        return Inertia::render('multas/Show', [
            'multa' => [
                'id' => $multa->id,
                'fecha_generacion' => $multa->fecha_generacion->format('Y-m-d'),
                'motivo' => $multa->motivo,
                'descripcion' => $multa->descripcion,
                'monto' => (float) $multa->monto,
                'saldo' => (float) ($multa->saldo ?? $multa->monto),
                'estado' => $multa->estado,
                'gestion' => $multa->gestion,
                'cuotas_vencidas' => $multa->cuotas_vencidas,
                'afiliado' => [
                    'id' => $multa->afiliado->id,
                    'nombre_completo' => $multa->afiliado->nombre_completo,
                    'ci' => $multa->afiliado->ci,
                ],
                'lote_id' => $multa->lote_afiliado_id,
                'lote' => $multa->lote ? [
                    'numero' => $multa->lote->numero_lote,
                    'otb' => $multa->lote->otb?->nombre,
                ] : null,
                'trabajo' => $multa->trabajo ? [
                    'tipo' => $multa->trabajo->tipo,
                    'fecha' => $multa->trabajo->fecha_inicio?->format('Y-m-d'),
                    'otb' => $multa->trabajo->otb?->nombre,
                    'monto_multa' => (float) $multa->trabajo->monto_multa,
                ] : null,
                'generada_por' => $multa->generadaPor?->name,
                'recibos' => $recibos,
            ],
            'filtros_retorno' => [
                'estado' => $request->query('estado') ?: null,
                'motivo' => $request->query('motivo') ?: null,
                'ci' => $request->query('ci') ?: null,
                'q' => $request->query('q') ?: null,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('multas/Create', [
            'afiliados' => Afiliado::query()
                ->where('estado', '!=', Afiliado::ESTADO_BAJA)
                ->orderBy('apellidos')
                ->get(['id', 'nombres', 'apellidos', 'ci'])
                ->map(fn (Afiliado $a): array => [
                    'id' => $a->id,
                    'nombre_completo' => $a->nombre_completo,
                    'ci' => $a->ci,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'afiliado_id' => ['required', 'integer', Rule::exists('afiliados', 'id')],
            'fecha_generacion' => ['required', 'date'],
            'descripcion' => ['required', 'string', 'max:500'],
            'monto' => ['required', 'numeric', 'min:0.01'],
        ]);

        $afiliado = Afiliado::query()->findOrFail($validated['afiliado_id']);
        $lote = $afiliado->lotes()->orderBy('id')->first() ?? $afiliado->sincronizarLotePrincipal();

        Multa::create([
            'afiliado_id' => $afiliado->id,
            'lote_afiliado_id' => $lote->id,
            'fecha_generacion' => $validated['fecha_generacion'],
            'motivo' => Multa::MOTIVO_INDIVIDUAL,
            'gestion' => null,
            'descripcion' => $validated['descripcion'],
            'monto' => $validated['monto'],
            'saldo' => $validated['monto'],
            'estado' => Multa::ESTADO_PENDIENTE,
            'generada_por' => $request->user()?->id,
        ]);

        $this->activityLogger->log(
            $request->user(),
            'Multas',
            'Registro de multa manual',
        );

        return redirect()->route('multas.index')->with('success', 'Multa registrada correctamente.');
    }

    public function generarAutomaticas(Request $request, int $afiliadoId): RedirectResponse
    {
        $afiliado = Afiliado::query()->findOrFail($afiliadoId);
        $creadas = $this->calculadorMultas->evaluarYGenerar($afiliado, $request->user());
        $this->calculadorMultas->actualizarEstadoPorMora($afiliado->fresh());

        $mensaje = $creadas > 0
            ? "Se generaron {$creadas} multa(s) automáticas."
            : 'No se generaron multas (el afiliado no supera los umbrales de mora).';

        return back()->with($creadas > 0 ? 'success' : 'info', $mensaje);
    }

    private function indexGestion(Request $request): Response
    {
        $filtros = $this->resolverFiltrosListado($request);
        $termino = $filtros['q'];

        $query = Multa::query()
            ->with(['afiliado:id,nombres,apellidos,ci', 'lote.otb:id,nombre'])
            ->when($termino !== '', function ($multas) use ($termino): void {
                $multas->where(function ($filtro) use ($termino): void {
                    $filtro->whereHas('afiliado', function ($afiliado) use ($termino): void {
                        $afiliado->where('nombres', 'like', "%{$termino}%")
                            ->orWhere('apellidos', 'like', "%{$termino}%")
                            ->orWhere('ci', 'like', "%{$termino}%");
                        foreach (preg_split('/\s+/', $termino) ?: [] as $parte) {
                            if ($parte === '' || $parte === $termino) {
                                continue;
                            }
                            $afiliado->orWhere('nombres', 'like', "%{$parte}%")
                                ->orWhere('apellidos', 'like', "%{$parte}%");
                        }
                    })->orWhereHas('lote', function ($lote) use ($termino): void {
                        $numero = preg_replace('/\D/', '', $termino) ?: $termino;
                        $lote->where('numero_lote', $termino)
                            ->orWhere('numero_lote', $numero);
                    });
                });
            });

        $this->aplicarFiltrosEstadoYMotivo($query, $filtros['estado'], $filtros['motivo']);

        $multas = $query->latest('fecha_generacion')->paginate(20)->withQueryString();

        return Inertia::render('multas/Index', [
            'modo' => 'gestion',
            'multas' => $multas->through(fn (Multa $m): array => $this->mapMulta($m)),
            'filtros' => [
                'estado' => $filtros['estado'],
                'motivo' => $filtros['motivo'],
                'q' => $termino,
            ],
            'opcionesFiltro' => $this->opcionesFiltroMultas(),
            'conteos' => $this->conteosMultas(),
            'afiliado' => null,
            'mensaje' => null,
            'busqueda' => ['ci' => '', 'q' => $termino],
            'consultaVinculada' => false,
        ]);
    }

    private function indexConsulta(Request $request): Response
    {
        ['afiliado' => $afiliado, 'mensaje' => $mensaje] = $this->consulta->resolverAfiliadoConsulta($request);
        $filtros = $this->resolverFiltrosListado($request);

        $multas = $afiliado
            ? $this->consulta->multasDeAfiliado($afiliado, $request)->through(fn (Multa $m): array => $this->mapMulta($m))
            : $this->multasVacias();

        return Inertia::render('multas/Index', [
            'modo' => 'consulta',
            'multas' => $multas,
            'filtros' => [
                'estado' => $filtros['estado'],
                'motivo' => $filtros['motivo'],
                'ci' => (string) $request->query('ci', ''),
            ],
            'opcionesFiltro' => $this->opcionesFiltroMultas(),
            'conteos' => $afiliado ? $this->conteosMultas($afiliado) : [
                'todas' => 0,
                'pendiente' => 0,
                'pagada' => 0,
                'anulada' => 0,
                'recibos_multa' => 0,
            ],
            'afiliado' => $afiliado ? $this->consulta->resumenAfiliado($afiliado) : null,
            'mensaje' => $mensaje,
            'busqueda' => [
                'ci' => (string) $request->query('ci', ''),
                'q' => '',
            ],
            'consultaVinculada' => $this->consulta->esConsultaVinculada($request->user()),
        ]);
    }

    /**
     * @return array{estado: string, motivo: string, q: string}
     */
    private function resolverFiltrosListado(Request $request): array
    {
        $estado = trim((string) $request->query('estado', Multa::ESTADO_PENDIENTE));
        $estadosValidos = ['todas', Multa::ESTADO_PENDIENTE, Multa::ESTADO_PAGADA, Multa::ESTADO_ANULADA];
        if (! in_array($estado, $estadosValidos, true)) {
            $estado = Multa::ESTADO_PENDIENTE;
        }

        $motivo = trim((string) $request->query('motivo', ''));
        $motivosValidos = [
            '',
            Multa::MOTIVO_MORA_TRIMESTRAL,
            Multa::MOTIVO_MORA_ANUAL,
            Multa::MOTIVO_FALTA_TRABAJO,
            Multa::MOTIVO_INDIVIDUAL,
        ];
        if (! in_array($motivo, $motivosValidos, true)) {
            $motivo = '';
        }

        return [
            'estado' => $estado,
            'motivo' => $motivo,
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Multa>  $query
     */
    private function aplicarFiltrosEstadoYMotivo($query, string $estado, string $motivo): void
    {
        if ($estado === 'todas') {
            // Sin filtro de estado.
        } elseif ($estado === Multa::ESTADO_PENDIENTE) {
            $query->porCobrar();
        } else {
            $query->where('estado', $estado);
        }

        if ($motivo !== '') {
            $query->where('motivo', $motivo);
        }
    }

    /**
     * @return array{
     *   estados: list<array{value: string, label: string}>,
     *   motivos: list<array{value: string, label: string}>
     * }
     */
    private function opcionesFiltroMultas(): array
    {
        return [
            'estados' => [
                ['value' => Multa::ESTADO_PENDIENTE, 'label' => 'Pendiente'],
                ['value' => Multa::ESTADO_PAGADA, 'label' => 'Pagada'],
                ['value' => Multa::ESTADO_ANULADA, 'label' => 'Anulada'],
                ['value' => 'todas', 'label' => 'Todas'],
            ],
            'motivos' => array_values(array_filter([
                ['value' => '', 'label' => 'Todos los motivos'],
                ['value' => Multa::MOTIVO_MORA_TRIMESTRAL, 'label' => 'Mora trimestral (Bs 50)'],
                // Multas anuales sueltas de la regla vieja (si aún quedan pendientes).
                Multa::query()->where('motivo', Multa::MOTIVO_MORA_ANUAL)->exists()
                    ? ['value' => Multa::MOTIVO_MORA_ANUAL, 'label' => 'Mora anual (Bs 150)']
                    : null,
                ['value' => Multa::MOTIVO_FALTA_TRABAJO, 'label' => 'Falta de trabajo'],
                ['value' => Multa::MOTIVO_INDIVIDUAL, 'label' => 'Multa individual'],
            ])),
        ];
    }

    /**
     * @return array{todas: int, pendiente: int, pagada: int, anulada: int, recibos_multa: int}
     */
    private function conteosMultas(?Afiliado $afiliado = null): array
    {
        $multas = Multa::query()->when($afiliado, fn ($q) => $q->where('afiliado_id', $afiliado->id));
        $recibos = Pago::query()
            ->where('estado', Pago::ESTADO_EMITIDO)
            ->when($afiliado, fn ($q) => $q->where('afiliado_id', $afiliado->id))
            ->whereHas('detalles', fn ($q) => $q->where('concepto', PagoDetalle::CONCEPTO_MULTA));

        return [
            'todas' => (clone $multas)->count(),
            'pendiente' => (clone $multas)->where('estado', Multa::ESTADO_PENDIENTE)->count(),
            'pagada' => (clone $multas)->where('estado', Multa::ESTADO_PAGADA)->count(),
            'anulada' => (clone $multas)->where('estado', Multa::ESTADO_ANULADA)->count(),
            'recibos_multa' => $recibos->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMulta(Multa $m): array
    {
        $reciboId = PagoDetalle::query()
            ->where('multa_id', $m->id)
            ->whereHas('pago', fn ($q) => $q->where('estado', Pago::ESTADO_EMITIDO))
            ->latest('id')
            ->value('pago_id');

        return [
            'id' => $m->id,
            'afiliado' => [
                'id' => $m->afiliado->id,
                'ci' => $m->afiliado->ci,
                'nombre_completo' => $m->afiliado->nombre_completo,
            ],
            'lote_id' => $m->lote_afiliado_id,
            'numero_lote' => $m->lote?->numero_lote,
            'otb' => $m->lote?->otb?->nombre,
            'lote' => $m->lote ? [
                'numero' => $m->lote->numero_lote,
                'numero_lote' => $m->lote->numero_lote,
                'otb' => $m->lote->otb?->nombre,
            ] : null,
            'fecha_generacion' => $m->fecha_generacion->format('Y-m-d'),
            'motivo' => $m->motivo,
            'descripcion' => $m->descripcion,
            'monto' => (float) ($m->saldo ?? $m->monto),
            'estado' => $m->estado,
            'recibo_id' => $reciboId ? (int) $reciboId : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function multasVacias(): array
    {
        return [
            'data' => [],
            'links' => [],
            'current_page' => 1,
            'last_page' => 1,
            'total' => 0,
        ];
    }
}
