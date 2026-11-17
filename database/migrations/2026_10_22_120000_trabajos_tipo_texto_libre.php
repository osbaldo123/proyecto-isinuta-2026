<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El tipo de trabajo lo escribe la secretaria según la situación.
 * No es una lista fija.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('trabajos', 'tipo')) {
            return;
        }

        Schema::table('trabajos', function (Blueprint $table): void {
            $table->string('tipo', 100)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('trabajos', 'tipo')) {
            return;
        }

        Schema::table('trabajos', function (Blueprint $table): void {
            $table->string('tipo', 30)->change();
        });
    }
};
