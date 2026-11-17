<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un afiliado (CI + complemento) no puede repetir el mismo número de lote,
 * aunque las propiedades estén en OTBs distintas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropUnique('afiliados_ci_zona_lote_unique');
        });

        Schema::table('afiliados', function (Blueprint $table) {
            $table->unique(
                ['ci', 'complemento', 'numero_lote'],
                'afiliados_ci_lote_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropUnique('afiliados_ci_lote_unique');
        });

        Schema::table('afiliados', function (Blueprint $table) {
            $table->unique(
                ['ci', 'complemento', 'zona_id', 'numero_lote'],
                'afiliados_ci_zona_lote_unique'
            );
        });
    }
};
