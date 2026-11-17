<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServicioRequest;
use App\Http\Requests\UpdateServicioRequest;
use App\Models\Categoria;
use App\Models\Servicio;
use App\Services\ActivityLogger;
use App\Services\CategoriaTarifaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ServiciosController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly CategoriaTarifaService $tarifaService,
    ) {}

    public function index(Request $request): Response
    {
        $busqueda = trim((string) $request->query('q', ''));
        $tipo = trim((string) $request->query('tipo', ''));
        $estado = trim((string) $request->query('estado', ''));

        $tiposValidos = collect($this->tipos())->pluck('value')->all();
        $categorias = $this->categoriasActivas();
        $lotesPorCategoria = $this->lotesPorCategoria();

        $servicios = Servicio::query()
            ->with('tarifasCategoria')
            ->withCount('lotes')
            ->buscar($busqueda)
            ->when(
                $tipo !== '' && in_array($tipo, $tiposValidos, true),
                fn ($q) => $q->where('tipo', $tipo)
            )
            ->when($estado === 'activo', fn ($q) => $q->where('activo', true))
            ->when($estado === 'inactivo', fn ($q) => $q->where('activo', false))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Servicio $servicio): array => [
                'id' => $servicio->id,
                'codigo' => $servicio->codigo,
                'nombre' => $servicio->nombre,
                'tipo' => $servicio->tipo,
                'tipo_label' => $this->tipoLabel($servicio->tipo),
                'tarifa_mensual' => (float) $servicio->tarifa_mensual,
                'tarifas_por_categoria' => $this->tarifasPorCategoria($servicio, $categorias, $lotesPorCategoria, true),
                'activo' => $servicio->activo,
                'lotes' => $servicio->lotes_count,
                'es_base' => $servicio->esBase(),
            ]);

        return Inertia::render('servicios/Index', [
            'servicios' => $servicios,
            'tipos' => $this->tipos(),
            'filtros' => [
                'q' => $busqueda,
                'tipo' => $tipo,
                'estado' => $estado,
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $servicio = Servicio::query()
            ->withCount('lotes')
            ->findOrFail($id);

        $lotesPorCategoria = $this->lotesPorCategoria($servicio->id);
        // Misma lógica que el listado: Domiciliar/Comercial juntos (agua y alcantarillado).
        $tarifas = $this->tarifasPorCategoria(
            $servicio,
            $this->categoriasActivas(),
            $lotesPorCategoria,
            true,
        );

        return Inertia::render('servicios/Show', [
            'servicio' => [
                'id' => $servicio->id,
                'codigo' => $servicio->codigo,
                'nombre' => $servicio->nombre,
                'tipo' => $servicio->tipo,
                'tipo_label' => $this->tipoLabel($servicio->tipo),
                'tarifa_mensual' => (float) $servicio->tarifa_mensual,
                'tarifas_por_categoria' => $tarifas,
                'activo' => $servicio->activo,
                'lotes_count' => $servicio->lotes_count,
                'creado_en' => optional($servicio->created_at)?->format('d/m/Y H:i'),
                'actualizado_en' => optional($servicio->updated_at)?->format('d/m/Y H:i'),
            ],
            'categoria_id' => null,
            'puede_editar' => $request->user()?->hasPermission('servicios.gestionar') ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        $tarifas = $this->categoriasActivas()
            ->map(fn (Categoria $categoria): array => [
                'categoria_id' => $categoria->id,
                'categoria' => $this->nombreCategoria($categoria),
                'monto' => 0.0,
                'lotes' => 0,
            ])
            ->values()
            ->all();

        return Inertia::render('servicios/Create', [
            'tarifas_por_categoria' => $tarifas,
            'categoria_id' => $this->categoriaElegida($tarifas, (int) $request->query('categoria', 0)),
        ]);
    }

    /**
     * @param  array<int, array{categoria_id: int}>  $tarifas
     */
    private function categoriaElegida(array $tarifas, int $categoriaId): ?int
    {
        return collect($tarifas)->contains('categoria_id', $categoriaId)
            ? $categoriaId
            : ($tarifas[0]['categoria_id'] ?? null);
    }

    public function store(StoreServicioRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $datosTarifa = $this->resolverDatosTarifa($validated);
        unset(
            $validated['tarifas'],
            $validated['lineas'],
            $validated['categoria_id'],
            $validated['categoria_nombre'],
        );

        $servicio = DB::transaction(function () use ($validated, $datosTarifa): Servicio {
            $servicio = Servicio::create([
                ...$validated,
                'tarifa_mensual' => $datosTarifa['tarifa_mensual'],
                'descripcion' => $datosTarifa['etiqueta'],
                'activo' => $validated['activo'] ?? true,
            ]);

            if ($datosTarifa['tarifas'] !== []) {
                $this->guardarTarifas($servicio, $datosTarifa['tarifas']);
            }

            return $servicio;
        });

        $this->activityLogger->log($request->user(), 'Servicios', 'Alta de servicio: '.$servicio->nombre);

        return redirect()->route('servicios.index')->with('success', 'Servicio creado correctamente.');
    }

    public function edit(Request $request, int $id): Response
    {
        $servicio = Servicio::query()->withCount('lotes')->findOrFail($id);
        // Una sola vista de edición con todas las líneas (categorías) del servicio.
        $tarifas = $this->tarifasPorCategoria(
            $servicio,
            $this->categoriasActivas(),
            $this->lotesPorCategoria($servicio->id),
            true,
        );

        return Inertia::render('servicios/Edit', [
            'servicio' => [
                'id' => $servicio->id,
                'codigo' => $servicio->codigo,
                'nombre' => $servicio->nombre,
                'tipo' => $servicio->tipo,
                'tarifas_por_categoria' => $tarifas,
                'activo' => $servicio->activo,
                'lotes' => $servicio->lotes_count,
                'es_base' => $servicio->esBase(),
            ],
            'categoria_id' => $this->categoriaElegida($tarifas, (int) $request->query('categoria', 0)),
        ]);
    }

    public function update(UpdateServicioRequest $request, int $id): RedirectResponse
    {
        $servicio = Servicio::query()->findOrFail($id);
        $validated = $request->validated();
        $datosTarifa = $this->resolverDatosTarifa($validated, $servicio);
        unset(
            $validated['tarifas'],
            $validated['lineas'],
            $validated['categoria_id'],
            $validated['categoria_nombre'],
        );

        DB::transaction(function () use ($servicio, $validated, $datosTarifa): void {
            $categorias = $this->categoriasActivas();
            $tarifasAnteriores = collect($this->tarifasPorCategoria($servicio, $categorias))->pluck('monto', 'categoria_id');
            $eraBase = $servicio->esBase();

            if ($eraBase) {
                $servicio->update($validated);
            } else {
                $servicio->update([
                    ...$validated,
                    'tarifa_mensual' => $datosTarifa['tarifa_mensual'],
                    'descripcion' => $datosTarifa['etiqueta'],
                ]);
            }

            if ($eraBase && ! $servicio->fresh()->esBase()) {
                $servicio->tarifasCategoria()->syncWithoutDetaching(
                    $tarifasAnteriores->map(fn (float $monto): array => ['tarifa' => $monto])->all()
                );
                $servicio->unsetRelation('tarifasCategoria');
            }

            if ($datosTarifa['tarifas'] !== []) {
                $this->guardarTarifas($servicio, $datosTarifa['tarifas']);
            }
        });

        $this->activityLogger->log($request->user(), 'Servicios', 'Actualización de servicio: '.$servicio->nombre);

        return redirect()->route('servicios.index')->with('success', 'Servicio actualizado correctamente.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $servicio = Servicio::query()->withCount('lotes')->findOrFail($id);

        if ($servicio->esBase()) {
            return redirect()
                ->route('servicios.index')
                ->with('error', 'Los servicios de agua y alcantarillado no se pueden eliminar.');
        }

        if ($servicio->lotes_count > 0) {
            return redirect()
                ->route('servicios.index')
                ->with('error', 'No se puede eliminar un servicio con lotes asociados.');
        }

        $nombre = $servicio->nombre;
        $servicio->delete();

        $this->activityLogger->log($request->user(), 'Servicios', "Eliminación de servicio: {$nombre}");

        return redirect()->route('servicios.index')->with('success', 'Servicio eliminado correctamente.');
    }

    /**
     * @return Collection<int, Categoria>
     */
    private function categoriasActivas(): Collection
    {
        return Categoria::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get();
    }

    /**
     * Resuelve categorías/tarifas desde líneas del formulario (o formato legacy).
     *
     * @param  array<string, mixed>  $validated
     * @return array{tarifas: array<int, float>, tarifa_mensual: float, etiqueta: string|null}
     */
    private function resolverDatosTarifa(array $validated, ?Servicio $servicio = null): array
    {
        /** @var list<array{categoria_id?: int|null, categoria_nombre?: string, monto?: mixed, estado?: string}> $lineas */
        $lineas = $validated['lineas'] ?? [];

        if ($lineas !== []) {
            $tarifas = [];
            $etiquetas = [];
            $primerMonto = 0.0;

            foreach ($lineas as $linea) {
                if (($linea['estado'] ?? 'Activa') !== 'Activa') {
                    continue;
                }

                $nombre = trim((string) ($linea['categoria_nombre'] ?? ''));
                if ($nombre === '') {
                    continue;
                }

                $monto = round((float) ($linea['monto'] ?? 0), 2);
                $categoriaId = (int) ($linea['categoria_id'] ?? 0);
                $categoria = $categoriaId > 0
                    ? Categoria::query()->find($categoriaId)
                    : null;

                if (! $categoria) {
                    $categoria = Categoria::query()
                        ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($nombre)])
                        ->first();
                }

                if (! $categoria) {
                    $categoria = Categoria::query()->create([
                        'slug' => Categoria::generarSlugUnico($nombre),
                        'nombre' => $nombre,
                        'descripcion' => null,
                        'tarifa_agua' => 0,
                        'tarifa_alcantarillado' => 0,
                        'activo' => true,
                        'orden' => (int) Categoria::query()->max('orden') + 1,
                    ]);
                }

                $tarifas[$categoria->id] = $monto;
                $etiquetas[] = $this->nombreCategoria($categoria);
                if ($primerMonto <= 0) {
                    $primerMonto = $monto;
                }
            }

            return [
                'tarifas' => $tarifas,
                'tarifa_mensual' => $servicio && $servicio->esBase()
                    ? (float) $servicio->tarifa_mensual
                    : $primerMonto,
                'etiqueta' => $etiquetas !== [] ? implode(' / ', $etiquetas) : $servicio?->descripcion,
            ];
        }

        /** @var array<int|string, float|int|string> $tarifasEntrada */
        $tarifasEntrada = $validated['tarifas'] ?? [];
        $nombre = trim((string) ($validated['categoria_nombre'] ?? ''));
        $monto = (float) (reset($tarifasEntrada) ?: 0);

        // Peticiones con varias tarifas (tests / agua multi-categoría): respetar tal cual.
        if ($nombre === '' && count($tarifasEntrada) > 1) {
            return [
                'tarifas' => $tarifasEntrada,
                'tarifa_mensual' => $servicio ? (float) $servicio->tarifa_mensual : 0.0,
                'etiqueta' => $servicio?->descripcion,
            ];
        }

        if ($nombre === '' && $tarifasEntrada !== []) {
            return [
                'tarifas' => $tarifasEntrada,
                'tarifa_mensual' => round($monto, 2),
                'etiqueta' => $servicio?->descripcion,
            ];
        }

        if ($nombre === '') {
            return [
                'tarifas' => [],
                'tarifa_mensual' => round($monto, 2),
                'etiqueta' => $servicio?->descripcion,
            ];
        }

        $categoria = Categoria::query()
            ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($nombre)])
            ->first();

        if (! $categoria) {
            $categoria = Categoria::query()->create([
                'slug' => Categoria::generarSlugUnico($nombre),
                'nombre' => $nombre,
                'descripcion' => null,
                'tarifa_agua' => 0,
                'tarifa_alcantarillado' => 0,
                'activo' => true,
                'orden' => (int) Categoria::query()->max('orden') + 1,
            ]);
        }

        return [
            'tarifas' => [$categoria->id => $monto],
            'tarifa_mensual' => round($monto, 2),
            'etiqueta' => $nombre,
        ];
    }

    /**
     * @param  array<int|string, int|float|string>  $tarifas
     */
    private function guardarTarifas(Servicio $servicio, array $tarifas): void
    {
        $categorias = Categoria::query()->whereIn('id', array_keys($tarifas))->orderBy('orden')->orderBy('nombre')->get();

        foreach ($categorias as $categoria) {
            if ($this->tarifaService->asignarTarifa($servicio, $categoria, (float) $tarifas[$categoria->id])) {
                $this->tarifaService->aplicarTarifasPorCategoria($categoria->fresh());
            }
        }

        if ($servicio->tipoTarifaPorCategoria() === null && $categorias->isNotEmpty()) {
            $servicio->update(['tarifa_mensual' => round((float) $tarifas[$categorias->first()->id], 2)]);
        }
    }

    /**
     * @return Collection<int|string, Collection<int, object>>
     */
    private function lotesPorCategoria(?int $servicioId = null): Collection
    {
        return DB::table('lote_servicio')
            ->join('lote_afiliados', 'lote_afiliados.id', '=', 'lote_servicio.lote_afiliado_id')
            ->when($servicioId, fn ($q) => $q->where('lote_servicio.servicio_id', $servicioId))
            ->selectRaw('lote_servicio.servicio_id, lote_afiliados.categoria_id, COUNT(*) as total')
            ->groupBy('lote_servicio.servicio_id', 'lote_afiliados.categoria_id')
            ->get()
            ->groupBy('servicio_id');
    }

    /**
     * @param  Collection<int, Categoria>  $categorias
     * @param  Collection<int|string, Collection<int, object>>|null  $lotesPorCategoria
     * @return array<int, array{categoria_id: int, categoria: string, monto: float, lotes: int}>
     */
    private function tarifasPorCategoria(
        Servicio $servicio,
        Collection $categorias,
        ?Collection $lotesPorCategoria = null,
        bool $soloDefinidas = false,
    ): array {
        $servicio->loadMissing('tarifasCategoria');
        $lotes = $lotesPorCategoria?->get($servicio->id) ?? collect();

        // Agua/alcantarillado en listado: solo categorías de propiedad (no las inventadas en pruebas).
        if ($soloDefinidas && $servicio->esBase()) {
            $core = [Categoria::SLUG_DOMICILIARIA, Categoria::SLUG_COMERCIAL];
            $categorias = $categorias->filter(
                fn (Categoria $categoria): bool => in_array($categoria->slug, $core, true)
            )->values();
        }

        $tarifas = $categorias
            ->map(fn (Categoria $categoria): array => [
                'categoria_id' => $categoria->id,
                'categoria' => $this->nombreCategoria($categoria),
                'monto' => $servicio->tarifaParaCategoria($categoria),
                'lotes' => (int) ($lotes->firstWhere('categoria_id', $categoria->id)?->total ?? 0),
            ])
            ->values();

        // Servicios propios: una sola fila (pivot o etiqueta individual).
        if ($soloDefinidas && ! $servicio->esBase()) {
            $definidas = $tarifas
                ->filter(fn (array $tarifa): bool => $servicio->tarifasCategoria->contains('id', $tarifa['categoria_id']))
                ->values();

            if ($definidas->isNotEmpty()) {
                return $definidas->all();
            }

            $etiqueta = trim((string) $servicio->descripcion);

            return [[
                'categoria_id' => 0,
                'categoria' => $etiqueta !== ''
                    ? mb_convert_case($etiqueta, MB_CASE_TITLE, 'UTF-8')
                    : '—',
                'monto' => (float) $servicio->tarifa_mensual,
                'lotes' => (int) ($servicio->lotes_count ?? 0),
            ]];
        }

        return $tarifas->all();
    }

    private function nombreCategoria(Categoria $categoria): string
    {
        return mb_convert_case(trim((string) $categoria->nombre), MB_CASE_TITLE, 'UTF-8');
    }

    private function tipoLabel(?string $tipo): string
    {
        return Servicio::etiquetaTipo($tipo);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function tipos(): array
    {
        return collect(Servicio::TIPOS)
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
