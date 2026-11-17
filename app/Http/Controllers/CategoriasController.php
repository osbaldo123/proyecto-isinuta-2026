<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\LoteAfiliado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoriasController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = trim((string) $request->query('q', ''));

        $categorias = Categoria::query()
            ->addSelect([
                'lotes_count' => LoteAfiliado::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('categoria_id', 'categorias.id'),
            ])
            ->when($busqueda !== '', function ($query) use ($busqueda): void {
                $like = "%{$busqueda}%";
                $query->where(function ($q) use ($like, $busqueda): void {
                    $q->where('nombre', 'like', $like)
                        ->orWhere('descripcion', 'like', $like)
                        ->orWhere('slug', 'like', $like);

                    $estado = mb_strtolower($busqueda);
                    if (str_contains('activa', $estado) || str_contains($estado, 'activ')) {
                        $q->orWhere('activo', true);
                    }
                    if (str_contains('inactiva', $estado) || str_contains($estado, 'inactiv')) {
                        $q->orWhere('activo', false);
                    }
                });
            })
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Categoria $categoria): array => [
                'id' => $categoria->id,
                'nombre' => $categoria->nombre,
                'descripcion' => $categoria->descripcion ?? '',
                'tarifa_agua' => (float) $categoria->tarifa_agua,
                'tarifa_alcantarillado' => (float) $categoria->tarifa_alcantarillado,
                'total_mensual' => $categoria->tarifaMensualTotal(),
                'lotes' => (int) $categoria->lotes_count,
                'estado' => $categoria->activo ? 'Activa' : 'Inactiva',
            ]);

        return Inertia::render('categorias/Index', [
            'categorias' => $categorias,
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('categorias/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validar($request);

        Categoria::create([
            'slug' => Categoria::generarSlugUnico($validated['nombre']),
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'tarifa_agua' => 0,
            'tarifa_alcantarillado' => 0,
            'activo' => $validated['estado'] === 'Activa',
            'orden' => (int) Categoria::query()->max('orden') + 1,
        ]);

        return redirect()->route('categorias.index')->with('success', 'Categoría creada correctamente.');
    }

    public function edit(int $id): Response
    {
        $categoria = Categoria::query()->findOrFail($id);

        $lotes = (int) LoteAfiliado::query()->where('categoria_id', $categoria->id)->count();

        return Inertia::render('categorias/Edit', [
            'categoria' => [
                'id' => $categoria->id,
                'nombre' => $categoria->nombre,
                'descripcion' => $categoria->descripcion ?? '',
                'lotes' => $lotes,
                'estado' => $categoria->activo ? 'Activa' : 'Inactiva',
            ],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $categoria = Categoria::query()->findOrFail($id);
        $validated = $this->validar($request, $categoria->id);

        $categoria->update([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'activo' => $validated['estado'] === 'Activa',
        ]);

        return redirect()->route('categorias.index')->with('success', 'Categoría actualizada correctamente.');
    }

    public function toggleEstado(int $id): RedirectResponse
    {
        $categoria = Categoria::query()->findOrFail($id);
        $categoria->update(['activo' => ! $categoria->activo]);

        $mensaje = $categoria->activo
            ? 'Categoría activada correctamente.'
            : 'Categoría desactivada correctamente.';

        return redirect()->route('categorias.index')->with('success', $mensaje);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categorias', 'nombre')->ignore($ignorarId),
            ],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'estado' => ['required', 'in:Activa,Inactiva'],
        ], [
            'nombre.required' => 'Debe ingresar el nombre de la categoría.',
            'nombre.unique' => 'Ya existe una categoría con ese nombre.',
        ]);
    }
}
