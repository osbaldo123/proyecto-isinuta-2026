<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoteServicio extends Model
{
    use HasSpanishTimestamps;

    protected $table = 'lote_servicio';

    protected $fillable = [
        'lote_afiliado_id',
        'servicio_id',
        'tarifa_acordada',
        'fecha_inicio',
        'fecha_fin',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tarifa_acordada' => 'decimal:2',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
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
}
