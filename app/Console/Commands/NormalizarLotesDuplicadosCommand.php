<?php

namespace App\Console\Commands;

use App\Models\LoteAfiliado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizarLotesDuplicadosCommand extends Command
{
    protected $signature = 'lotes:normalizar-duplicados
                            {--dry-run : Simular sin guardar cambios}';

    protected $description = 'Asigna números de lote únicos y secuenciales (1, 2, 3…) sin repetir en todo el sistema';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $duplicados = LoteAfiliado::query()
            ->select('numero_lote', DB::raw('COUNT(*) as total'))
            ->groupBy('numero_lote')
            ->having('total', '>', 1)
            ->count();

        if ($duplicados === 0) {
            $this->info('Todos los números de lote ya son únicos.');

            return self::SUCCESS;
        }

        $this->warn("Hay {$duplicados} número(s) de lote repetido(s). Renumerando…");

        $renumerados = 0;
        $numero = 1;

        foreach (LoteAfiliado::query()->orderBy('id')->cursor() as $lote) {
            $actual = (string) $lote->numero_lote;
            $nuevo = (string) $numero++;

            if ($actual === $nuevo) {
                continue;
            }

            $this->line("  Lote #{$lote->id}: {$actual} → {$nuevo}");

            if (! $dryRun) {
                $lote->update(['numero_lote' => $nuevo]);
            }

            $renumerados++;
        }

        if ($dryRun) {
            $this->info("Simulación: se ajustarían {$renumerados} lote(s).");
        } else {
            $this->info("Listo: se ajustaron {$renumerados} lote(s). Cada lote tiene un número distinto.");
        }

        return self::SUCCESS;
    }
}
