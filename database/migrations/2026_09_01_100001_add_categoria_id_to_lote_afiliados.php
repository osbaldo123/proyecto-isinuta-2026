<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lote_afiliados', function (Blueprint $table): void {
            $table->foreignId('categoria_id')
                ->nullable()
                ->after('observaciones')
                ->constrained('categorias')
                ->nullOnDelete();
        });

        $map = [
            'alojamiento' => 'domiciliaria',
            'comercio' => 'comercial',
            'banos' => 'comercial',
            'inquilinos' => 'domiciliaria',
        ];

        $categorias = DB::table('categorias')->pluck('id', 'slug');

        foreach ($map as $legacy => $slug) {
            if (! isset($categorias[$slug])) {
                continue;
            }

            DB::table('lote_afiliados')
                ->where('categoria_propiedad', $legacy)
                ->update(['categoria_id' => $categorias[$slug]]);
        }

        $domiciliariaId = $categorias['domiciliaria'] ?? null;

        if ($domiciliariaId) {
            DB::table('lote_afiliados')
                ->whereNull('categoria_id')
                ->update(['categoria_id' => $domiciliariaId]);
        }

        foreach (DB::table('categorias')->get() as $categoria) {
            $total = round((float) $categoria->tarifa_agua + (float) $categoria->tarifa_alcantarillado, 2);

            DB::table('lote_afiliados')
                ->where('categoria_id', $categoria->id)
                ->update([
                    'categoria_propiedad' => $categoria->slug,
                    'monto_categorizado' => $total,
                ]);
        }

        $this->sincronizarTarifasServicios();
    }

    public function down(): void
    {
        Schema::table('lote_afiliados', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('categoria_id');
        });
    }

    private function sincronizarTarifasServicios(): void
    {
        $categorias = DB::table('categorias')->get()->keyBy('id');
        $servicios = DB::table('servicios')->get();

        $lotes = DB::table('lote_afiliados')
            ->whereNotNull('categoria_id')
            ->pluck('categoria_id', 'id');

        foreach ($lotes as $loteId => $categoriaId) {
            $categoria = $categorias->get($categoriaId);

            if (! $categoria) {
                continue;
            }

            $pivots = DB::table('lote_servicio')
                ->where('lote_afiliado_id', $loteId)
                ->get();

            foreach ($pivots as $pivot) {
                $servicio = $servicios->firstWhere('id', $pivot->servicio_id);

                if (! $servicio) {
                    continue;
                }

                $tarifa = $this->tarifaParaServicio($categoria, $servicio);

                DB::table('lote_servicio')
                    ->where('id', $pivot->id)
                    ->update(['tarifa_acordada' => $tarifa]);
            }
        }
    }

    private function tarifaParaServicio(object $categoria, object $servicio): float
    {
        $tipo = mb_strtolower(trim((string) $servicio->tipo));

        if ($tipo === 'agua' || str_contains(mb_strtolower((string) $servicio->nombre), 'agua')) {
            return (float) $categoria->tarifa_agua;
        }

        if ($tipo === 'alcantarillado' || str_contains(mb_strtolower((string) $servicio->nombre), 'alcantar')) {
            return (float) $categoria->tarifa_alcantarillado;
        }

        return (float) $servicio->tarifa_mensual;
    }
};
