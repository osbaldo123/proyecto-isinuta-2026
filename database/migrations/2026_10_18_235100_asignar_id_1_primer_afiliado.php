<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reasigna el primer afiliado (id=2) a id=1 y actualiza FKs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('afiliados')) {
            return;
        }

        if (DB::table('afiliados')->where('id', 1)->exists()) {
            return;
        }

        $origen = DB::table('afiliados')->orderBy('id')->value('id');
        if (! $origen) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (['lote_afiliados', 'multas', 'pagos', 'trabajos', 'users'] as $tabla) {
                if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'afiliado_id')) {
                    continue;
                }

                DB::table($tabla)
                    ->where('afiliado_id', $origen)
                    ->update(['afiliado_id' => 1]);
            }

            DB::table('afiliados')
                ->where('id', $origen)
                ->update(['id' => 1]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // No reversible de forma segura.
    }
};
