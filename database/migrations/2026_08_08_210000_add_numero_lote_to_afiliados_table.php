<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->string('numero_lote', 30)->default('1')->after('direccion');
        });

        DB::table('afiliados')->where(function ($query): void {
            $query->whereNull('numero_lote')->orWhere('numero_lote', '');
        })->update(['numero_lote' => '1']);

        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropUnique(['ci', 'complemento']);
        });

        if ($this->hasIndex('afiliados', 'afiliados_telefono_unique')) {
            Schema::table('afiliados', function (Blueprint $table) {
                $table->dropUnique('afiliados_telefono_unique');
            });
        }

        Schema::table('afiliados', function (Blueprint $table) {
            $table->unique(
                ['ci', 'complemento', 'zona_id', 'numero_lote'],
                'afiliados_ci_zona_lote_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropUnique('afiliados_ci_zona_lote_unique');
        });

        Schema::table('afiliados', function (Blueprint $table) {
            $table->unique(['ci', 'complemento']);
        });

        if (! $this->hasIndex('afiliados', 'afiliados_telefono_unique')) {
            Schema::table('afiliados', function (Blueprint $table) {
                $table->unique('telefono', 'afiliados_telefono_unique');
            });
        }

        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropColumn('numero_lote');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
