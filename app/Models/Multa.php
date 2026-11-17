<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Database\Factories\MultaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Multa extends Model
{
    /** @use HasFactory<MultaFactory> */
    use HasFactory, HasSpanishTimestamps;

    protected $fillable = [
        'afiliado_id',
        'lote_afiliado_id',
        'trabajo_id',
        'fecha_generacion',
        'motivo',
        'gestion',
        'periodo',
        'cuotas_vencidas',
        'descripcion',
        'monto',
        'saldo',
        'estado',
        'generada_por',
    ];

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PAGADA = 'pagada';

    public const ESTADO_ANULADA = 'anulada';

    /**
     * Multa trimestral: el día 1 del mes siguiente a 3 meses sin pagar. Su `periodo` (Y-m) es el
     * mes en que se aplica: 2026-04 por enero a marzo, 2026-08 por mayo a julio, 2026-12 por
     * septiembre a noviembre.
     */
    public const MOTIVO_MORA_TRIMESTRAL = 'mora_trimestral';

    /** Multa anual de la regla anterior (ya no se genera); se conserva para las registradas antes. */
    public const MOTIVO_MORA_ANUAL = 'mora_anual';

    public const MOTIVO_INDIVIDUAL = 'individual';

    public const MOTIVO_FALTA_TRABAJO = 'falta_trabajo';

    /**
     * @param  Builder<Multa>  $query
     * @return Builder<Multa>
     */
    public function scopePorCobrar(Builder $query): Builder
    {
        return $query
            ->where('estado', self::ESTADO_PENDIENTE)
            ->where(function (Builder $saldo): void {
                $saldo->whereNull('saldo')->orWhere('saldo', '>', 0);
            });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_generacion' => 'date',
            'gestion' => 'integer',
            'cuotas_vencidas' => 'integer',
            'monto' => 'decimal:2',
            'saldo' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<LoteAfiliado, $this>
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteAfiliado::class, 'lote_afiliado_id');
    }

    /**
     * @return BelongsTo<Afiliado, $this>
     */
    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(Afiliado::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generada_por');
    }

    /**
     * @return BelongsTo<Trabajo, $this>
     */
    public function trabajo(): BelongsTo
    {
        return $this->belongsTo(Trabajo::class);
    }
}
