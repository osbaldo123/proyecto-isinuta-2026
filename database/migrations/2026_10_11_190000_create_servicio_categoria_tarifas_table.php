<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio_categoria_tarifas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->decimal('tarifa', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['servicio_id', 'categoria_id']);
        });

        $servicios = DB::table('servicios')
            ->whereNotIn(DB::raw('LOWER(TRIM(tipo))'), ['agua', 'alcantarillado'])
            ->get(['id', 'tarifa_mensual']);
        $categorias = DB::table('categorias')->pluck('id');
        $ahora = now();

        foreach ($servicios as $servicio) {
            foreach ($categorias as $categoriaId) {
                DB::table('servicio_categoria_tarifas')->insert([
                    'servicio_id' => $servicio->id,
                    'categoria_id' => $categoriaId,
                    'tarifa' => $servicio->tarifa_mensual,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_categoria_tarifas');
    }
};
