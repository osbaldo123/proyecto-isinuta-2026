<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quita columnas y tablas que no se usan en formularios ni menú:
 * - tipos_afiliacion.apellido (sin input en UI)
 * - lote_afiliados.cantidad_inquilinos (siempre null)
 * - alarmas / calendario_eventos (módulos sin menú, 0 datos)
 * - passkeys (Fortify sin pantalla)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tipos_afiliacion') && Schema::hasColumn('tipos_afiliacion', 'apellido')) {
            Schema::table('tipos_afiliacion', function (Blueprint $table): void {
                $table->dropColumn('apellido');
            });
        }

        if (Schema::hasTable('lote_afiliados') && Schema::hasColumn('lote_afiliados', 'cantidad_inquilinos')) {
            Schema::table('lote_afiliados', function (Blueprint $table): void {
                $table->dropColumn('cantidad_inquilinos');
            });
        }

        Schema::dropIfExists('alarmas');
        Schema::dropIfExists('calendario_eventos');
        Schema::dropIfExists('passkeys');
    }

    public function down(): void
    {
        if (Schema::hasTable('tipos_afiliacion') && ! Schema::hasColumn('tipos_afiliacion', 'apellido')) {
            Schema::table('tipos_afiliacion', function (Blueprint $table): void {
                $table->string('apellido', 120)->nullable()->after('nombre');
            });
        }

        if (Schema::hasTable('lote_afiliados') && ! Schema::hasColumn('lote_afiliados', 'cantidad_inquilinos')) {
            Schema::table('lote_afiliados', function (Blueprint $table): void {
                $table->unsignedSmallInteger('cantidad_inquilinos')->nullable()->after('categoria_propiedad');
            });
        }

        if (! Schema::hasTable('alarmas')) {
            Schema::create('alarmas', function (Blueprint $table): void {
                $table->id();
                $table->string('tipo', 100);
                $table->string('ubicacion', 150);
                $table->string('zona', 100)->nullable();
                $table->string('estado', 30)->default('Pendiente');
                $table->date('fecha')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('calendario_eventos')) {
            Schema::create('calendario_eventos', function (Blueprint $table): void {
                $table->id();
                $table->string('titulo', 150);
                $table->date('fecha');
                $table->string('tipo', 50)->nullable();
                $table->text('descripcion')->nullable();
                $table->timestamps();
            });
        }
    }
};
