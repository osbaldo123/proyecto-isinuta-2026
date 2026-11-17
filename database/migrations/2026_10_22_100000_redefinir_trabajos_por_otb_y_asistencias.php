<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El trabajo deja de ser una instalación o reparación.
 * Pasa a ser el registro de un trabajo de una OTB, con lista por afiliado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trabajos') && Schema::hasColumn('trabajos', 'afiliado_id')) {
            DB::table('trabajos')->delete();

            foreach (['afiliado_id', 'lote_afiliado_id', 'servicio_id', 'asignado_a'] as $columna) {
                if (! Schema::hasColumn('trabajos', $columna)) {
                    continue;
                }

                Schema::table('trabajos', function (Blueprint $table) use ($columna): void {
                    $table->dropConstrainedForeignId($columna);
                });
            }

            foreach (Schema::getIndexes('trabajos') as $index) {
                if (($index['primary'] ?? false) === true) {
                    continue;
                }

                $columnas = $index['columns'] ?? [];
                if (! in_array('estado', $columnas, true) && ! in_array('fecha_solicitud', $columnas, true)) {
                    continue;
                }

                Schema::table('trabajos', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index['name']);
                });
            }

            $sobran = array_values(array_filter(
                ['estado', 'fecha_solicitud', 'fecha_programada', 'fecha_conclusion', 'forma_pago_instalacion', 'costo_acordado', 'observaciones'],
                fn (string $columna): bool => Schema::hasColumn('trabajos', $columna),
            ));

            if ($sobran !== []) {
                Schema::table('trabajos', function (Blueprint $table) use ($sobran): void {
                    $table->dropColumn($sobran);
                });
            }

            Schema::table('trabajos', function (Blueprint $table): void {
                $table->foreignId('zona_id')->constrained('zonas')->restrictOnDelete();
                $table->string('tipo', 100);
                $table->string('descripcion');
                $table->date('fecha_inicio');
                $table->decimal('monto_multa', 10, 2);
                $table->index(['zona_id', 'fecha_inicio']);
            });
        }

        if (! Schema::hasTable('asistencias')) {
            Schema::create('asistencias', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('trabajo_id')->constrained('trabajos')->cascadeOnDelete();
                $table->foreignId('afiliado_id')->constrained('afiliados')->cascadeOnDelete();
                $table->boolean('presente')->default(false);
                $table->timestamp('fecha_creacion')->nullable();
                $table->timestamp('fecha_actualizacion')->nullable();

                $table->unique(['trabajo_id', 'afiliado_id']);
            });
        }

        if (Schema::hasTable('multas') && ! Schema::hasColumn('multas', 'trabajo_id')) {
            Schema::table('multas', function (Blueprint $table): void {
                $table->foreignId('trabajo_id')
                    ->nullable()
                    ->constrained('trabajos')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('multas', 'trabajo_id')) {
            Schema::table('multas', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('trabajo_id');
            });
        }

        Schema::dropIfExists('asistencias');

        if (! Schema::hasColumn('trabajos', 'zona_id')) {
            return;
        }

        DB::table('trabajos')->delete();

        Schema::table('trabajos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('zona_id');
            $table->dropColumn(['tipo', 'descripcion', 'fecha_inicio', 'monto_multa']);
        });

        Schema::table('trabajos', function (Blueprint $table): void {
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
            $table->index(['estado', 'fecha_solicitud']);
        });
    }
};
