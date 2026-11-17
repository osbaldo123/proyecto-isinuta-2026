<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de transferencias (cesiones) de un lote entre afiliados.
 *
 * El lote no se duplica ni se borra: solo cambia su titular (lote_afiliados.afiliado_id).
 * Cada transferencia queda registrada aquí con una copia del nombre y CI de ambas personas,
 * para que el historial se conserve aunque luego cambien o se eliminen sus datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cesiones_lote')) {
            return;
        }

        Schema::create('cesiones_lote', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lote_afiliado_id')->constrained('lote_afiliados')->cascadeOnDelete();

            $table->foreignId('afiliado_anterior_id')->nullable()->constrained('afiliados')->nullOnDelete();
            $table->string('nombre_anterior', 210);
            $table->string('ci_anterior', 20)->nullable();

            $table->foreignId('afiliado_nuevo_id')->nullable()->constrained('afiliados')->nullOnDelete();
            $table->string('nombre_nuevo', 210);
            $table->string('ci_nuevo', 20)->nullable();

            $table->date('fecha_cesion');
            $table->string('motivo', 500);
            $table->string('documento_respaldo', 120)->nullable();

            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_actualizacion')->nullable();

            $table->index(['lote_afiliado_id', 'fecha_cesion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cesiones_lote');
    }
};
