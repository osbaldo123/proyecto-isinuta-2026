<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('descripcion', 500)->nullable();
            $table->string('tipo', 50)->default('OTB');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 100);
            $table->string('tipo', 50)->default('agua');
            $table->decimal('tarifa_mensual', 10, 2)->default(0);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('afiliados', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('ci', 8)->unique();
            $table->string('telefono', 20)->nullable();
            $table->string('direccion')->nullable();
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->date('fecha_afiliacion');
            $table->string('forma_pago_afiliacion', 20)->default('contado');
            $table->decimal('monto_afiliacion', 10, 2)->default(0);
            $table->decimal('saldo_afiliacion', 10, 2)->default(0);
            $table->string('estado', 20)->default('activo');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('afiliado_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afiliado_id')->constrained('afiliados')->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->decimal('tarifa_acordada', 10, 2)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->timestamps();
            $table->unique(['afiliado_id', 'servicio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afiliado_servicio');
        Schema::dropIfExists('afiliados');
        Schema::dropIfExists('servicios');
        Schema::dropIfExists('zonas');
    }
};
