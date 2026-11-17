<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El código AFI identifica a la persona; varias propiedades (lotes)
 * del mismo afiliado comparten el mismo código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
        });

        Schema::table('afiliados', function (Blueprint $table) {
            $table->unique(['codigo', 'numero_lote'], 'afiliados_codigo_lote_unique');
        });
    }

    public function down(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropUnique('afiliados_codigo_lote_unique');
        });

        Schema::table('afiliados', function (Blueprint $table) {
            $table->unique('codigo');
        });
    }
};
