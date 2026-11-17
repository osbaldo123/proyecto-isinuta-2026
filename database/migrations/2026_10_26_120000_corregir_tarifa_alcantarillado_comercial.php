<?php

use App\Models\Categoria;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Servicio;
use App\Services\CategoriaTarifaService;
use Illuminate\Database\Migrations\Migration;

/**
 * Tarifa oficial de alcantarillado comercial: Bs 20 (la migración 2026_09_02 la dejó en Bs 30).
 * Corrige la categoría, la tarifa acordada de los lotes comerciales y las cuotas de
 * alcantarillado que aún no están pagadas por completo. Las cuotas ya pagadas a Bs 30
 * no se modifican: la diferencia (Bs 10 por mes) la define la directiva.
 */
return new class extends Migration
{
    private const TARIFA_OFICIAL = 20.0;

    public function up(): void
    {
        $comercial = Categoria::query()->where('slug', Categoria::SLUG_COMERCIAL)->first();

        if (! $comercial) {
            return;
        }

        $comercial->update(['tarifa_alcantarillado' => self::TARIFA_OFICIAL]);
        app(CategoriaTarifaService::class)->aplicarTarifasPorCategoria($comercial);

        $servicioIds = Servicio::query()->where('tipo', 'alcantarillado')->pluck('id');
        $loteIds = LoteAfiliado::query()->where('categoria_id', $comercial->id)->pluck('id');

        if ($servicioIds->isEmpty() || $loteIds->isEmpty()) {
            return;
        }

        Cuota::query()
            ->whereIn('servicio_id', $servicioIds)
            ->whereIn('lote_afiliado_id', $loteIds)
            ->where('estado', Cuota::ESTADO_PENDIENTE)
            ->where('monto_original', '!=', self::TARIFA_OFICIAL)
            ->orderBy('id')
            ->chunkById(200, function ($cuotas): void {
                foreach ($cuotas as $cuota) {
                    $pagado = round((float) $cuota->monto_original - (float) $cuota->saldo, 2);
                    $saldo = round(max(0, self::TARIFA_OFICIAL - $pagado), 2);

                    $cuota->update([
                        'monto_original' => self::TARIFA_OFICIAL,
                        'saldo' => $saldo,
                        'estado' => $saldo <= 0 ? Cuota::ESTADO_PAGADA : Cuota::ESTADO_PENDIENTE,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Corrección de datos: no se revierte.
    }
};
