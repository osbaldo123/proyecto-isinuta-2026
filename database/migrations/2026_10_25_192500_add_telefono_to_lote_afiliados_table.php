<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El teléfono pasa a cada lote. Los lotes de la misma persona
 * pueden repetir el número o tener uno distinto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lote_afiliados', 'telefono')) {
            Schema::table('lote_afiliados', function (Blueprint $table): void {
                $table->string('telefono', 20)->nullable()->after('direccion');
            });
        }

        $telefonos = DB::table('afiliados')->pluck('telefono', 'id');

        DB::table('lote_afiliados')
            ->whereNull('telefono')
            ->orderBy('id')
            ->get(['id', 'afiliado_id'])
            ->each(function (object $lote) use ($telefonos): void {
                $telefono = $telefonos[$lote->afiliado_id] ?? null;

                if (! filled($telefono)) {
                    return;
                }

                DB::table('lote_afiliados')->where('id', $lote->id)->update([
                    'telefono' => $telefono,
                ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('lote_afiliados', 'telefono')) {
            Schema::table('lote_afiliados', function (Blueprint $table): void {
                $table->dropColumn('telefono');
            });
        }
    }
};
