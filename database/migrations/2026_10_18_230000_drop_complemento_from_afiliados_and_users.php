<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['afiliados', 'users'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'complemento')) {
                continue;
            }

            if (Schema::hasColumn($table, 'ci_completo') && Schema::hasColumn($table, 'ci')) {
                DB::table($table)
                    ->whereNotNull('ci')
                    ->where(function ($query): void {
                        $query->whereNull('ci_completo')
                            ->orWhereColumn('ci_completo', '!=', 'ci');
                    })
                    ->update(['ci_completo' => DB::raw('ci')]);
            }

            foreach ($this->indexesWithComplemento($table) as $index) {
                $this->dropIndexIfExists($table, $index);
            }

            $this->dropUniqueOnColumnsIfExists($table, ['ci', 'complemento']);

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (Schema::hasColumn($table, 'complemento')) {
                    $blueprint->dropColumn('complemento');
                }
            });

            $this->ensureUniqueOnCi($table);
        }
    }

    public function down(): void
    {
        foreach (['users', 'afiliados'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->dropIndexIfExists($table, "{$table}_ci_unique");

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (! Schema::hasColumn($table, 'complemento')) {
                    $blueprint->string('complemento', 2)->nullable()->after('ci');
                }
            });

            try {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->unique(['ci', 'complemento']);
                });
            } catch (Throwable) {
                // ya existe
            }
        }
    }

    /**
     * @return list<string>
     */
    private function indexesWithComplemento(string $table): array
    {
        if ($table === 'afiliados') {
            return [
                'afiliados_ci_complemento_unique',
                'afiliados_ci_complemento_zona_lote_unique',
                'afiliados_ci_complemento_numero_lote_unique',
            ];
        }

        return ['users_ci_complemento_unique'];
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropUniqueOnColumnsIfExists(string $table, array $columns): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                $blueprint->dropUnique($columns);
            });
        } catch (Throwable) {
            // ignore
        }
    }

    private function ensureUniqueOnCi(string $table): void
    {
        $indexName = "{$table}_ci_unique";

        if ($this->hasIndex($table, $indexName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unique('ci');
            });
        } catch (Throwable) {
            // ya existe con otro nombre
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->hasIndex($table, $index)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                $blueprint->dropUnique($index);
            });
        } catch (Throwable) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                    $blueprint->dropIndex($index);
                });
            } catch (Throwable) {
                // ignore
            }
        }
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
