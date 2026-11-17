<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_afiliacion', function (Blueprint $table): void {
            $table->string('modalidad', 50)->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('tipos_afiliacion', function (Blueprint $table): void {
            $table->dropColumn('modalidad');
        });
    }
};
