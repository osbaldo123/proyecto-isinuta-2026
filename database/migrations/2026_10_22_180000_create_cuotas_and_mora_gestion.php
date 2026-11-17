<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuotas')) {
            Schema::create('cuotas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('lote_afiliado_id')->constrained('lote_afiliados')->cascadeOnDelete();
                $table->foreignId('servicio_id')->constrained('servicios')->restrictOnDelete();
                $table->char('periodo', 7);
                $table->unsignedSmallInteger('gestion');
                $table->decimal('monto_original', 10, 2);
                $table->decimal('saldo', 10, 2);
                $table->date('fecha_vencimiento');
                $table->string('estado', 20)->default('pendiente');
                $table->timestamp('fecha_creacion')->nullable();
                $table->timestamp('fecha_actualizacion')->nullable();

                $table->unique(['lote_afiliado_id', 'servicio_id', 'periodo'], 'cuotas_lote_servicio_periodo_unique');
                $table->index(['lote_afiliado_id', 'fecha_vencimiento']);
            });
        }

        Schema::table('pago_detalles', function (Blueprint $table): void {
            if (! Schema::hasColumn('pago_detalles', 'cuota_id')) {
                $table->foreignId('cuota_id')
                    ->nullable()
                    ->after('multa_id')
                    ->constrained('cuotas')
                    ->nullOnDelete();
            }
        });

        Schema::table('multas', function (Blueprint $table): void {
            if (! Schema::hasColumn('multas', 'gestion')) {
                $table->unsignedSmallInteger('gestion')->nullable()->after('motivo');
            }
            if (! Schema::hasColumn('multas', 'cuotas_vencidas')) {
                $table->unsignedSmallInteger('cuotas_vencidas')->nullable()->after('gestion');
            }
            if (! Schema::hasColumn('multas', 'saldo')) {
                $table->decimal('saldo', 10, 2)->nullable()->after('monto');
            }
        });

        if (Schema::hasColumn('multas', 'gestion')) {
            $existe = collect(Schema::getIndexes('multas'))
                ->contains(fn (array $index): bool => ($index['name'] ?? '') === 'multas_lote_motivo_gestion_unique');

            if (! $existe) {
                Schema::table('multas', function (Blueprint $table): void {
                    $table->unique(['lote_afiliado_id', 'motivo', 'gestion'], 'multas_lote_motivo_gestion_unique');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('multas', 'gestion')) {
            Schema::table('multas', function (Blueprint $table): void {
                $table->dropUnique('multas_lote_motivo_gestion_unique');
                $table->dropColumn(['gestion', 'cuotas_vencidas', 'saldo']);
            });
        }

        if (Schema::hasColumn('pago_detalles', 'cuota_id')) {
            Schema::table('pago_detalles', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('cuota_id');
            });
        }

        Schema::dropIfExists('cuotas');
    }
};
