<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cambios_titularidad')) {
            return;
        }

        Schema::create('cambios_titularidad', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lote_afiliado_id')->constrained('lote_afiliados')->cascadeOnDelete();
            $table->foreignId('afiliado_anterior_id')->constrained('afiliados')->restrictOnDelete();
            $table->foreignId('afiliado_nuevo_id')->constrained('afiliados')->restrictOnDelete();
            $table->date('fecha_cambio');
            $table->string('motivo', 500);
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fecha_cambio', 'lote_afiliado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_titularidad');
    }
};
