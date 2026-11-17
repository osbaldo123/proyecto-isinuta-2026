<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea lote_afiliados relacionada con afiliados (afiliado_id).
 * Cada propiedad/lote queda en esta tabla; el afiliado es el titular.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lote_afiliados')) {
            return;
        }

        Schema::create('lote_afiliados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afiliado_id')->constrained('afiliados')->cascadeOnDelete();
            $table->string('numero_lote', 30);
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->string('direccion')->nullable();
            $table->date('fecha_afiliacion')->nullable();
            $table->string('forma_pago_afiliacion', 20)->default('contado');
            $table->decimal('monto_afiliacion', 10, 2)->default(0);
            $table->decimal('saldo_afiliacion', 10, 2)->default(0);
            $table->string('estado', 20)->default('activo');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['afiliado_id', 'numero_lote'], 'lote_afiliados_afiliado_lote_unique');
            $table->index(['zona_id', 'numero_lote']);
        });

        // Migración inicial: 1 fila afiliados (propiedad actual) → 1 lote vinculado.
        // Nota: hoy aún puede haber varias filas afiliados por el mismo CI (un lote c/u).
        // Cada una genera su lote con afiliado_id = esa fila.
        $filas = DB::table('afiliados')->orderBy('id')->get();

        foreach ($filas as $fila) {
            $numeroLote = filled($fila->numero_lote ?? null) ? (string) $fila->numero_lote : '1';

            $exists = DB::table('lote_afiliados')
                ->where('afiliado_id', $fila->id)
                ->where('numero_lote', $numeroLote)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('lote_afiliados')->insert([
                'afiliado_id' => $fila->id,
                'numero_lote' => $numeroLote,
                'zona_id' => $fila->zona_id ?? null,
                'direccion' => $fila->direccion ?? null,
                'fecha_afiliacion' => $fila->fecha_afiliacion ?? now()->toDateString(),
                'forma_pago_afiliacion' => $fila->forma_pago_afiliacion ?? 'contado',
                'monto_afiliacion' => $fila->monto_afiliacion ?? 0,
                'saldo_afiliacion' => $fila->saldo_afiliacion ?? 0,
                'estado' => $fila->estado ?? 'activo',
                'observaciones' => $fila->observaciones ?? null,
                'created_at' => $fila->created_at ?? now(),
                'updated_at' => $fila->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_afiliados');
    }
};
