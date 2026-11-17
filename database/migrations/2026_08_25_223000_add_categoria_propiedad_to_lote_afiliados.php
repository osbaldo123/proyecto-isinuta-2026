<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lote_afiliados', function (Blueprint $table): void {
            $table->string('categoria_propiedad', 30)->nullable()->after('observaciones');
            $table->unsignedSmallInteger('cantidad_inquilinos')->nullable()->after('categoria_propiedad');
            $table->decimal('monto_categorizado', 12, 2)->nullable()->after('cantidad_inquilinos');
        });
    }

    public function down(): void
    {
        Schema::table('lote_afiliados', function (Blueprint $table): void {
            $table->dropColumn(['categoria_propiedad', 'cantidad_inquilinos', 'monto_categorizado']);
        });
    }
};
