<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\LoteAfiliado;
use App\Models\Servicio;

class CategoriaTarifaService
{
    public function tarifaParaServicio(Categoria $categoria, Servicio $servicio): float
    {
        return $servicio->tarifaParaCategoria($categoria);
    }

    /**
     * Guarda la tarifa del servicio para la categoría; devuelve true si el monto cambió.
     */
    public function asignarTarifa(Servicio $servicio, Categoria $categoria, float $monto): bool
    {
        $monto = round($monto, 2);
        $servicio->loadMissing('tarifasCategoria');
        $cambio = round($servicio->tarifaParaCategoria($categoria), 2) !== $monto;
        $tipo = $servicio->tipoTarifaPorCategoria();

        if ($tipo === null) {
            $servicio->tarifasCategoria()->syncWithoutDetaching([$categoria->id => ['tarifa' => $monto]]);
            $servicio->unsetRelation('tarifasCategoria');
        } elseif ($cambio) {
            $categoria->update([$tipo === 'agua' ? 'tarifa_agua' : 'tarifa_alcantarillado' => $monto]);
        }

        return $cambio;
    }

    public function aplicarTarifasLote(LoteAfiliado $lote): void
    {
        $lote->loadMissing('categoria');

        $categoria = $lote->categoria;

        if (! $categoria) {
            return;
        }

        $total = 0.0;

        foreach ($lote->servicios()->get() as $servicio) {
            $tarifa = $this->tarifaParaServicio($categoria, $servicio);
            $total += $tarifa;

            $lote->servicios()->updateExistingPivot($servicio->id, [
                'tarifa_acordada' => $tarifa,
            ]);
        }

        $lote->update([
            'categoria_propiedad' => $categoria->slug,
            'monto_categorizado' => round($total, 2),
        ]);
    }

    public function aplicarTarifasPorCategoria(Categoria $categoria): void
    {
        LoteAfiliado::query()
            ->where('categoria_id', $categoria->id)
            ->each(fn (LoteAfiliado $lote) => $this->aplicarTarifasLote($lote));
    }
}
