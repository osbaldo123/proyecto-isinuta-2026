<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('afiliados', 'foto_perfil')) {
            Schema::table('afiliados', function (Blueprint $table) {
                $table->dropColumn('foto_perfil');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('afiliados', 'foto_perfil')) {
            Schema::table('afiliados', function (Blueprint $table) {
                $table->string('foto_perfil')->nullable()->after('email');
            });
        }
    }
};
