<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Afiliado;
use App\Models\LoteAfiliado;
use App\Services\MultasConsultaService;
use App\Services\Pagos\EstadoCuentaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Estado de cuenta de un lote: qué meses pagó, cuáles debe, sus recibos y sus multas.
 * Solo consulta en pantalla (sin imprimir ni Excel).
 */
class EstadoCuentaController extends Controller
{
    public function __construct(
        private readonly EstadoCuentaService $estadoCuenta,
        private readonly MultasConsultaService $consulta,
    ) {}

    public function show(Request $request, int $id): Response
    {
        $lote = $this->lote($request, $id);
        $datos = $this->estadoCuenta->paraLote($lote, $request->integer('gestion') ?: null);

        return Inertia::render('pagos/EstadoCuenta', [
            'lote' => $this->cabecera($lote),
            'estado' => $datos,
            'puedeCobrar' => $lote->estado !== Afiliado::ESTADO_BAJA
                && (bool) $request->user()?->hasPermission(Permission::GestionarPagos->value),
        ]);
    }

    private function lote(Request $request, int $id): LoteAfiliado
    {
        $lote = LoteAfiliado::query()->with(['afiliado', 'otb', 'categoria'])->findOrFail($id);
        abort_unless($lote->afiliado !== null, 404);
        $this->consulta->assertPuedeVerLote($request, $lote);

        return $lote;
    }

    /**
     * @return array<string, mixed>
     */
    private function cabecera(LoteAfiliado $lote): array
    {
        $afiliado = $lote->afiliado->conLote($lote);

        return [
            'id' => $lote->id,
            'numero_lote' => (string) ($lote->numero_lote ?? ''),
            'otb' => $lote->otb?->nombre,
            'categoria' => $lote->categoria?->nombre ?? LoteAfiliado::labelCategoria($lote->categoria_propiedad),
            'estado' => $lote->estado,
            'fecha_afiliacion' => $lote->fecha_afiliacion?->format('d/m/Y'),
            'forma_pago_afiliacion' => match ((string) $lote->forma_pago_afiliacion) {
                Afiliado::FORMA_CONTADO => 'Al contado',
                Afiliado::FORMA_PLAZO => 'A plazo',
                default => null,
            },
            'afiliado' => [
                'id' => $afiliado->id,
                'nombre_completo' => $afiliado->nombre_completo,
                'ci' => $afiliado->ciCompletoDisplay(),
            ],
            // Todos los lotes del afiliado (incluido el actual) para el selector tipo botones.
            'lotes' => $afiliado->lotes()
                ->with('otb:id,nombre')
                ->orderBy('id')
                ->get(['id', 'numero_lote', 'otb_id', 'estado'])
                ->map(fn (LoteAfiliado $item): array => [
                    'id' => $item->id,
                    'numero_lote' => (string) ($item->numero_lote ?? ''),
                    'otb' => $item->otb?->nombre,
                    'estado' => $item->estado,
                    'activo' => $item->id === $lote->id,
                ])
                ->all(),
        ];
    }
}
