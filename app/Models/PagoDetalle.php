<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoDetalle extends Model
{
    use HasSpanishTimestamps;

    protected $fillable = [
        'pago_id',
        'concepto',
        'descripcion',
        'monto',
        'servicio_id',
        'multa_id',
        'cuota_id',
        'periodo',
    ];

    public const CONCEPTO_SERVICIO_MENSUAL = 'servicio_mensual';

    public const CONCEPTO_CONSUMO_AGUA = 'consumo_agua';

    public const CONCEPTO_PAGO_ALCANTARILLADO = 'pago_alcantarillado';

    public const CONCEPTO_PAGO_TRABAJO = 'pago_trabajo';

    public const CONCEPTO_PAGO_TRABAJO_ALCANTARILLADO = 'pago_trabajo_alcantarillado';

    public const CONCEPTO_PAGO_TRABAJO_AGUA = 'pago_trabajo_agua';

    public const CONCEPTO_REUNIONES_ALCANTARILLADO = 'reuniones_alcantarillado';

    public const CONCEPTO_REUNIONES_AGUA = 'reuniones_agua';

    public const CONCEPTO_MULTA = 'multa';

    public const CONCEPTO_AFILIACION = 'afiliacion';

    public const CONCEPTO_AFILIACION_AGUA = 'afiliacion_agua';

    public const CONCEPTO_AFILIACION_ALCANTARILLADO = 'afiliacion_alcantarillado';

    public const CONCEPTO_APORTE = 'aporte';

    public const CONCEPTO_OTRO = 'otro';

    public const MONTO_AFILIACION_AGUA = 3500.0;

    public const MONTO_AFILIACION_AGUA_PLAZO = 3800.0;

    public const MONTO_AFILIACION_ALCANTARILLADO = 2000.0;

    public const MONTO_AFILIACION_ALCANTARILLADO_PLAZO = 2500.0;

    /**
     * Conceptos de mensualidad (agua / alcantarillado).
     *
     * @return array<string, string>
     */
    public static function labelsCuota(): array
    {
        return [
            self::CONCEPTO_CONSUMO_AGUA => 'Consumo de agua',
            self::CONCEPTO_PAGO_ALCANTARILLADO => 'Pago de alcantarillado',
        ];
    }

    /**
     * Multas de mora/manuales y cobros por falta de trabajo o reunión.
     * Se agrupan en el filtro de recibos bajo «Multas».
     *
     * @return list<string>
     */
    public static function conceptosMultaOFalta(): array
    {
        return [
            self::CONCEPTO_MULTA,
            self::CONCEPTO_PAGO_TRABAJO,
            self::CONCEPTO_PAGO_TRABAJO_AGUA,
            self::CONCEPTO_PAGO_TRABAJO_ALCANTARILLADO,
            self::CONCEPTO_REUNIONES_AGUA,
            self::CONCEPTO_REUNIONES_ALCANTARILLADO,
        ];
    }

    /**
     * @return list<string>
     */
    public static function conceptosCuota(): array
    {
        return array_keys(self::labelsCuota());
    }

    /**
     * @return list<string>
     */
    public static function conceptosPermitidos(): array
    {
        $desdeCatalogo = TipoAfiliacion::query()
            ->pluck('codigo')
            ->map(fn (string $codigo): string => 'afiliacion_'.$codigo)
            ->all();

        return array_values(array_unique([
            ...self::conceptosCuota(),
            ...self::conceptosMultaOFalta(),
            self::CONCEPTO_SERVICIO_MENSUAL,
            self::CONCEPTO_AFILIACION,
            self::CONCEPTO_AFILIACION_AGUA,
            self::CONCEPTO_AFILIACION_ALCANTARILLADO,
            ...$desdeCatalogo,
            self::CONCEPTO_APORTE,
            self::CONCEPTO_OTRO,
        ]));
    }

    /**
     * @return list<string>
     */
    public static function conceptosAfiliacion(): array
    {
        return [
            self::CONCEPTO_AFILIACION,
            self::CONCEPTO_AFILIACION_AGUA,
            self::CONCEPTO_AFILIACION_ALCANTARILLADO,
        ];
    }

    public static function esConceptoAfiliacion(string $concepto): bool
    {
        if (in_array($concepto, self::conceptosAfiliacion(), true)) {
            return true;
        }

        return TipoAfiliacion::codigoDesdeConcepto($concepto) !== null;
    }

    public static function montoAfiliacionPorConcepto(string $concepto): ?float
    {
        $desdeCatalogo = TipoAfiliacion::montoPorConcepto($concepto);
        if ($desdeCatalogo !== null) {
            return $desdeCatalogo;
        }

        return match ($concepto) {
            self::CONCEPTO_AFILIACION_AGUA => self::MONTO_AFILIACION_AGUA,
            self::CONCEPTO_AFILIACION_ALCANTARILLADO => self::MONTO_AFILIACION_ALCANTARILLADO,
            default => null,
        };
    }

    /**
     * Cuotas de servicio mensual (histórico + agua/alcantarillado).
     *
     * @return list<string>
     */
    public static function conceptosServicioMensual(): array
    {
        return [
            self::CONCEPTO_SERVICIO_MENSUAL,
            self::CONCEPTO_CONSUMO_AGUA,
            self::CONCEPTO_PAGO_ALCANTARILLADO,
        ];
    }

    public static function requierePeriodo(string $concepto): bool
    {
        return in_array($concepto, [...self::conceptosCuota(), self::CONCEPTO_SERVICIO_MENSUAL], true);
    }

    public static function label(string $concepto): string
    {
        $desdeCatalogo = TipoAfiliacion::labelPorConcepto($concepto);
        if ($desdeCatalogo) {
            return $desdeCatalogo;
        }

        return self::labelsCuota()[$concepto]
            ?? match ($concepto) {
                self::CONCEPTO_SERVICIO_MENSUAL => 'Consumo de agua',
                self::CONCEPTO_MULTA => 'Multa / recargo',
                self::CONCEPTO_PAGO_TRABAJO => 'Falta de trabajos',
                self::CONCEPTO_PAGO_TRABAJO_ALCANTARILLADO => 'Falta de trabajos de alcantarillado',
                self::CONCEPTO_PAGO_TRABAJO_AGUA => 'Falta de trabajos de agua potable',
                self::CONCEPTO_REUNIONES_ALCANTARILLADO => 'Falta de reuniones de alcantarillado',
                self::CONCEPTO_REUNIONES_AGUA => 'Falta de reuniones de agua potable',
                self::CONCEPTO_AFILIACION => 'Afiliación',
                self::CONCEPTO_AFILIACION_AGUA => 'Afiliación de servicio de agua potable',
                self::CONCEPTO_AFILIACION_ALCANTARILLADO => 'Afiliación de servicio de alcantarillado',
                self::CONCEPTO_APORTE => 'Aporte',
                self::CONCEPTO_OTRO => 'Otro',
                default => ucfirst(str_replace('_', ' ', $concepto)),
            };
    }

    /**
     * @return array{key: string, label: string, tipo: string}|null
     */
    public static function clasificacionCuota(string $concepto): ?array
    {
        return match ($concepto) {
            self::CONCEPTO_CONSUMO_AGUA => [
                'key' => 'servicio_agua_potable',
                'label' => 'Consumo de agua',
                'tipo' => 'ingreso',
            ],
            self::CONCEPTO_PAGO_ALCANTARILLADO => [
                'key' => 'servicio_alcantarillado',
                'label' => 'Pago de alcantarillado',
                'tipo' => 'ingreso',
            ],
            self::CONCEPTO_PAGO_TRABAJO => [
                'key' => 'pago_trabajo',
                'label' => 'Falta de trabajos',
                'tipo' => 'caja',
            ],
            self::CONCEPTO_PAGO_TRABAJO_ALCANTARILLADO => [
                'key' => 'pago_trabajo_alcantarillado',
                'label' => 'Falta de trabajos de alcantarillado',
                'tipo' => 'caja',
            ],
            self::CONCEPTO_PAGO_TRABAJO_AGUA => [
                'key' => 'pago_trabajo_agua',
                'label' => 'Falta de trabajos de agua potable',
                'tipo' => 'caja',
            ],
            self::CONCEPTO_REUNIONES_ALCANTARILLADO => [
                'key' => 'reuniones_alcantarillado',
                'label' => 'Falta de reuniones de alcantarillado',
                'tipo' => 'caja',
            ],
            self::CONCEPTO_REUNIONES_AGUA => [
                'key' => 'reuniones_agua',
                'label' => 'Falta de reuniones de agua potable',
                'tipo' => 'caja',
            ],
            default => null,
        };
    }

    public static function conceptoDesdeServicio(?Servicio $servicio): string
    {
        if ($servicio?->esAlcantarillado()) {
            return self::CONCEPTO_PAGO_ALCANTARILLADO;
        }

        return self::CONCEPTO_CONSUMO_AGUA;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Pago, $this>
     */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    /**
     * @return BelongsTo<Servicio, $this>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /**
     * @return BelongsTo<Multa, $this>
     */
    public function multa(): BelongsTo
    {
        return $this->belongsTo(Multa::class);
    }
}
