<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_afiliacion', function (Blueprint $table) {
            $table->decimal('monto_plazo', 10, 2)->default(0)->after('monto');
        });

        DB::table('tipos_afiliacion')->update(['monto_plazo' => DB::raw('monto')]);

        $plazoAgua = Schema::hasTable('configuracion')
            ? DB::table('configuracion')->where('clave', 'afiliacion_plazo')->value('valor')
            : null;

        DB::table('tipos_afiliacion')
            ->where('codigo', 'agua')
            ->update(['monto_plazo' => is_numeric($plazoAgua) ? (float) $plazoAgua : 3800]);

        // Oficial Isinuta: alcantarillado a plazo Bs 2.500 (no el mismo que al contado).
        DB::table('tipos_afiliacion')
            ->where('codigo', 'alcantarillado')
            ->update(['monto_plazo' => 2500]);
    }

    public function down(): void
    {
        Schema::table('tipos_afiliacion', function (Blueprint $table) {
            $table->dropColumn('monto_plazo');
        });
    }
};
