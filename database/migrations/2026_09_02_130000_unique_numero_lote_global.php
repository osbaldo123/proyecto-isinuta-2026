<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lote_afiliados', function (Blueprint $table): void {
            $table->dropUnique('lote_afiliados_zona_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::table('lote_afiliados', function (Blueprint $table): void {
            $table->unique(['zona_id', 'numero_lote'], 'lote_afiliados_zona_numero_unique');
        });
    }
};
