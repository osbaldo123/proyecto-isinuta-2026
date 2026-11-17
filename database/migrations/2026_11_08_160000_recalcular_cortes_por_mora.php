<?php

use App\Services\CalculadorMultasService;
use Illuminate\Database\Migrations\Migration;

/**
 * Revisión de la mora de todo el padrón con las reglas actuales:
 *
 * - Corte: un lote queda Cortado solo si tiene 12 meses vencidos sin pagar (Configuración › corte del
 *   servicio). Había lotes cortados con 1 a 3 meses vencidos que venían de datos o configuraciones
 *   anteriores; como seguían debiendo algo, el sistema no los reactivaba solo. Los que sí llegan a
 *   12 meses siguen cortados.
 * - Multa trimestral: se aplican las que faltan (Bs 50 el día 1 del mes siguiente a 3 meses sin
 *   pagar) y las de la regla anterior que siguen sin pagar se recalculan con la regla nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        $calculador = app(CalculadorMultasService::class);

        $calculador->recalcularCortes();
        $calculador->aplicarMultasPendientes();
    }

    public function down(): void
    {
        // Corrección de datos: no se revierte.
    }
};
