<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lote_afiliados')
            ->where('categoria_propiedad', 'alojamiento')
            ->update(['monto_categorizado' => 17]);
    }

    public function down(): void
    {
        DB::table('lote_afiliados')
            ->where('categoria_propiedad', 'alojamiento')
            ->update(['monto_categorizado' => 80]);
    }
};
