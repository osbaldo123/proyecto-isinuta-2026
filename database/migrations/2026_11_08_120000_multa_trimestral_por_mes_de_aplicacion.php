<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nueva regla de la multa trimestral: Bs 50 el día 1 del mes siguiente a 3 meses sin pagar.
 * El mes en que se aplica se cobra normal y no entra en la cuenta, así que un año sin pagar
 * acumula Bs 50 (1 de abril), Bs 100 (1 de agosto) y Bs 150 (1 de diciembre). Ya no hay una
 * multa anual aparte.
 *
 * - multas.periodo: mes (Y-m) en que se aplica cada multa por mora. Antes había una sola multa
 *   trimestral por gestión; ahora puede haber varias en el mismo año, y el mes las identifica.
 * - Configuración: se quita el monto de la multa anual. Los meses con los que el lote pasa a
 *   «Cortado» se guardan con su propio nombre (antes eran los «meses de la multa anual»).
 *
 * Las multas ya registradas no cambian de monto ni de estado. El sistema las ubica en la nueva
 * regla la primera vez que vuelve a revisar cada lote (CalculadorMultasService).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('multas', 'periodo')) {
            Schema::table('multas', function (Blueprint $table): void {
                $table->char('periodo', 7)->nullable()->after('gestion');
            });
        }

        // Primero el índice nuevo y después se quita el anterior: en MySQL la clave foránea
        // de lote_afiliado_id siempre necesita un índice que empiece por esa columna.
        if (! $this->tieneIndice('multas_lote_motivo_periodo_unique')) {
            Schema::table('multas', function (Blueprint $table): void {
                $table->unique(['lote_afiliado_id', 'motivo', 'periodo'], 'multas_lote_motivo_periodo_unique');
            });
        }

        if ($this->tieneIndice('multas_lote_motivo_gestion_unique')) {
            Schema::table('multas', function (Blueprint $table): void {
                $table->dropUnique('multas_lote_motivo_gestion_unique');
            });
        }

        DB::table('configuracion')->where('clave', 'multa_anual')->delete();

        $corte = DB::table('configuracion')->where('clave', 'meses_multa_anual')->first();
        if ($corte !== null) {
            if (DB::table('configuracion')->where('clave', 'meses_corte_servicio')->exists()) {
                DB::table('configuracion')->where('id', $corte->id)->delete();
            } else {
                DB::table('configuracion')->where('id', $corte->id)->update([
                    'clave' => 'meses_corte_servicio',
                    'descripcion' => 'Meses vencidos sin pagar para cortar el servicio',
                ]);
            }
        }

        DB::table('configuracion')->where('clave', 'multa_trimestral')->update([
            'descripcion' => 'Monto de la multa trimestral (Bs)',
        ]);
        DB::table('configuracion')->where('clave', 'meses_multa_trimestral')->update([
            'descripcion' => 'Meses sin pagar para aplicar la multa trimestral (el día 1 del mes siguiente)',
        ]);
    }

    public function down(): void
    {
        DB::table('configuracion')->where('clave', 'meses_corte_servicio')->update([
            'clave' => 'meses_multa_anual',
            'descripcion' => 'Meses de mora para aplicar multa anual',
        ]);

        if (! DB::table('configuracion')->where('clave', 'multa_anual')->exists()) {
            DB::table('configuracion')->insert([
                'clave' => 'multa_anual',
                'valor' => '150',
                'tipo' => 'decimal',
                'descripcion' => 'Monto de la multa por mora anual (Bs)',
            ]);
        }

        // El índice anterior solo admite una multa por lote, motivo y gestión.
        $repetidas = DB::table('multas')
            ->select('lote_afiliado_id', 'motivo', 'gestion')
            ->whereNotNull('lote_afiliado_id')
            ->whereNotNull('gestion')
            ->groupBy('lote_afiliado_id', 'motivo', 'gestion')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        Schema::table('multas', function (Blueprint $table) use ($repetidas): void {
            if ($repetidas) {
                if (! $this->tieneIndice('multas_lote_afiliado_id_index')) {
                    $table->index('lote_afiliado_id', 'multas_lote_afiliado_id_index');
                }
            } elseif (! $this->tieneIndice('multas_lote_motivo_gestion_unique')) {
                $table->unique(['lote_afiliado_id', 'motivo', 'gestion'], 'multas_lote_motivo_gestion_unique');
            }
        });

        if ($this->tieneIndice('multas_lote_motivo_periodo_unique')) {
            Schema::table('multas', function (Blueprint $table): void {
                $table->dropUnique('multas_lote_motivo_periodo_unique');
            });
        }

        if (Schema::hasColumn('multas', 'periodo')) {
            Schema::table('multas', function (Blueprint $table): void {
                $table->dropColumn('periodo');
            });
        }
    }

    private function tieneIndice(string $nombre): bool
    {
        return collect(Schema::getIndexes('multas'))
            ->contains(fn (array $indice): bool => ($indice['name'] ?? '') === $nombre);
    }
};
