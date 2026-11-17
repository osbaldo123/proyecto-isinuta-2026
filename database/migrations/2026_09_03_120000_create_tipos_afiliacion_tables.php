<?php

use App\Models\PagoDetalle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_afiliacion', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->decimal('monto', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('lote_tipo_afiliacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lote_afiliado_id')->constrained('lote_afiliados')->cascadeOnDelete();
            $table->foreignId('tipo_afiliacion_id')->constrained('tipos_afiliacion')->cascadeOnDelete();
            $table->decimal('monto', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['lote_afiliado_id', 'tipo_afiliacion_id'], 'lote_tipo_afiliacion_unique');
        });

        $now = now();
        $aguaId = DB::table('tipos_afiliacion')->insertGetId([
            'codigo' => 'agua',
            'nombre' => 'Afiliación de servicio de agua potable',
            'descripcion' => 'Instalación / implementación del servicio de agua potable',
            'monto' => PagoDetalle::MONTO_AFILIACION_AGUA,
            'activo' => true,
            'orden' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $alcId = DB::table('tipos_afiliacion')->insertGetId([
            'codigo' => 'alcantarillado',
            'nombre' => 'Afiliación de servicio de alcantarillado',
            'descripcion' => 'Instalación / implementación del servicio de alcantarillado',
            'monto' => PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO,
            'activo' => true,
            'orden' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Migrar montos ya registrados en lotes hacia el pivote.
        DB::table('lote_afiliados')->orderBy('id')->chunkById(200, function ($lotes) use ($aguaId, $alcId, $now): void {
            $filas = [];
            foreach ($lotes as $lote) {
                $agua = (float) ($lote->monto_afiliacion_agua ?? 0);
                $alc = (float) ($lote->monto_afiliacion_alcantarillado ?? 0);

                if ($agua > 0) {
                    $filas[] = [
                        'lote_afiliado_id' => $lote->id,
                        'tipo_afiliacion_id' => $aguaId,
                        'monto' => $agua,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($alc > 0) {
                    $filas[] = [
                        'lote_afiliado_id' => $lote->id,
                        'tipo_afiliacion_id' => $alcId,
                        'monto' => $alc,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($filas !== []) {
                DB::table('lote_tipo_afiliacion')->insert($filas);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_tipo_afiliacion');
        Schema::dropIfExists('tipos_afiliacion');
    }
};
