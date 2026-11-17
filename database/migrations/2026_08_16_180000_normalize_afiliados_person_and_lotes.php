<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normaliza: 1 persona en afiliados + N lotes en lote_afiliados.
 * Consolida filas duplicadas por CI+complemento y quita campos de propiedad de afiliados.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->consolidarPersonasYLotes();
        $this->ajustarEsquemaAfiliados();
    }

    public function down(): void
    {
        Schema::table('afiliados', function (Blueprint $table): void {
            if (! Schema::hasColumn('afiliados', 'numero_lote')) {
                $table->string('numero_lote', 30)->default('1')->after('direccion');
            }
            if (! Schema::hasColumn('afiliados', 'zona_id')) {
                $table->foreignId('zona_id')->nullable()->after('numero_lote')->constrained('zonas')->nullOnDelete();
            }
            if (! Schema::hasColumn('afiliados', 'fecha_afiliacion')) {
                $table->date('fecha_afiliacion')->nullable()->after('zona_id');
            }
            if (! Schema::hasColumn('afiliados', 'forma_pago_afiliacion')) {
                $table->string('forma_pago_afiliacion')->nullable()->after('fecha_afiliacion');
            }
            if (! Schema::hasColumn('afiliados', 'monto_afiliacion')) {
                $table->decimal('monto_afiliacion', 10, 2)->default(0)->after('forma_pago_afiliacion');
            }
            if (! Schema::hasColumn('afiliados', 'saldo_afiliacion')) {
                $table->decimal('saldo_afiliacion', 10, 2)->default(0)->after('monto_afiliacion');
            }
            if (! Schema::hasColumn('afiliados', 'estado')) {
                $table->string('estado', 20)->default('activo')->after('saldo_afiliacion');
            }
            if (! Schema::hasColumn('afiliados', 'observaciones')) {
                $table->text('observaciones')->nullable()->after('estado');
            }
        });
    }

    private function consolidarPersonasYLotes(): void
    {
        if (! Schema::hasTable('lote_afiliados') || ! Schema::hasColumn('afiliados', 'numero_lote')) {
            return;
        }

        $grupos = DB::table('afiliados')
            ->select('ci', 'complemento')
            ->groupBy('ci', 'complemento')
            ->get();

        foreach ($grupos as $grupo) {
            $filas = DB::table('afiliados')
                ->where('ci', $grupo->ci)
                ->where(function ($q) use ($grupo): void {
                    if ($grupo->complemento === null || $grupo->complemento === '') {
                        $q->whereNull('complemento')->orWhere('complemento', '');
                    } else {
                        $q->where('complemento', $grupo->complemento);
                    }
                })
                ->orderBy('id')
                ->get();

            if ($filas->isEmpty()) {
                continue;
            }

            $canonicoId = (int) $filas->first()->id;
            $codigoCanonico = (string) $filas->first()->codigo;

            foreach ($filas as $fila) {
                $numeroLote = filled($fila->numero_lote ?? null) ? (string) $fila->numero_lote : '1';

                $loteId = DB::table('lote_afiliados')
                    ->where('afiliado_id', $canonicoId)
                    ->where('numero_lote', $numeroLote)
                    ->value('id');

                $payloadLote = [
                    'afiliado_id' => $canonicoId,
                    'numero_lote' => $numeroLote,
                    'zona_id' => $fila->zona_id,
                    'direccion' => $fila->direccion,
                    'fecha_afiliacion' => $fila->fecha_afiliacion,
                    'forma_pago_afiliacion' => $fila->forma_pago_afiliacion ?? 'contado',
                    'monto_afiliacion' => $fila->monto_afiliacion ?? 0,
                    'saldo_afiliacion' => $fila->saldo_afiliacion ?? 0,
                    'estado' => $fila->estado ?? 'activo',
                    'observaciones' => $fila->observaciones,
                    'updated_at' => now(),
                ];

                if ($loteId) {
                    DB::table('lote_afiliados')->where('id', $loteId)->update($payloadLote);
                } else {
                    $payloadLote['created_at'] = now();
                    $loteId = DB::table('lote_afiliados')->insertGetId($payloadLote);
                }

                // Lotes viejos apuntando a la fila duplicada → reasignar operaciones y borrar.
                $lotesViejos = DB::table('lote_afiliados')
                    ->where('afiliado_id', $fila->id)
                    ->when((int) $fila->id === $canonicoId, fn ($q) => $q->where('id', '!=', $loteId))
                    ->when((int) $fila->id !== $canonicoId, fn ($q) => $q)
                    ->pluck('id');

                foreach ($lotesViejos as $viejoId) {
                    if ((int) $viejoId === (int) $loteId) {
                        continue;
                    }

                    foreach (['pagos', 'multas', 'trabajos'] as $tabla) {
                        if (Schema::hasColumn($tabla, 'lote_afiliado_id')) {
                            DB::table($tabla)->where('lote_afiliado_id', $viejoId)->update(['lote_afiliado_id' => $loteId]);
                        }
                    }

                    DB::table('lote_afiliados')->where('id', $viejoId)->delete();
                }

                foreach (['pagos', 'multas', 'trabajos'] as $tabla) {
                    DB::table($tabla)->where('afiliado_id', $fila->id)->update([
                        'afiliado_id' => $canonicoId,
                    ]);
                    DB::table($tabla)
                        ->where('afiliado_id', $canonicoId)
                        ->whereNull('lote_afiliado_id')
                        ->update(['lote_afiliado_id' => $loteId]);
                }

                // Servicios: mover al canónico si no existe.
                $servicios = DB::table('afiliado_servicio')->where('afiliado_id', $fila->id)->get();
                foreach ($servicios as $servicio) {
                    $existe = DB::table('afiliado_servicio')
                        ->where('afiliado_id', $canonicoId)
                        ->where('servicio_id', $servicio->servicio_id)
                        ->exists();

                    if (! $existe) {
                        DB::table('afiliado_servicio')->insert([
                            'afiliado_id' => $canonicoId,
                            'servicio_id' => $servicio->servicio_id,
                            'tarifa_acordada' => $servicio->tarifa_acordada,
                            'fecha_inicio' => $servicio->fecha_inicio,
                            'fecha_fin' => $servicio->fecha_fin,
                            'created_at' => $servicio->created_at ?? now(),
                            'updated_at' => now(),
                        ]);
                    }

                    if ((int) $fila->id !== $canonicoId) {
                        DB::table('afiliado_servicio')->where('id', $servicio->id)->delete();
                    }
                }

                if (Schema::hasColumn('users', 'afiliado_id')) {
                    DB::table('users')->where('afiliado_id', $fila->id)->update(['afiliado_id' => $canonicoId]);
                }

                if ((int) $fila->id !== $canonicoId) {
                    DB::table('lote_afiliados')->where('afiliado_id', $fila->id)->delete();
                    DB::table('afiliados')->where('id', $fila->id)->delete();
                } else {
                    DB::table('afiliados')->where('id', $canonicoId)->update([
                        'codigo' => $codigoCanonico,
                    ]);
                }
            }
        }
    }

    private function ajustarEsquemaAfiliados(): void
    {
        // Quitar índices compuestos que usan columnas de lote.
        $this->dropIndexIfExists('afiliados', 'afiliados_ci_lote_unique');
        $this->dropIndexIfExists('afiliados', 'afiliados_codigo_lote_unique');
        $this->dropIndexIfExists('afiliados', 'afiliados_ci_complemento_zona_lote_unique');

        Schema::table('afiliados', function (Blueprint $table): void {
            if (Schema::hasColumn('afiliados', 'zona_id')) {
                $table->dropConstrainedForeignId('zona_id');
            }
        });

        Schema::table('afiliados', function (Blueprint $table): void {
            foreach (['numero_lote', 'fecha_afiliacion', 'forma_pago_afiliacion', 'monto_afiliacion', 'saldo_afiliacion', 'observaciones'] as $col) {
                if (Schema::hasColumn('afiliados', $col)) {
                    $table->dropColumn($col);
                }
            }
            // estado se mantiene en afiliados (estado general de la persona).
        });

        // Unique por persona.
        try {
            Schema::table('afiliados', function (Blueprint $table): void {
                $table->unique('codigo', 'afiliados_codigo_unique');
            });
        } catch (Throwable) {
            // ya existe
        }

        try {
            Schema::table('afiliados', function (Blueprint $table): void {
                $table->unique(['ci', 'complemento'], 'afiliados_ci_complemento_unique');
            });
        } catch (Throwable) {
            // ya existe
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
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
};
