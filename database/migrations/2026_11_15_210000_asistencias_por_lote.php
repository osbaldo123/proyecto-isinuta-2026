<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La lista de trabajos se marca por lote: un afiliado con 3 lotes puede estar
 * presente en uno y con falta (multa) en los otros dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asistencias')) {
            return;
        }

        $indexNames = collect(Schema::getIndexes('asistencias'))->pluck('name');
        $tieneUniqueTrabajoLote = $indexNames->contains('asistencias_trabajo_id_lote_afiliado_id_unique');
        $tieneFkLote = collect(Schema::getForeignKeys('asistencias'))
            ->contains(fn (array $fk): bool => in_array('lote_afiliado_id', $fk['columns'], true));
        $tieneUniqueAfiliado = $indexNames->contains('asistencias_trabajo_id_afiliado_id_unique');

        if (
            $tieneUniqueTrabajoLote
            && $tieneFkLote
            && Schema::hasColumn('asistencias', 'lote_afiliado_id')
            && DB::table('asistencias')->whereNull('lote_afiliado_id')->count() === 0
        ) {
            return;
        }

        if (! Schema::hasColumn('asistencias', 'lote_afiliado_id')) {
            Schema::table('asistencias', function (Blueprint $table): void {
                $table->unsignedBigInteger('lote_afiliado_id')->nullable()->after('afiliado_id');
            });
        }

        // El FK de trabajo_id puede apoyarse en el unique (trabajo_id, afiliado_id).
        // Crear índice propio antes de soltar ese unique y poder duplicar filas por lote.
        if ($tieneUniqueAfiliado) {
            $indexes = collect(Schema::getIndexes('asistencias'));
            $tieneIndiceTrabajoSolo = $indexes->contains(
                fn (array $idx): bool => $idx['columns'] === ['trabajo_id']
            );

            if (! $tieneIndiceTrabajoSolo) {
                Schema::table('asistencias', function (Blueprint $table): void {
                    $table->index('trabajo_id');
                });
            }

            Schema::table('asistencias', function (Blueprint $table): void {
                $table->dropUnique(['trabajo_id', 'afiliado_id']);
            });
        }

        $filas = DB::table('asistencias as a')
            ->join('trabajos as t', 't.id', '=', 'a.trabajo_id')
            ->select(
                'a.id',
                'a.trabajo_id',
                'a.afiliado_id',
                'a.lote_afiliado_id',
                'a.presente',
                'a.fecha_creacion',
                'a.fecha_actualizacion',
                't.otb_id',
            )
            ->get();

        $yaAsignados = DB::table('asistencias')
            ->whereNotNull('lote_afiliado_id')
            ->get(['trabajo_id', 'lote_afiliado_id'])
            ->mapWithKeys(fn ($row): array => [$row->trabajo_id.'-'.$row->lote_afiliado_id => true]);

        foreach ($filas as $asistencia) {
            if ($asistencia->lote_afiliado_id !== null) {
                continue;
            }

            $lotes = DB::table('lote_afiliados')
                ->where('afiliado_id', $asistencia->afiliado_id)
                ->where('otb_id', $asistencia->otb_id)
                ->where('estado', '!=', 'baja')
                ->orderBy('id')
                ->pluck('id');

            if ($lotes->isEmpty()) {
                DB::table('asistencias')->where('id', $asistencia->id)->delete();

                continue;
            }

            $primero = true;
            foreach ($lotes as $loteId) {
                $clave = $asistencia->trabajo_id.'-'.$loteId;
                if ($yaAsignados->has($clave)) {
                    if ($primero) {
                        DB::table('asistencias')->where('id', $asistencia->id)->delete();
                        $primero = false;
                    }

                    continue;
                }

                if ($primero) {
                    DB::table('asistencias')->where('id', $asistencia->id)->update([
                        'lote_afiliado_id' => $loteId,
                    ]);
                    $yaAsignados[$clave] = true;
                    $primero = false;

                    continue;
                }

                DB::table('asistencias')->insert([
                    'trabajo_id' => $asistencia->trabajo_id,
                    'afiliado_id' => $asistencia->afiliado_id,
                    'lote_afiliado_id' => $loteId,
                    'presente' => $asistencia->presente,
                    'fecha_creacion' => $asistencia->fecha_creacion,
                    'fecha_actualizacion' => $asistencia->fecha_actualizacion,
                ]);
                $yaAsignados[$clave] = true;
            }
        }

        DB::table('asistencias')->whereNull('lote_afiliado_id')->delete();

        $indexNames = collect(Schema::getIndexes('asistencias'))->pluck('name');
        if (! $indexNames->contains('asistencias_trabajo_id_lote_afiliado_id_unique')) {
            Schema::table('asistencias', function (Blueprint $table): void {
                $table->unique(['trabajo_id', 'lote_afiliado_id']);
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE asistencias MODIFY lote_afiliado_id BIGINT UNSIGNED NOT NULL');
        } else {
            // SQLite (tests): recrear columna NOT NULL vía change si hay datos.
            Schema::table('asistencias', function (Blueprint $table): void {
                $table->unsignedBigInteger('lote_afiliado_id')->nullable(false)->change();
            });
        }

        $tieneFkLote = collect(Schema::getForeignKeys('asistencias'))
            ->contains(fn (array $fk): bool => in_array('lote_afiliado_id', $fk['columns'], true));

        if (! $tieneFkLote) {
            Schema::table('asistencias', function (Blueprint $table): void {
                $table->foreign('lote_afiliado_id')
                    ->references('id')
                    ->on('lote_afiliados')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('asistencias', 'lote_afiliado_id')) {
            return;
        }

        $ids = DB::table('asistencias')
            ->selectRaw('MIN(id) as id')
            ->groupBy('trabajo_id', 'afiliado_id')
            ->pluck('id');

        DB::table('asistencias')->whereNotIn('id', $ids)->delete();

        Schema::table('asistencias', function (Blueprint $table): void {
            $table->dropUnique(['trabajo_id', 'lote_afiliado_id']);
            $table->dropForeign(['lote_afiliado_id']);
            $table->dropColumn('lote_afiliado_id');
            $table->unique(['trabajo_id', 'afiliado_id']);
        });
    }
};
