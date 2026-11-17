<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renombra timestamps de afiliados a español (como se ve en HeidiSQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('afiliados')) {
            return;
        }

        Schema::table('afiliados', function (Blueprint $table): void {
            if (Schema::hasColumn('afiliados', 'created_at') && ! Schema::hasColumn('afiliados', 'fecha_creacion')) {
                $table->renameColumn('created_at', 'fecha_creacion');
            }

            if (Schema::hasColumn('afiliados', 'updated_at') && ! Schema::hasColumn('afiliados', 'fecha_actualizacion')) {
                $table->renameColumn('updated_at', 'fecha_actualizacion');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('afiliados')) {
            return;
        }

        Schema::table('afiliados', function (Blueprint $table): void {
            if (Schema::hasColumn('afiliados', 'fecha_creacion') && ! Schema::hasColumn('afiliados', 'created_at')) {
                $table->renameColumn('fecha_creacion', 'created_at');
            }

            if (Schema::hasColumn('afiliados', 'fecha_actualizacion') && ! Schema::hasColumn('afiliados', 'updated_at')) {
                $table->renameColumn('fecha_actualizacion', 'updated_at');
            }
        });
    }
};
