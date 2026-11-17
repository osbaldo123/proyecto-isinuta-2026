<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zonas') && ! Schema::hasTable('otbs')) {
            Schema::rename('zonas', 'otbs');
        }

        $this->renameForeignId('lote_afiliados', 'zona_id', 'otb_id', 'otbs', nullable: true);
        $this->renameForeignId('trabajos', 'zona_id', 'otb_id', 'otbs', nullable: false);

        if (Schema::hasTable('afiliados') && Schema::hasColumn('afiliados', 'zona_id')) {
            $this->renameForeignId('afiliados', 'zona_id', 'otb_id', 'otbs', nullable: true);
        }

        $this->renameIndexIfExists('lote_afiliados', 'lote_afiliados_zona_numero_unique', 'lote_afiliados_otb_numero_unique');
        $this->renameIndexIfExists('lote_afiliados', 'lote_afiliados_zona_id_numero_lote_index', 'lote_afiliados_otb_id_numero_lote_index');
        $this->renameIndexIfExists('trabajos', 'trabajos_zona_id_fecha_inicio_index', 'trabajos_otb_id_fecha_inicio_index');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('nombre', 'zonas.ver')->update([
                'nombre' => 'otb.ver',
                'descripcion' => 'Ver OTB',
                'grupo' => 'otb',
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('nombre', 'otb.ver')->update([
                'nombre' => 'zonas.ver',
                'descripcion' => 'Ver OTB',
                'grupo' => 'zonas',
            ]);
        }

        $this->renameIndexIfExists('trabajos', 'trabajos_otb_id_fecha_inicio_index', 'trabajos_zona_id_fecha_inicio_index');
        $this->renameIndexIfExists('lote_afiliados', 'lote_afiliados_otb_id_numero_lote_index', 'lote_afiliados_zona_id_numero_lote_index');
        $this->renameIndexIfExists('lote_afiliados', 'lote_afiliados_otb_numero_unique', 'lote_afiliados_zona_numero_unique');

        if (Schema::hasTable('afiliados') && Schema::hasColumn('afiliados', 'otb_id')) {
            $this->renameForeignId('afiliados', 'otb_id', 'zona_id', 'zonas', nullable: true);
        }

        $this->renameForeignId('trabajos', 'otb_id', 'zona_id', 'zonas', nullable: false);
        $this->renameForeignId('lote_afiliados', 'otb_id', 'zona_id', 'zonas', nullable: true);

        if (Schema::hasTable('otbs') && ! Schema::hasTable('zonas')) {
            Schema::rename('otbs', 'zonas');
        }
    }

    private function renameForeignId(string $table, string $from, string $to, string $referencedTable, bool $nullable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table($table, function (Blueprint $blueprint) use ($from, $to): void {
                $blueprint->renameColumn($from, $to);
            });

            return;
        }

        $this->dropForeignByColumn($table, $from);

        DB::statement("ALTER TABLE `{$table}` CHANGE `{$from}` `{$to}` BIGINT UNSIGNED ".($nullable ? 'NULL' : 'NOT NULL'));

        Schema::table($table, function (Blueprint $blueprint) use ($to, $referencedTable, $nullable): void {
            $foreign = $blueprint->foreign($to)->references('id')->on($referencedTable);
            if ($nullable) {
                $foreign->nullOnDelete();
            } else {
                $foreign->restrictOnDelete();
            }
        });
    }

    private function dropForeignByColumn(string $table, string $column): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $database = Schema::getConnection()->getDatabaseName();
        $rows = DB::select(
            'SELECT CONSTRAINT_NAME AS name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, $table, $column],
        );

        foreach ($rows as $row) {
            $name = $row->name ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            try {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
            } catch (\Throwable) {
                // Ignorar si ya no existe.
            }
        }
    }

    private function renameIndexIfExists(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table) || Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $database = Schema::getConnection()->getDatabaseName();
        $exists = DB::selectOne(
            'SELECT INDEX_NAME AS name
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
             LIMIT 1',
            [$database, $table, $from],
        );

        if (! $exists) {
            return;
        }

        $toExists = DB::selectOne(
            'SELECT INDEX_NAME AS name
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
             LIMIT 1',
            [$database, $table, $to],
        );

        if ($toExists) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` RENAME INDEX `{$from}` TO `{$to}`");
    }
};
