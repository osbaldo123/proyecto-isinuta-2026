<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unique(['telefono_codigo', 'telefono'], 'users_telefono_unique');
        });

        Schema::table('afiliados', function (Blueprint $table): void {
            $table->unique('telefono', 'afiliados_telefono_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_telefono_unique');
        });

        Schema::table('afiliados', function (Blueprint $table): void {
            $table->dropUnique('afiliados_telefono_unique');
        });
    }
};
