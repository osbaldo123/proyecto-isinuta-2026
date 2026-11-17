<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Limpia datos de prueba y claves de configuración obsoletas.
 *
 * No elimina tablas: afiliados/lote_afiliados no son duplicados (persona vs lote).
 * alarmas/calendario/passkeys/jobs vacías se mantienen porque el sistema las usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Servicio de prueba "Empresarial(prueba)" sin lotes ni trabajos.
        $servicioIds = DB::table('servicios')
            ->where('tipo', 'otro')
            ->where(function ($q): void {
                $q->where('nombre', 'like', '%prueba%')
                    ->orWhere('nombre', 'Empresarial(prueba)');
            })
            ->pluck('id');

        if ($servicioIds->isNotEmpty()) {
            DB::table('servicio_categoria_tarifas')->whereIn('servicio_id', $servicioIds)->delete();
            DB::table('lote_servicio')->whereIn('servicio_id', $servicioIds)->delete();
            DB::table('trabajos')->whereIn('servicio_id', $servicioIds)->delete();
            DB::table('servicios')->whereIn('id', $servicioIds)->delete();
        }

        // Tipo de afiliación de prueba sin lotes asociados.
        $tipoIds = DB::table('tipos_afiliacion')
            ->whereNotIn('codigo', ['agua', 'alcantarillado'])
            ->where(function ($q): void {
                $q->where('nombre', 'like', '%prueba%')
                    ->orWhere('nombre', 'Empresarial(prueba)');
            })
            ->pluck('id');

        if ($tipoIds->isNotEmpty()) {
            $enUso = DB::table('lote_tipo_afiliacion')
                ->whereIn('tipo_afiliacion_id', $tipoIds)
                ->pluck('tipo_afiliacion_id')
                ->unique();

            $borrar = $tipoIds->diff($enUso);
            if ($borrar->isNotEmpty()) {
                DB::table('tipos_afiliacion')->whereIn('id', $borrar)->delete();
            }
        }

        // Categoría "Otro" de prueba: sin lotes ni tarifas.
        $otroIds = DB::table('categorias')
            ->where(function ($q): void {
                $q->where('slug', 'otro')
                    ->orWhereRaw('LOWER(TRIM(nombre)) = ?', ['otro']);
            })
            ->pluck('id');

        foreach ($otroIds as $categoriaId) {
            $lotes = DB::table('lote_afiliados')->where('categoria_id', $categoriaId)->count();
            $tarifas = DB::table('servicio_categoria_tarifas')->where('categoria_id', $categoriaId)->count();
            if ($lotes === 0 && $tarifas === 0) {
                DB::table('categorias')->where('id', $categoriaId)->delete();
            }
        }

        // Claves de configuración reemplazadas por categorias / tipos_afiliacion.
        DB::table('configuracion')->whereIn('clave', [
            'tarifa_agua',
            'tarifa_alcantarillado',
            'afiliacion_contado',
            'afiliacion_plazo',
        ])->delete();
    }

    public function down(): void
    {
        // No se restauran datos de prueba.
    }
};
