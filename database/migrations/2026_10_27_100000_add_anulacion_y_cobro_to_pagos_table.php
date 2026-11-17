<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los recibos ya no se borran: se anulan con motivo, usuario y fecha, y conservan su número.
 * `cobro_id` agrupa los recibos que se emitieron juntos en un mismo cobro (uno por propósito).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->uuid('cobro_id')->nullable()->after('lote_afiliado_id')->index();
            $table->timestamp('anulado_en')->nullable()->after('observaciones');
            $table->foreignId('anulado_por')->nullable()->after('anulado_en')->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable()->after('anulado_por');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('anulado_por');
            $table->dropIndex(['cobro_id']);
            $table->dropColumn(['cobro_id', 'anulado_en', 'motivo_anulacion']);
        });
    }
};
