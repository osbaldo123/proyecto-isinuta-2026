<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['afiliados_codigo_unique', 'afiliados_codigo_lote_unique'] as $indexName) {
            try {
                Schema::table('afiliados', function (Blueprint $table) use ($indexName): void {
                    $table->dropUnique($indexName);
                });
            } catch (\Throwable) {
                // índice inexistente
            }
        }

        try {
            Schema::table('afiliados', function (Blueprint $table): void {
                $table->dropUnique(['codigo']);
            });
        } catch (\Throwable) {
            // índice inexistente
        }

        if (Schema::hasColumn('afiliados', 'codigo')) {
            Schema::table('afiliados', function (Blueprint $table): void {
                $table->dropColumn('codigo');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('afiliados', 'codigo')) {
            Schema::table('afiliados', function (Blueprint $table): void {
                $table->string('codigo', 20)->nullable()->after('id');
            });
        }
    }
};
