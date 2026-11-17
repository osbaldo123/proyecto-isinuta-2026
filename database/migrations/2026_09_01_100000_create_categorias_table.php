<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->decimal('tarifa_agua', 10, 2)->default(17);
            $table->decimal('tarifa_alcantarillado', 10, 2)->default(30);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('categorias')->insert([
            [
                'slug' => 'comercial',
                'nombre' => 'Comercial',
                'descripcion' => 'Para tiendas de barrio, pensiones, talleres o cualquier negocio.',
                'tarifa_agua' => 17,
                'tarifa_alcantarillado' => 30,
                'activo' => true,
                'orden' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'domiciliaria',
                'nombre' => 'domiciliar',
                'descripcion' => 'Para el uso exclusivo de familias en sus hogares.',
                'tarifa_agua' => 17,
                'tarifa_alcantarillado' => 30,
                'activo' => true,
                'orden' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
