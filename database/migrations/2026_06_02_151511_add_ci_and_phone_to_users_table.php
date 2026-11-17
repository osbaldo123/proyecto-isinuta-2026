<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ci', 8)->nullable()->unique()->after('email');
            $table->string('complemento', 2)->nullable()->after('ci');
            $table->string('ci_completo', 12)->nullable()->after('complemento');
            $table->string('telefono_codigo', 6)->default('+591')->after('ci_completo');
            $table->string('telefono', 15)->nullable()->after('telefono_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ci']);
            $table->dropColumn([
                'ci',
                'complemento',
                'ci_completo',
                'telefono_codigo',
                'telefono',
            ]);
        });
    }
};
