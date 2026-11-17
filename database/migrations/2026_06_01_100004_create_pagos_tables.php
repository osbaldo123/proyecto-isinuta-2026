<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recibo', 30)->unique();
            $table->foreignId('afiliado_id')->constrained('afiliados')->cascadeOnDelete();
            $table->date('fecha_pago');
            $table->decimal('monto_total', 10, 2);
            $table->string('metodo_pago', 20)->default('efectivo');
            $table->string('estado', 20)->default('emitido');
            $table->foreignId('cobrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('pago_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->string('concepto', 50);
            $table->string('descripcion')->nullable();
            $table->decimal('monto', 10, 2);
            $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
            $table->foreignId('multa_id')->nullable()->constrained('multas')->nullOnDelete();
            $table->string('periodo', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_detalles');
        Schema::dropIfExists('pagos');
    }
};
