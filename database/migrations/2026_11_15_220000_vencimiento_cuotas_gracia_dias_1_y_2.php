<?php

use App\Services\CuotaMensualService;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regla de cobro mensual:
 * - El mes de servicio (ej. enero) se empieza a cobrar desde el 1 del mes siguiente.
 * - Días 1 y 2: aún se puede pagar sin estar vencido.
 * - Desde el día 3: si no canceló, la cuota queda vencida (deuda de ese mes).
 *
 * fecha_vencimiento = día 2 del mes siguiente al periodo (vencida cuando hoy > ese día).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuotas')) {
            return;
        }

        DB::table('cuotas')
            ->orderBy('id')
            ->chunkById(200, function ($cuotas): void {
                foreach ($cuotas as $cuota) {
                    if (! is_string($cuota->periodo) || ! preg_match('/^\d{4}-\d{2}$/', $cuota->periodo)) {
                        continue;
                    }

                    $vence = CuotaMensualService::fechaVencimientoDelPeriodo($cuota->periodo);

                    if ($cuota->fecha_vencimiento === $vence) {
                        continue;
                    }

                    DB::table('cuotas')->where('id', $cuota->id)->update([
                        'fecha_vencimiento' => $vence,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cuotas')) {
            return;
        }

        DB::table('cuotas')
            ->orderBy('id')
            ->chunkById(200, function ($cuotas): void {
                foreach ($cuotas as $cuota) {
                    if (! is_string($cuota->periodo) || ! preg_match('/^\d{4}-\d{2}$/', $cuota->periodo)) {
                        continue;
                    }

                    $vence = Carbon::createFromFormat('Y-m-d', $cuota->periodo.'-01')
                        ->addMonth()
                        ->day(15)
                        ->toDateString();

                    DB::table('cuotas')->where('id', $cuota->id)->update([
                        'fecha_vencimiento' => $vence,
                    ]);
                }
            });
    }
};
