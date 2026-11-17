<?php

use App\Models\Categoria;
use App\Services\CategoriaTarifaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categorias')) {
            return;
        }

        $tarifas = [
            Categoria::SLUG_DOMICILIARIA => ['tarifa_agua' => 8, 'tarifa_alcantarillado' => 15],
            Categoria::SLUG_COMERCIAL => ['tarifa_agua' => 17, 'tarifa_alcantarillado' => 30],
        ];

        foreach ($tarifas as $slug => $valores) {
            DB::table('categorias')
                ->where('slug', $slug)
                ->update([
                    ...$valores,
                    'updated_at' => now(),
                ]);
        }

        $service = app(CategoriaTarifaService::class);

        Categoria::query()
            ->whereIn('slug', array_keys($tarifas))
            ->each(fn (Categoria $categoria) => $service->aplicarTarifasPorCategoria($categoria));
    }

    public function down(): void
    {
        if (! Schema::hasTable('categorias')) {
            return;
        }

        $tarifas = [
            Categoria::SLUG_DOMICILIARIA => ['tarifa_agua' => 17, 'tarifa_alcantarillado' => 30],
            Categoria::SLUG_COMERCIAL => ['tarifa_agua' => 17, 'tarifa_alcantarillado' => 30],
        ];

        foreach ($tarifas as $slug => $valores) {
            DB::table('categorias')
                ->where('slug', $slug)
                ->update([
                    ...$valores,
                    'updated_at' => now(),
                ]);
        }
    }
};
