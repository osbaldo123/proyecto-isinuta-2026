<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateConfiguracionRequest;
use App\Models\Configuracion;
use App\Services\ActivityLogger;
use App\Services\CalculadorMultasService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracionController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly CalculadorMultasService $calculadorMultas,
    ) {}

    /**
     * @return array<int, array{clave: string, label: string, valor: float|int, tipo: string, descripcion: string|null}>
     */
    private function configuraciones(): array
    {
        $items = Configuracion::query()->orderBy('id')->get();

        return $items->map(fn (Configuracion $item) => [
            'clave' => $item->clave,
            'label' => $this->labelFor($item->clave),
            'valor' => match ($item->tipo) {
                'integer' => (int) $item->valor,
                'decimal' => (float) $item->valor,
                default => $item->valor,
            },
            'tipo' => $item->tipo,
            'descripcion' => $item->descripcion,
        ])->all();
    }

    private function labelFor(string $clave): string
    {
        return match ($clave) {
            Configuracion::MULTA_TRIMESTRAL => 'Multa trimestral (Bs)',
            Configuracion::MESES_MULTA_TRIMESTRAL => 'Meses sin pagar (multa trimestral)',
            Configuracion::MESES_CORTE_SERVICIO => 'Meses vencidos (corte del servicio)',
            default => $clave,
        };
    }

    public function index(): Response
    {
        return Inertia::render('configuracion/Index', [
            'configuraciones' => $this->configuraciones(),
        ]);
    }

    public function update(UpdateConfiguracionRequest $request): RedirectResponse
    {
        $mesesCorteAntes = (int) Configuracion::get(Configuracion::MESES_CORTE_SERVICIO, 12);

        foreach (Configuracion::DEFINICIONES as $clave => $definicion) {
            Configuracion::set(
                $clave,
                $request->validated($clave),
                $definicion['tipo'],
                $definicion['descripcion'],
            );
        }

        // Con otros meses para el corte, los lotes cortados se vuelven a revisar con el número nuevo.
        if ((int) $request->validated(Configuracion::MESES_CORTE_SERVICIO) !== $mesesCorteAntes) {
            $this->calculadorMultas->recalcularCortes();
        }

        $this->activityLogger->log(
            $request->user(),
            'Multas',
            'Actualización de la configuración de multas por mora',
        );

        return redirect()
            ->route('configuracion.index')
            ->with('success', 'Configuración de multas por mora actualizada correctamente.');
    }
}
