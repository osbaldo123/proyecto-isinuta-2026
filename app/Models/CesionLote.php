<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transferencia (cesión) de un lote de un afiliado a otra persona.
 */
class CesionLote extends Model
{
    use HasSpanishTimestamps;

    protected $table = 'cesiones_lote';

    protected $fillable = [
        'lote_afiliado_id',
        'afiliado_anterior_id',
        'nombre_anterior',
        'ci_anterior',
        'afiliado_nuevo_id',
        'nombre_nuevo',
        'ci_nuevo',
        'fecha_cesion',
        'motivo',
        'documento_respaldo',
        'registrado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_cesion' => 'date',
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
    public function afiliadoAnterior(): BelongsTo
    {
        return $this->belongsTo(Afiliado::class, 'afiliado_anterior_id');
    }

    /**
     * @return BelongsTo<Afiliado, $this>
     */
    public function afiliadoNuevo(): BelongsTo
    {
        return $this->belongsTo(Afiliado::class, 'afiliado_nuevo_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
