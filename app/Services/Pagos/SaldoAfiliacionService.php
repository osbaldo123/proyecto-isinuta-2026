<?php

namespace App\Services\Pagos;

use App\Models\LoteAfiliado;

/**
 * Saldo de afiliación del lote (agua y alcantarillado por separado) al cobrar o anular.
 */
class SaldoAfiliacionService
{
    public function descontar(LoteAfiliado $lote, string $concepto, float $monto): void
    {
        $monto = round(max(0, $monto), 2);
        $agua = round((float) $lote->saldo_afiliacion_agua, 2);
        $alcantarillado = round((float) $lote->saldo_afiliacion_alcantarillado, 2);

        if ($this->esAlcantarillado($concepto)) {
            $alcantarillado = max(0, round($alcantarillado - $monto, 2));
        } elseif ($this->esAgua($concepto)) {
            $agua = max(0, round($agua - $monto, 2));
        } else {
            $tomaAgua = min($agua, $monto);
            $agua = round($agua - $tomaAgua, 2);
            $alcantarillado = max(0, round($alcantarillado - ($monto - $tomaAgua), 2));
        }

        $this->guardar($lote, $agua, $alcantarillado);
    }

    public function restaurar(LoteAfiliado $lote, string $concepto, float $monto): void
    {
        $monto = round(max(0, $monto), 2);
        $agua = round((float) $lote->saldo_afiliacion_agua, 2);
        $alcantarillado = round((float) $lote->saldo_afiliacion_alcantarillado, 2);
        $topeAgua = round((float) $lote->monto_afiliacion_agua, 2);
        $topeAlcantarillado = round((float) $lote->monto_afiliacion_alcantarillado, 2);

        if ($this->esAlcantarillado($concepto)) {
            $alcantarillado = round($alcantarillado + $monto, 2);
            if ($topeAlcantarillado > 0) {
                $alcantarillado = min($alcantarillado, $topeAlcantarillado);
            }
        } else {
            $agua = round($agua + $monto, 2);
            if ($topeAgua > 0) {
                $agua = min($agua, $topeAgua);
            }
        }

        $this->guardar($lote, $agua, $alcantarillado);
    }

    private function guardar(LoteAfiliado $lote, float $agua, float $alcantarillado): void
    {
        $lote->update([
            'saldo_afiliacion_agua' => $agua,
            'saldo_afiliacion_alcantarillado' => $alcantarillado,
            'saldo_afiliacion' => round($agua + $alcantarillado, 2),
        ]);
        $lote->refresh();
    }

    private function esAlcantarillado(string $concepto): bool
    {
        return str_contains($concepto, 'alcantarillado');
    }

    private function esAgua(string $concepto): bool
    {
        return str_contains($concepto, 'agua');
    }
}
