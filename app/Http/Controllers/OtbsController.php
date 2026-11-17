<?php

namespace App\Http\Controllers;

use App\Models\LoteAfiliado;
use App\Models\Otb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OtbsController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = trim((string) $request->query('q', ''));

        $otbs = Otb::query()
            ->addSelect([
                'afiliados_count' => LoteAfiliado::query()
                    ->selectRaw('count(distinct afiliado_id)')
                    ->whereColumn('otb_id', 'otbs.id'),
                'lotes_count' => LoteAfiliado::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('otb_id', 'otbs.id'),
            ])
            ->when($busqueda !== '', function ($query) use ($busqueda): void {
                $like = "%{$busqueda}%";
                $query->where(function ($q) use ($like, $busqueda): void {
                    $q->where('nombre', 'like', $like)
                        ->orWhere('descripcion', 'like', $like);

                    $estado = mb_strtolower($busqueda);
                    if (str_contains('activa', $estado) || str_contains($estado, 'activ')) {
                        $q->orWhere('activa', true);
                    }
                    if (str_contains('inactiva', $estado) || str_contains($estado, 'inactiv')) {
                        $q->orWhere('activa', false);
                    }
                });
            })
            ->orderBy('nombre')
            ->get()
            ->map(fn (Otb $otb): array => [
                'id' => $otb->id,
                'nombre' => $otb->nombre,
                'descripcion' => $otb->descripcion ?? '',
                'afiliados' => (int) $otb->afiliados_count,
                'lotes' => (int) $otb->lotes_count,
                'estado' => $otb->activa ? 'Activa' : 'Inactiva',
            ]);

        return Inertia::render('otb/Index', [
            'otbs' => $otbs,
            'resumen' => [
                // Misma métrica que Afiliados (personas únicas), no la suma por OTB.
                'afiliados' => (int) LoteAfiliado::query()->distinct()->count('afiliado_id'),
                'lotes' => (int) LoteAfiliado::query()->count(),
            ],
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('otb/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validar($request);

        Otb::create([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'activa' => $validated['estado'] === 'Activa',
            'tipo' => 'OTB',
        ]);

        return redirect()->route('otb.index')->with('success', 'OTB creada correctamente.');
    }

    public function edit(int $id): Response
    {
        $otb = Otb::query()->findOrFail($id);

        $afiliados = (int) LoteAfiliado::query()
            ->where('otb_id', $otb->id)
            ->distinct()
            ->count('afiliado_id');
        $lotes = (int) LoteAfiliado::query()
            ->where('otb_id', $otb->id)
            ->count();

        return Inertia::render('otb/Edit', [
            'otb' => [
                'id' => $otb->id,
                'nombre' => $otb->nombre,
                'descripcion' => $otb->descripcion ?? '',
                'afiliados' => $afiliados,
                'lotes' => $lotes,
                'estado' => $otb->activa ? 'Activa' : 'Inactiva',
            ],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $otb = Otb::query()->findOrFail($id);
        $validated = $this->validar($request, $otb->id);

        $otb->update([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'activa' => $validated['estado'] === 'Activa',
        ]);

        return redirect()->route('otb.index')->with('success', 'OTB actualizada correctamente.');
    }

    public function toggleEstado(int $id): RedirectResponse
    {
        $otb = Otb::query()->findOrFail($id);
        $otb->update(['activa' => ! $otb->activa]);

        $mensaje = $otb->activa
            ? 'La OTB quedó activa.'
            : 'La OTB quedó inactiva.';

        return redirect()->route('otb.index')->with('success', $mensaje);
    }

    public function destroy(int $id): RedirectResponse
    {
        $otb = Otb::query()->withCount('lotes')->findOrFail($id);

        if ($otb->lotes_count > 0) {
            return redirect()
                ->route('otb.index')
                ->with('error', 'No se puede eliminar una OTB con afiliados asociados.');
        }

        $otb->delete();

        return redirect()->route('otb.index')->with('success', 'OTB eliminada correctamente.');
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
                Rule::unique('otbs', 'nombre')->ignore($ignorarId),
            ],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'estado' => ['required', 'in:Activa,Inactiva'],
        ]);
    }
}
