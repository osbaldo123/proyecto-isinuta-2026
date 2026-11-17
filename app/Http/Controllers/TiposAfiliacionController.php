<?php

namespace App\Http\Controllers;

use App\Models\TipoAfiliacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TiposAfiliacionController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = trim((string) $request->query('q', ''));

        $tipos = TipoAfiliacion::query()
            ->withCount('lotes')
            ->when($busqueda !== '', function ($query) use ($busqueda): void {
                $like = "%{$busqueda}%";
                $query->where(function ($q) use ($like, $busqueda): void {
                    $q->where('nombre', 'like', $like)
                        ->orWhere('descripcion', 'like', $like)
                        ->orWhere('codigo', 'like', $like);

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
            ->map(fn (TipoAfiliacion $tipo): array => [
                'id' => $tipo->id,
                'codigo' => $tipo->codigo,
                'nombre' => $tipo->nombre,
                'descripcion' => $tipo->descripcion ?? '',
                'modalidad' => trim((string) ($tipo->modalidad ?? '')),
                'monto' => (float) $tipo->monto,
                'monto_plazo' => $tipo->montoPlazo(),
                'lotes' => (int) $tipo->lotes_count,
                'estado' => $tipo->activo ? 'Activa' : 'Inactiva',
                'orden' => (int) $tipo->orden,
            ]);

        return Inertia::render('afiliaciones/Index', [
            'tipos' => $tipos,
            'filtros' => ['q' => $busqueda],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('afiliaciones/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validarConLineas($request);
        $montos = $this->montosDesdeLineas($validated['lineas']);

        TipoAfiliacion::create([
            'codigo' => TipoAfiliacion::generarCodigoUnico($validated['nombre']),
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'modalidad' => $montos['modalidad_etiqueta'],
            'monto' => $montos['monto'],
            'monto_plazo' => $montos['monto_plazo'],
            'activo' => $validated['estado'] === 'Activa',
            'orden' => (int) ($validated['orden'] ?? ((int) TipoAfiliacion::query()->max('orden') + 1)),
        ]);

        return redirect()->route('afiliaciones.index')->with('success', 'Tipo de afiliación creado correctamente.');
    }

    public function edit(int $id): Response
    {
        $tipo = TipoAfiliacion::query()->withCount('lotes')->findOrFail($id);

        $esSistema = in_array($tipo->codigo, [
            TipoAfiliacion::CODIGO_AGUA,
            TipoAfiliacion::CODIGO_ALCANTARILLADO,
        ], true);

        return Inertia::render('afiliaciones/Edit', [
            'tipo' => [
                'id' => $tipo->id,
                'codigo' => $tipo->codigo,
                'nombre' => $tipo->nombre,
                'descripcion' => $tipo->descripcion ?? '',
                'modalidad' => trim((string) ($tipo->modalidad ?? '')),
                'monto' => (float) $tipo->monto,
                'monto_plazo' => $tipo->montoPlazo(),
                'orden' => (int) $tipo->orden,
                'lotes' => (int) $tipo->lotes_count,
                'estado' => $tipo->activo ? 'Activa' : 'Inactiva',
                'es_sistema' => $esSistema,
            ],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tipo = TipoAfiliacion::query()->findOrFail($id);
        $validated = $this->validarConLineas($request, $tipo->id);
        $montos = $this->montosDesdeLineas($validated['lineas']);

        $tipo->update([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'modalidad' => $montos['modalidad_etiqueta'],
            'monto' => $montos['monto'],
            'monto_plazo' => $montos['monto_plazo'],
            'activo' => $validated['estado'] === 'Activa',
            'orden' => (int) ($validated['orden'] ?? $tipo->orden),
        ]);

        return redirect()->route('afiliaciones.index')->with('success', 'Tipo de afiliación actualizado correctamente.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $tipo = TipoAfiliacion::query()->withCount('lotes')->findOrFail($id);

        if (in_array($tipo->codigo, [TipoAfiliacion::CODIGO_AGUA, TipoAfiliacion::CODIGO_ALCANTARILLADO], true)) {
            return redirect()
                ->route('afiliaciones.index')
                ->with('error', 'No se puede eliminar la afiliación base de agua o alcantarillado. Puede desactivarla.');
        }

        if ((int) $tipo->lotes_count > 0) {
            return redirect()
                ->route('afiliaciones.index')
                ->with('error', 'No se puede eliminar: hay lotes con esta afiliación. Desactívela en su lugar.');
        }

        $tipo->delete();

        return redirect()->route('afiliaciones.index')->with('success', 'Tipo de afiliación eliminado.');
    }

    /**
     * @param  list<array{modalidad: string, monto: mixed, estado?: string}>  $lineas
     * @return array{monto: float, monto_plazo: float, modalidad_etiqueta: string}
     */
    private function montosDesdeLineas(array $lineas): array
    {
        $monto = 0.0;
        $montoPlazo = 0.0;
        $etiquetas = [];

        foreach ($lineas as $linea) {
            $estadoLinea = (string) ($linea['estado'] ?? 'Activa');
            if ($estadoLinea !== 'Activa') {
                continue;
            }

            $etiqueta = trim(preg_replace('/\s+/u', ' ', (string) ($linea['modalidad'] ?? '')) ?? '');
            if ($etiqueta === '') {
                continue;
            }

            $precio = round((float) ($linea['monto'] ?? 0), 2);
            $etiquetas[] = $etiqueta;

            if ($this->normalizarModalidad($etiqueta) === 'plazo') {
                $montoPlazo = $precio;
            } else {
                $monto = $precio;
            }
        }

        return [
            'monto' => $monto,
            'monto_plazo' => $montoPlazo,
            'modalidad_etiqueta' => implode(' / ', $etiquetas),
        ];
    }

    private function normalizarModalidad(string $texto): string
    {
        $texto = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $texto) ?? ''));

        if (
            $texto === 'plazo'
            || $texto === 'a plazo'
            || str_contains($texto, 'plazo')
            || str_contains($texto, 'cuota')
        ) {
            return 'plazo';
        }

        return 'contado';
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function validarConLineas(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:120',
                Rule::unique('tipos_afiliacion', 'nombre')->ignore($ignoreId),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.modalidad' => ['required', 'string', 'max:50'],
            'lineas.*.monto' => ['required', 'numeric', 'min:0'],
            'lineas.*.estado' => ['required', Rule::in(['Activa', 'Inactiva'])],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'estado' => ['required', Rule::in(['Activa', 'Inactiva'])],
        ], [
            'nombre.required' => 'Debe indicar el nombre.',
            'nombre.unique' => 'Ya existe un tipo de afiliación con ese nombre.',
            'lineas.required' => 'Debe agregar al menos una línea de modalidad y monto.',
            'lineas.min' => 'Debe agregar al menos una línea de modalidad y monto.',
            'lineas.*.modalidad.required' => 'Debe indicar la modalidad en cada línea.',
            'lineas.*.monto.required' => 'Debe indicar el monto en cada línea.',
            'lineas.*.estado.in' => 'El estado de la línea debe ser Activa o Inactiva.',
            'estado.in' => 'El estado debe ser Activa o Inactiva.',
        ]);

        $activas = collect($validated['lineas'])
            ->filter(fn (array $linea): bool => ($linea['estado'] ?? '') === 'Activa')
            ->count();

        if ($activas < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lineas' => 'Debe haber al menos una modalidad activa.',
            ]);
        }

        return $validated;
    }
}
