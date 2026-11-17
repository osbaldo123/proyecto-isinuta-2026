<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->string('complemento', 2)->nullable()->after('ci');
            $table->string('ci_completo', 12)->nullable()->after('complemento');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('afiliado_id')
                ->nullable()
                ->unique()
                ->after('is_system_account')
                ->constrained('afiliados')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['afiliado_id']);
            $table->dropColumn('afiliado_id');
        });

        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropColumn(['complemento', 'ci_completo']);
        });
    }
};
