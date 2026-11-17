<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropUnique(['ci']);
            $table->unique(['ci', 'complemento']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ci']);
            $table->unique(['ci', 'complemento']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ci', 'complemento']);
            $table->unique('ci');
        });

        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropUnique(['ci', 'complemento']);
            $table->unique('ci');
        });
    }
};
