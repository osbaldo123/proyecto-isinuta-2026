<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categorias')) {
            $industrialId = DB::table('categorias')->where('slug', 'industrial')->value('id');
            $domiciliarId = DB::table('categorias')
                ->whereIn('slug', ['domiciliaria', 'domiciliar'])
                ->orderBy('id')
                ->value('id');

            if ($industrialId) {
                if ($domiciliarId && Schema::hasTable('lote_afiliados') && Schema::hasColumn('lote_afiliados', 'categoria_id')) {
                    DB::table('lote_afiliados')
                        ->where('categoria_id', $industrialId)
                        ->update(['categoria_id' => $domiciliarId]);
                }

                if (Schema::hasTable('servicio_categoria_tarifas')) {
                    DB::table('servicio_categoria_tarifas')
                        ->where('categoria_id', $industrialId)
                        ->delete();
                }

                DB::table('categorias')->where('id', $industrialId)->delete();
            }
        }

        if (Schema::hasTable('permissions')) {
            $permisoId = DB::table('permissions')->where('nombre', 'reportes.recargos')->value('id');

            if ($permisoId) {
                if (Schema::hasTable('permission_role')) {
                    DB::table('permission_role')->where('permission_id', $permisoId)->delete();
                }

                DB::table('permissions')->where('id', $permisoId)->delete();
            }
        }
    }

    public function down(): void
    {
        // No se restaura: Industrial y reportes.recargos eran restos de prueba.
    }
};
