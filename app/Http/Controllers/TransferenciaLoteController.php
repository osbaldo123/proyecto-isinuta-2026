<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferirLoteRequest;
use App\Models\Afiliado;
use App\Models\LoteAfiliado;
use App\Services\AfiliadoUserService;
use App\Services\TransferenciaLoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Transferencia (cesión) de un lote a otra persona.
 * Solo se permite cuando el lote tiene todas sus deudas canceladas.
 */
class TransferenciaLoteController extends Controller
{
    public function __construct(
        private readonly TransferenciaLoteService $transferencias,
        private readonly AfiliadoUserService $afiliadoUser,
    ) {}

    public function create(Request $request, int $id): Response
    {
        $lote = LoteAfiliado::query()
            ->with(['afiliado', 'otb:id,nombre', 'categoria', 'serviciosActivos'])
            ->findOrFail($id);
        abort_unless($lote->afiliado !== null, 404);

        $deuda = $this->transferencias->deudaDelLote($lote, $request->user());
        $titular = $lote->afiliado;

        return Inertia::render('afiliados/Transferir', [
            'lote' => [
                'id' => $lote->id,
                'numero_lote' => $lote->numero_lote,
                'otb' => $lote->otb?->nombre,
                'direccion' => $lote->direccion,
                'categoria' => $lote->categoria?->nombre,
                'estado' => $lote->estado,
                'servicios' => $lote->serviciosActivos->pluck('nombre')->values()->all(),
            ],
            'titular' => [
                'id' => $titular->id,
                'nombre_completo' => $titular->nombre_completo,
                'ci' => $titular->ci,
                'otros_lotes' => $this->lotesDe($titular, $lote->id),
            ],
            'deuda' => $deuda,
            'bloqueo' => $this->transferencias->motivoBloqueo($lote, $deuda),
            'hoy' => now()->toDateString(),
            'historial' => $this->transferencias->historial($lote),
        ]);
    }

    /**
     * Busca por CI a la persona que recibirá el lote (paso 2 del formulario).
     */
    public function buscarTitular(Request $request, int $id): JsonResponse
    {
        $lote = LoteAfiliado::query()->findOrFail($id);
        $ci = preg_replace('/\D/', '', (string) $request->query('ci', ''));

        if (strlen($ci) !== 8) {
            return response()->json(['valido' => false, 'encontrado' => false]);
        }

        $persona = Afiliado::query()->where('ci', $ci)->orderBy('id')->first();

        if (! $persona) {
            return response()->json(['valido' => true, 'encontrado' => false]);
        }

        return response()->json([
            'valido' => true,
            'encontrado' => true,
            'es_titular_actual' => $persona->id === $lote->afiliado_id,
            'persona' => [
                'nombre_completo' => $persona->nombre_completo,
                'nombres' => $persona->nombres,
                'apellidos' => $persona->apellidos,
                'ci' => $persona->ci,
                'telefono' => $persona->telefono,
                'lotes' => $this->lotesDe($persona),
            ],
        ]);
    }

    public function store(TransferirLoteRequest $request, int $id): RedirectResponse
    {
        $lote = LoteAfiliado::query()->findOrFail($id);
        $cesion = $this->transferencias->transferir($lote, $request->validated(), $request->user());

        $mensaje = "Transferencia registrada: el lote {$lote->numero_lote} ahora pertenece a {$cesion->nombre_nuevo}.";

        try {
            $nuevo = Afiliado::query()->find($cesion->afiliado_nuevo_id);
            if (config('sistema.usuarios_afiliados') && $nuevo && ! $nuevo->user) {
                $this->afiliadoUser->createForAfiliado($nuevo);
                $mensaje .= ' Se generó su usuario de consulta (acceso por CI).';
            }
        } catch (\Throwable $e) {
            report($e);
            $mensaje .= ' No se pudo crear su usuario de consulta.';
        }

        return redirect()
            ->route('afiliados.show', $lote->id)
            ->with('success', $mensaje);
    }

    /**
     * @return list<array{numero_lote: string|null, otb: string|null}>
     */
    private function lotesDe(Afiliado $persona, ?int $excepto = null): array
    {
        return $persona->lotes()
            ->with('otb:id,nombre')
            ->when($excepto !== null, fn ($q) => $q->where('id', '!=', $excepto))
            ->get()
            ->map(fn (LoteAfiliado $l): array => [
                'numero_lote' => $l->numero_lote,
                'otb' => $l->otb?->nombre,
            ])
            ->values()
            ->all();
    }
}
