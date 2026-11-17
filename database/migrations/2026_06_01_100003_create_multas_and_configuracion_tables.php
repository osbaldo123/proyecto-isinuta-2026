<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afiliado_id')->constrained('afiliados')->cascadeOnDelete();
            $table->date('fecha_generacion');
            $table->string('motivo', 50);
            $table->string('descripcion')->nullable();
            $table->decimal('monto', 10, 2);
            $table->string('estado', 20)->default('pendiente');
            $table->foreignId('generada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->string('valor');
            $table->string('tipo', 20)->default('string');
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion');
        Schema::dropIfExists('multas');
    }
};
