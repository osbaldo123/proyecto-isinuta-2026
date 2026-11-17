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
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'ci_completo')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (Schema::hasColumn($table, 'ci_completo')) {
                    $blueprint->dropColumn('ci_completo');
                }
            });
        }

        if (! Schema::hasTable('afiliados') || ! Schema::hasColumn('afiliados', 'email')) {
            return;
        }

        $this->dropIndexIfExists('afiliados', 'afiliados_email_unique');
        $this->dropUniqueOnColumnsIfExists('afiliados', ['email']);

        Schema::table('afiliados', function (Blueprint $blueprint): void {
            if (Schema::hasColumn('afiliados', 'email')) {
                $blueprint->dropColumn('email');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('afiliados') && ! Schema::hasColumn('afiliados', 'email')) {
            Schema::table('afiliados', function (Blueprint $blueprint): void {
                $blueprint->string('email')->nullable()->unique()->after('telefono');
            });
        }

        foreach (['users', 'afiliados'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'ci_completo')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $after = Schema::hasColumn($table, 'ci') ? 'ci' : null;

                if ($after !== null) {
                    $blueprint->string('ci_completo', 12)->nullable()->after($after);
                } else {
                    $blueprint->string('ci_completo', 12)->nullable();
                }
            });
        }
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
