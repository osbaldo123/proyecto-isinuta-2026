<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->date('fecha_ingreso')->nullable()->after('is_system_account');
            $table->date('fecha_final')->nullable()->after('fecha_ingreso');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['fecha_ingreso', 'fecha_final']);
        });
    }
};
