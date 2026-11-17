<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->string('tipo_recibo', 20)->default('ingreso')->after('numero_recibo');
            $table->string('proposito', 120)->nullable()->after('tipo_recibo');
            $table->string('proposito_label', 180)->nullable()->after('proposito');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->dropColumn(['tipo_recibo', 'proposito', 'proposito_label']);
        });
    }
};
