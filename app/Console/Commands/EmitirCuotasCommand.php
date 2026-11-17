<?php

namespace App\Console\Commands;

use App\Models\Afiliado;
use App\Models\LoteAfiliado;
use App\Services\CalculadorMultasService;
use Illuminate\Console\Command;

class EmitirCuotasCommand extends Command
{
    protected $signature = 'cuotas:emitir';

    protected $description = 'Emite las cuotas de los meses cerrados, aplica la multa trimestral (el día 1 del mes siguiente a 3 meses sin pagar) y actualiza los cortes';

    public function handle(CalculadorMultasService $multas): int
    {
        $cuotas = 0;
        $generadas = 0;

        LoteAfiliado::query()
            ->where('estado', '!=', Afiliado::ESTADO_BAJA)
            ->orderBy('id')
            ->chunkById(80, function ($lotes) use ($multas, &$cuotas, &$generadas): void {
                foreach ($lotes as $lote) {
                    $generadas += $multas->evaluarLote($lote);
                    $cuotas++;
                }
            });

        $cortes = $multas->sincronizarCortesPorMora();

        $this->info("Lotes revisados: {$cuotas}. Multas nuevas: {$generadas}. Estados actualizados: {$cortes}.");

        return self::SUCCESS;
    }
}
