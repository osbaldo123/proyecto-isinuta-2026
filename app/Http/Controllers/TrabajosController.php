<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarListaTrabajoRequest;
use App\Http\Requests\StoreTrabajoRequest;
use App\Http\Requests\UpdateTrabajoRequest;
use App\Models\Multa;
use App\Models\Trabajo;
use App\Models\Otb;
use App\Services\ActivityLogger;
use App\Services\TrabajoListaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrabajosController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly TrabajoListaService $lista,
    ) {}

    public function index(Request $request): Response
    {
        $busqueda = trim((string) $request->query('q', ''));

        $trabajos = Trabajo::query()
            ->with('otb:id,nombre')
            ->withCount([
                'asistencias',
                'multas as multas_count' => fn ($query) => $query->whereIn('estado', [
                    Multa::ESTADO_PENDIENTE,
                    Multa::ESTADO_PAGADA,
                ]),
            ])
            ->buscar($busqueda)
            ->latest('fecha_inicio')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Trabajo $trabajo): array => $this->trabajoData($trabajo));

        return Inertia::render('trabajos/Index', [
            'trabajos' => $trabajos,
            'filtros' => [
                'q' => $busqueda,
            ],
            'formOptions' => [
                'otbs' => $this->otbs(),
            ],
        ]);
    }

    public function store(StoreTrabajoRequest $request): RedirectResponse
    {
        $trabajo = Trabajo::query()->create($request->validated());

        $this->activityLogger->log(
            $request->user(),
            'Trabajos',
            'Registro de trabajo #'.$trabajo->id.' en OTB '.$trabajo->otb?->nombre,
        );

        return redirect()
            ->route('trabajos.show', $trabajo->id)
            ->with('success', 'Trabajo registrado. Pase lista por lote (marque Presente).');
    }

    public function show(int $id): Response
    {
        $trabajo = Trabajo::query()->with('otb:id,nombre')->findOrFail($id);
        $filas = $this->lista->filas($trabajo);

        return Inertia::render('trabajos/Lista', [
            'trabajo' => $this->trabajoData($trabajo),
            'filas' => $filas,
            'listaGuardada' => $trabajo->asistencias()->exists(),
            'formOptions' => [
                'otbs' => $this->otbs(),
            ],
        ]);
    }

    public function update(UpdateTrabajoRequest $request, int $id): RedirectResponse
    {
        $trabajo = Trabajo::query()->withCount('asistencias')->findOrFail($id);
        $validated = $request->validated();

        if ($trabajo->asistencias_count > 0 && (int) $validated['otb_id'] !== (int) $trabajo->otb_id) {
            return redirect()
                ->route('trabajos.show', $trabajo->id)
                ->with('error', 'No se puede cambiar la OTB después de pasar lista.');
        }

        $trabajo->update($validated);
        $this->lista->refrescarMultasPendientes($trabajo->fresh('otb'));

        $this->activityLogger->log($request->user(), 'Trabajos', 'Actualización de trabajo #'.$trabajo->id);

        return redirect()
            ->route('trabajos.show', $trabajo->id)
            ->with('success', 'Trabajo actualizado.');
    }

    public function guardarLista(GuardarListaTrabajoRequest $request, int $id): RedirectResponse
    {
        $trabajo = Trabajo::query()->findOrFail($id);
        $marcas = collect($request->validated('asistencias'))
            ->map(fn (array $fila): array => [
                'lote_afiliado_id' => (int) $fila['lote_afiliado_id'],
                'presente' => filter_var($fila['presente'], FILTER_VALIDATE_BOOLEAN),
            ])
            ->all();

        $resultado = $this->lista->guardar($trabajo, $marcas, $request->user());

        $this->activityLogger->log(
            $request->user(),
            'Trabajos',
            "Lista del trabajo #{$trabajo->id}: {$resultado['presentes']} presente(s), {$resultado['ausentes']} ausente(s), {$resultado['multas']} multa(s).",
        );

        $mensaje = $resultado['ausentes'] > 0
            ? "Lista guardada. {$resultado['ausentes']} lote(s) sin presente (multa por lote)."
            : 'Lista guardada. Todos los lotes de esta OTB quedaron presentes.';

        return redirect()
            ->route('trabajos.show', $trabajo->id)
            ->with('success', $mensaje);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $trabajo = Trabajo::query()->findOrFail($id);

        if ($trabajo->multas()->where('estado', Multa::ESTADO_PAGADA)->exists()) {
            return redirect()
                ->route('trabajos.index')
                ->with('error', 'No se puede eliminar: ya hay una multa de este trabajo cobrada en un recibo.');
        }

        $trabajoId = $trabajo->id;
        $trabajo->multas()->delete();
        $trabajo->delete();

        $this->activityLogger->log($request->user(), 'Trabajos', "Eliminación de trabajo #{$trabajoId}");

        return redirect()->route('trabajos.index')->with('success', 'Trabajo eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function trabajoData(Trabajo $trabajo): array
    {
        $trabajo->loadMissing('otb:id,nombre');

        return [
            'id' => $trabajo->id,
            'otb_id' => $trabajo->otb_id,
            'otb' => $trabajo->otb?->nombre,
            'tipo' => $trabajo->tipo,
            'descripcion' => $trabajo->descripcion,
            'fecha_inicio' => $trabajo->fecha_inicio?->format('Y-m-d'),
            'monto_multa' => (float) $trabajo->monto_multa,
            'presentes' => $this->lista->contarLotesMarcados($trabajo, true),
            'ausentes' => $this->lista->contarLotesMarcados($trabajo, false),
            'multas' => (int) ($trabajo->multas_count ?? $trabajo->multas()->whereIn('estado', [
                Multa::ESTADO_PENDIENTE,
                Multa::ESTADO_PAGADA,
            ])->count()),
            'lista_cerrada' => isset($trabajo->asistencias_count)
                ? (int) $trabajo->asistencias_count > 0
                : $trabajo->asistencias()->exists(),
        ];
    }

    /**
     * @return array<int, array{id: int, nombre: string}>
     */
    private function otbs(): array
    {
        return Otb::query()
            ->where('activa', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (Otb $otb): array => [
                'id' => $otb->id,
                'nombre' => $otb->nombre,
            ])
            ->all();
    }
}
