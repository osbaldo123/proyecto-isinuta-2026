<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El servicio se contrata por lote (propiedad), no por persona.
 * Un afiliado con varios lotes puede tener agua en uno y agua+alcantarillado en otro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lote_servicio')) {
            Schema::create('lote_servicio', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('lote_afiliado_id')->constrained('lote_afiliados')->cascadeOnDelete();
                $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
                $table->decimal('tarifa_acordada', 10, 2)->nullable();
                $table->date('fecha_inicio')->nullable();
                $table->date('fecha_fin')->nullable();
                $table->timestamps();
                $table->unique(['lote_afiliado_id', 'servicio_id'], 'lote_servicio_lote_servicio_unique');
            });
        }

        if (Schema::hasTable('afiliado_servicio') && Schema::hasTable('lote_afiliados')) {
            $asignaciones = DB::table('afiliado_servicio')->get();
            $ahora = now();

            foreach ($asignaciones as $asignacion) {
                $lotes = DB::table('lote_afiliados')
                    ->where('afiliado_id', $asignacion->afiliado_id)
                    ->pluck('id');

                foreach ($lotes as $loteId) {
                    $existe = DB::table('lote_servicio')
                        ->where('lote_afiliado_id', $loteId)
                        ->where('servicio_id', $asignacion->servicio_id)
                        ->exists();

                    if ($existe) {
                        continue;
                    }

                    DB::table('lote_servicio')->insert([
                        'lote_afiliado_id' => $loteId,
                        'servicio_id' => $asignacion->servicio_id,
                        'tarifa_acordada' => $asignacion->tarifa_acordada,
                        'fecha_inicio' => $asignacion->fecha_inicio,
                        'fecha_fin' => $asignacion->fecha_fin,
                        'created_at' => $asignacion->created_at ?? $ahora,
                        'updated_at' => $asignacion->updated_at ?? $ahora,
                    ]);
                }
            }

            Schema::dropIfExists('afiliado_servicio');
        }

        $this->deduplicarNombresServicio();

        if (Schema::hasTable('servicios') && ! $this->tieneIndiceUnicoNombre()) {
            Schema::table('servicios', function (Blueprint $table): void {
                $table->unique('nombre');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('servicios') && $this->tieneIndiceUnicoNombre()) {
            Schema::table('servicios', function (Blueprint $table): void {
                $table->dropUnique(['nombre']);
            });
        }

        if (! Schema::hasTable('afiliado_servicio')) {
            Schema::create('afiliado_servicio', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('afiliado_id')->constrained('afiliados')->cascadeOnDelete();
                $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
                $table->decimal('tarifa_acordada', 10, 2)->nullable();
                $table->date('fecha_inicio')->nullable();
                $table->date('fecha_fin')->nullable();
                $table->timestamps();
                $table->unique(['afiliado_id', 'servicio_id']);
            });
        }

        if (Schema::hasTable('lote_servicio')) {
            $asignaciones = DB::table('lote_servicio')
                ->join('lote_afiliados', 'lote_afiliados.id', '=', 'lote_servicio.lote_afiliado_id')
                ->get([
                    'lote_afiliados.afiliado_id',
                    'lote_servicio.servicio_id',
                    'lote_servicio.tarifa_acordada',
                    'lote_servicio.fecha_inicio',
                    'lote_servicio.fecha_fin',
                    'lote_servicio.created_at',
                    'lote_servicio.updated_at',
                ]);

            foreach ($asignaciones as $asignacion) {
                $existe = DB::table('afiliado_servicio')
                    ->where('afiliado_id', $asignacion->afiliado_id)
                    ->where('servicio_id', $asignacion->servicio_id)
                    ->exists();

                if ($existe) {
                    continue;
                }

                DB::table('afiliado_servicio')->insert([
                    'afiliado_id' => $asignacion->afiliado_id,
                    'servicio_id' => $asignacion->servicio_id,
                    'tarifa_acordada' => $asignacion->tarifa_acordada,
                    'fecha_inicio' => $asignacion->fecha_inicio,
                    'fecha_fin' => $asignacion->fecha_fin,
                    'created_at' => $asignacion->created_at,
                    'updated_at' => $asignacion->updated_at,
                ]);
            }

            Schema::dropIfExists('lote_servicio');
        }
    }

    private function deduplicarNombresServicio(): void
    {
        if (! Schema::hasTable('servicios')) {
            return;
        }

        $servicios = DB::table('servicios')->orderBy('id')->get();
        $vistos = [];

        foreach ($servicios as $servicio) {
            $clave = mb_strtolower(trim((string) $servicio->nombre));

            if (! isset($vistos[$clave])) {
                $vistos[$clave] = $servicio->id;

                continue;
            }

            $canonicoId = $vistos[$clave];

            if (Schema::hasTable('lote_servicio')) {
                $yaAsignados = DB::table('lote_servicio')
                    ->where('servicio_id', $canonicoId)
                    ->pluck('lote_afiliado_id');

                if ($yaAsignados->isNotEmpty()) {
                    DB::table('lote_servicio')
                        ->where('servicio_id', $servicio->id)
                        ->whereIn('lote_afiliado_id', $yaAsignados)
                        ->delete();
                }

                DB::table('lote_servicio')
                    ->where('servicio_id', $servicio->id)
                    ->update(['servicio_id' => $canonicoId]);
            }

            if (Schema::hasTable('trabajos')) {
                DB::table('trabajos')
                    ->where('servicio_id', $servicio->id)
                    ->update(['servicio_id' => $canonicoId]);
            }

            if (Schema::hasTable('pago_detalles')) {
                DB::table('pago_detalles')
                    ->where('servicio_id', $servicio->id)
                    ->update(['servicio_id' => $canonicoId]);
            }

            DB::table('servicios')->where('id', $servicio->id)->delete();
        }

        if (Schema::hasTable('lote_servicio')) {
            $duplicados = DB::table('lote_servicio')
                ->select('lote_afiliado_id', 'servicio_id', DB::raw('MIN(id) as keep_id'))
                ->groupBy('lote_afiliado_id', 'servicio_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicados as $duplicado) {
                DB::table('lote_servicio')
                    ->where('lote_afiliado_id', $duplicado->lote_afiliado_id)
                    ->where('servicio_id', $duplicado->servicio_id)
                    ->where('id', '!=', $duplicado->keep_id)
                    ->delete();
            }
        }
    }

    private function tieneIndiceUnicoNombre(): bool
    {
        foreach (Schema::getIndexes('servicios') as $index) {
            $columnas = $index['columns'] ?? [];
            $unico = (bool) ($index['unique'] ?? false);

            if ($unico && $columnas === ['nombre']) {
                return true;
            }
        }

        return false;
    }
};
