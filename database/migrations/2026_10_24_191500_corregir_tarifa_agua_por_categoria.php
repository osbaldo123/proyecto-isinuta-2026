<?php

use App\Models\Categoria;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Servicio;
use App\Services\CategoriaTarifaService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * El agua domiciliaria debe cobrarse a Bs 8 y la comercial a Bs 17.
     * Bs 10 y Bs 20 no son la tarifa de la categoría.
     */
    public function up(): void
    {
        $tarifasAgua = [
            Categoria::SLUG_DOMICILIARIA => 8.0,
            Categoria::SLUG_COMERCIAL => 17.0,
        ];

        $servicioAguaId = Servicio::query()->where('tipo', 'agua')->value('id');
        $tarifaService = app(CategoriaTarifaService::class);

        foreach ($tarifasAgua as $slug => $monto) {
            $categoria = Categoria::query()->where('slug', $slug)->first();

            if (! $categoria) {
                continue;
            }

            $categoria->update(['tarifa_agua' => $monto]);
            $tarifaService->aplicarTarifasPorCategoria($categoria);

            if (! $servicioAguaId) {
                continue;
            }

            $loteIds = LoteAfiliado::query()
                ->where('categoria_id', $categoria->id)
                ->pluck('id');

            Cuota::query()
                ->where('servicio_id', $servicioAguaId)
                ->where('estado', Cuota::ESTADO_PENDIENTE)
                ->whereIn('lote_afiliado_id', $loteIds)
                ->orderBy('id')
                ->chunkById(200, function ($cuotas) use ($monto): void {
                    foreach ($cuotas as $cuota) {
                        $pagado = round((float) $cuota->monto_original - (float) $cuota->saldo, 2);
                        $saldo = round(max(0, $monto - $pagado), 2);

                        $cuota->update([
                            'monto_original' => $monto,
                            'saldo' => $saldo,
                            'estado' => $saldo <= 0 ? Cuota::ESTADO_PAGADA : Cuota::ESTADO_PENDIENTE,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        $tarifasAgua = [
            Categoria::SLUG_DOMICILIARIA => 10.0,
            Categoria::SLUG_COMERCIAL => 20.0,
        ];

        $tarifaService = app(CategoriaTarifaService::class);

        foreach ($tarifasAgua as $slug => $monto) {
            $categoria = Categoria::query()->where('slug', $slug)->first();

            if (! $categoria) {
                continue;
            }

            $categoria->update(['tarifa_agua' => $monto]);
            $tarifaService->aplicarTarifasPorCategoria($categoria);
        }
    }
};
