<?php

use App\Models\Categoria;
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

        DB::table('categorias')
            ->where('slug', Categoria::SLUG_DOMICILIARIA)
            ->update([
                'nombre' => 'domiciliar',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('categorias')) {
            return;
        }

        DB::table('categorias')
            ->where('slug', Categoria::SLUG_DOMICILIARIA)
            ->update([
                'nombre' => 'Domiciliaria',
                'updated_at' => now(),
            ]);
    }
};
