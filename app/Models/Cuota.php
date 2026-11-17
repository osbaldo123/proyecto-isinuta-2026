<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cuota extends Model
{
    use HasSpanishTimestamps;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PAGADA = 'pagada';

    protected $fillable = [
        'lote_afiliado_id',
        'servicio_id',
        'periodo',
        'gestion',
        'monto_original',
        'saldo',
        'fecha_vencimiento',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gestion' => 'integer',
            'monto_original' => 'decimal:2',
            'saldo' => 'decimal:2',
            'fecha_vencimiento' => 'date',
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
     * @return BelongsTo<Servicio, $this>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /**
     * Pendiente (atrasada) para cobro/caja: el mes de servicio se cobra desde el 1 del mes
     * siguiente; fecha_vencimiento es el día 2 (en cobro 1–2) y desde el día 3 queda pendiente.
     */
    public function vencida(): bool
    {
        return $this->fecha_vencimiento !== null
            && $this->fecha_vencimiento->toDateString() < now()->toDateString()
            && (float) $this->saldo > 0;
    }
}
