<?php

use App\Models\PagoDetalle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alinea afiliaciones agua/alcantarillado con los servicios ya asignados a cada lote.
 * Regla de negocio: el acceso al servicio implica haber pasado por la afiliación.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tipoAguaId = DB::table('tipos_afiliacion')->where('codigo', 'agua')->value('id');
        $tipoAlcId = DB::table('tipos_afiliacion')->where('codigo', 'alcantarillado')->value('id');

        if (! $tipoAguaId || ! $tipoAlcId) {
            return;
        }

        $montoAgua = (float) (DB::table('tipos_afiliacion')->where('id', $tipoAguaId)->value('monto')
            ?: PagoDetalle::MONTO_AFILIACION_AGUA);
        $montoAlc = (float) (DB::table('tipos_afiliacion')->where('id', $tipoAlcId)->value('monto')
            ?: PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO);

        $servicioAguaIds = DB::table('servicios')
            ->where(function ($q): void {
                $q->where('tipo', 'agua')
                    ->orWhere('nombre', 'like', '%agua%');
            })
            ->where('nombre', 'not like', '%alcantar%')
            ->pluck('id')
            ->all();

        $servicioAlcIds = DB::table('servicios')
            ->where(function ($q): void {
                $q->where('tipo', 'alcantarillado')
                    ->orWhere('nombre', 'like', '%alcantar%');
            })
            ->pluck('id')
            ->all();

        $now = now();

        $this->backfillTipoDesdeServicios($servicioAguaIds, (int) $tipoAguaId, $montoAgua, 'monto_afiliacion_agua', $now);
        $this->backfillTipoDesdeServicios($servicioAlcIds, (int) $tipoAlcId, $montoAlc, 'monto_afiliacion_alcantarillado', $now);

        // Recalcular total de afiliación = agua + alcantarillado (sin tocar saldo).
        DB::statement('
            UPDATE lote_afiliados
            SET monto_afiliacion = ROUND(COALESCE(monto_afiliacion_agua, 0) + COALESCE(monto_afiliacion_alcantarillado, 0), 2)
        ');
    }

    /**
     * @param  list<int|string>  $servicioIds
     */
    private function backfillTipoDesdeServicios(
        array $servicioIds,
        int $tipoId,
        float $montoCatalogo,
        string $columnaMonto,
        mixed $now,
    ): void {
        if ($servicioIds === []) {
            return;
        }

        $loteIds = DB::table('lote_servicio')
            ->whereIn('servicio_id', $servicioIds)
            ->distinct()
            ->pluck('lote_afiliado_id');

        foreach ($loteIds->chunk(200) as $chunk) {
            $yaTiene = DB::table('lote_tipo_afiliacion')
                ->where('tipo_afiliacion_id', $tipoId)
                ->whereIn('lote_afiliado_id', $chunk)
                ->pluck('lote_afiliado_id')
                ->all();
            $yaTieneSet = array_flip($yaTiene);

            $filas = [];
            $lotesParaMonto = [];

            foreach ($chunk as $loteId) {
                $loteId = (int) $loteId;
                if (! isset($yaTieneSet[$loteId])) {
                    $filas[] = [
                        'lote_afiliado_id' => $loteId,
                        'tipo_afiliacion_id' => $tipoId,
                        'monto' => $montoCatalogo,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                $lotesParaMonto[] = $loteId;
            }

            if ($filas !== []) {
                DB::table('lote_tipo_afiliacion')->insert($filas);
            }

            if ($lotesParaMonto !== []) {
                DB::table('lote_afiliados')
                    ->whereIn('id', $lotesParaMonto)
                    ->where($columnaMonto, '<=', 0)
                    ->update([$columnaMonto => $montoCatalogo]);
            }
        }
    }

    public function down(): void
    {
        // No revertimos el backfill: los vínculos reflejan el estado real de servicios.
    }
};
