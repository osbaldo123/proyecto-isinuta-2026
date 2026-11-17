<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table): void {
            $table->decimal('costo_instalacion_contado', 10, 2)->default(0)->after('tarifa_mensual');
            $table->decimal('costo_instalacion_plazos', 10, 2)->default(0)->after('costo_instalacion_contado');
        });

        Schema::create('trabajos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('afiliado_id')->constrained('afiliados')->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios')->restrictOnDelete();
            $table->string('estado', 30)->default('pendiente');
            $table->date('fecha_solicitud');
            $table->date('fecha_programada')->nullable();
            $table->date('fecha_conclusion')->nullable();
            $table->string('forma_pago_instalacion', 20)->nullable();
            $table->decimal('costo_acordado', 10, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'fecha_solicitud']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajos');

        Schema::table('servicios', function (Blueprint $table): void {
            $table->dropColumn(['costo_instalacion_contado', 'costo_instalacion_plazos']);
        });
    }
};
