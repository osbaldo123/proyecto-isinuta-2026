<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renombra created_at / updated_at a fecha_creacion / fecha_actualizacion
 * en tablas de negocio. No toca jobs / password_reset_tokens (Laravel).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tablasConAmbos = [
        'categorias',
        'configuracion',
        'lote_afiliados',
        'lote_servicio',
        'lote_tipo_afiliacion',
        'multas',
        'pago_detalles',
        'pagos',
        'permission_role',
        'permissions',
        'recibo_sequences',
        'role_user',
        'roles',
        'servicio_categoria_tarifas',
        'servicios',
        'tipos_afiliacion',
        'trabajos',
        'users',
        'zonas',
    ];

    public function up(): void
    {
        foreach ($this->tablasConAmbos as $tabla) {
            $this->renombrarAmbos($tabla);
        }

        // Solo created_at
        if (Schema::hasTable('activity_logs')
            && Schema::hasColumn('activity_logs', 'created_at')
            && ! Schema::hasColumn('activity_logs', 'fecha_creacion')
        ) {
            Schema::table('activity_logs', function (Blueprint $table): void {
                $table->renameColumn('created_at', 'fecha_creacion');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablasConAmbos as $tabla) {
            $this->revertirAmbos($tabla);
        }

        if (Schema::hasTable('activity_logs')
            && Schema::hasColumn('activity_logs', 'fecha_creacion')
            && ! Schema::hasColumn('activity_logs', 'created_at')
        ) {
            Schema::table('activity_logs', function (Blueprint $table): void {
                $table->renameColumn('fecha_creacion', 'created_at');
            });
        }
    }

    private function renombrarAmbos(string $tabla): void
    {
        if (! Schema::hasTable($tabla)) {
            return;
        }

        Schema::table($tabla, function (Blueprint $table) use ($tabla): void {
            if (Schema::hasColumn($tabla, 'created_at') && ! Schema::hasColumn($tabla, 'fecha_creacion')) {
                $table->renameColumn('created_at', 'fecha_creacion');
            }

            if (Schema::hasColumn($tabla, 'updated_at') && ! Schema::hasColumn($tabla, 'fecha_actualizacion')) {
                $table->renameColumn('updated_at', 'fecha_actualizacion');
            }
        });
    }

    private function revertirAmbos(string $tabla): void
    {
        if (! Schema::hasTable($tabla)) {
            return;
        }

        Schema::table($tabla, function (Blueprint $table) use ($tabla): void {
            if (Schema::hasColumn($tabla, 'fecha_creacion') && ! Schema::hasColumn($tabla, 'created_at')) {
                $table->renameColumn('fecha_creacion', 'created_at');
            }

            if (Schema::hasColumn($tabla, 'fecha_actualizacion') && ! Schema::hasColumn($tabla, 'updated_at')) {
                $table->renameColumn('fecha_actualizacion', 'updated_at');
            }
        });
    }
};
