<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * metadata no se usa en la UI de Historial; la acción ya describe el evento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        if (Schema::hasColumn('activity_logs', 'metadata')) {
            Schema::table('activity_logs', function (Blueprint $table): void {
                $table->dropColumn('metadata');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        if (! Schema::hasColumn('activity_logs', 'metadata')) {
            Schema::table('activity_logs', function (Blueprint $table): void {
                $table->json('metadata')->nullable()->after('accion');
            });
        }
    }
};
