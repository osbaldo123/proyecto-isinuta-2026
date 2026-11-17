<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    use HasSpanishTimestamps;

    protected $table = 'configuracion';

    protected $fillable = ['clave', 'valor', 'tipo', 'descripcion'];

    /** Monto de cada recibo de multa trimestral (Bs 50; acumulado anual 150). */
    public const MULTA_TRIMESTRAL = 'multa_trimestral';

    /** Meses sin pagar para aplicar la multa trimestral, el día 1 del mes siguiente (3). */
    public const MESES_MULTA_TRIMESTRAL = 'meses_multa_trimestral';

    /** Meses vencidos sin pagar con los que el lote pasa a «Cortado» (12). */
    public const MESES_CORTE_SERVICIO = 'meses_corte_servicio';

    /**
     * Valores iniciales y descripción de cada clave.
     *
     * @var array<string, array{valor: string, tipo: string, descripcion: string}>
     */
    public const DEFINICIONES = [
        self::MULTA_TRIMESTRAL => [
            'valor' => '50',
            'tipo' => 'decimal',
            'descripcion' => 'Monto de cada recibo de multa trimestral (Bs). Tres tramos al año = acumulado Bs 150',
        ],
        self::MESES_MULTA_TRIMESTRAL => [
            'valor' => '3',
            'tipo' => 'integer',
            'descripcion' => 'Meses sin pagar para aplicar la multa trimestral (el día 1 del mes siguiente)',
        ],
        self::MESES_CORTE_SERVICIO => [
            'valor' => '12',
            'tipo' => 'integer',
            'descripcion' => 'Meses vencidos sin pagar para cortar el servicio',
        ],
    ];

    public static function get(string $clave, mixed $default = null): mixed
    {
        $item = static::query()->where('clave', $clave)->first();

        if (! $item) {
            return $default;
        }

        return match ($item->tipo) {
            'integer' => (int) $item->valor,
            'decimal' => (float) $item->valor,
            default => $item->valor,
        };
    }

    public static function set(string $clave, mixed $valor, string $tipo, ?string $descripcion = null): self
    {
        return static::query()->updateOrCreate(
            ['clave' => $clave],
            [
                'valor' => (string) $valor,
                'tipo' => $tipo,
                'descripcion' => $descripcion,
            ],
        );
    }
}
