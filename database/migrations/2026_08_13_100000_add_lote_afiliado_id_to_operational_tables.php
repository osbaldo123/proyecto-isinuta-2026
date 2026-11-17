<?php

use App\Models\Afiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\Trabajo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobros, multas y trabajos pertenecen a un lote/propiedad concreto,
 * no al titular en general (aunque comparta nombre y CI).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pagos', 'multas', 'trabajos'] as $tabla) {
            if (! Schema::hasColumn($tabla, 'lote_afiliado_id')) {
                Schema::table($tabla, function (Blueprint $table): void {
                    $table->foreignId('lote_afiliado_id')
                        ->nullable()
                        ->after('afiliado_id')
                        ->constrained('lote_afiliados')
                        ->nullOnDelete();
                });
            }
        }

        $this->vincularLotesExistentes();
    }

    public function down(): void
    {
        foreach (['pagos', 'multas', 'trabajos'] as $tabla) {
            if (Schema::hasColumn($tabla, 'lote_afiliado_id')) {
                Schema::table($tabla, function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('lote_afiliado_id');
                });
            }
        }
    }

    private function vincularLotesExistentes(): void
    {
        Afiliado::query()->orderBy('id')->chunkById(100, function ($afiliados): void {
            foreach ($afiliados as $afiliado) {
                $afiliado->sincronizarLotePrincipal();
            }
        });

        $resolver = function (?int $afiliadoId): ?int {
            if (! $afiliadoId) {
                return null;
            }

            $afiliado = Afiliado::query()->find($afiliadoId);

            if (! $afiliado) {
                return null;
            }

            return $afiliado->sincronizarLotePrincipal()->id;
        };

        Pago::query()->whereNull('lote_afiliado_id')->orderBy('id')->chunkById(200, function ($pagos) use ($resolver): void {
            foreach ($pagos as $pago) {
                $loteId = $resolver($pago->afiliado_id);
                if ($loteId) {
                    $pago->forceFill(['lote_afiliado_id' => $loteId])->save();
                }
            }
        });

        Multa::query()->whereNull('lote_afiliado_id')->orderBy('id')->chunkById(200, function ($multas) use ($resolver): void {
            foreach ($multas as $multa) {
                $loteId = $resolver($multa->afiliado_id);
                if ($loteId) {
                    $multa->forceFill(['lote_afiliado_id' => $loteId])->save();
                }
            }
        });

        Trabajo::query()->whereNull('lote_afiliado_id')->orderBy('id')->chunkById(200, function ($trabajos) use ($resolver): void {
            foreach ($trabajos as $trabajo) {
                $loteId = $resolver($trabajo->afiliado_id);
                if ($loteId) {
                    $trabajo->forceFill(['lote_afiliado_id' => $loteId])->save();
                }
            }
        });
    }
};
