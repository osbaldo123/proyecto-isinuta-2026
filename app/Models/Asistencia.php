<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    use HasSpanishTimestamps;

    protected $table = 'asistencias';

    protected $fillable = [
        'trabajo_id',
        'afiliado_id',
        'lote_afiliado_id',
        'presente',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'presente' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Trabajo, $this>
     */
    public function trabajo(): BelongsTo
    {
        return $this->belongsTo(Trabajo::class);
    }

    /**
     * @return BelongsTo<Afiliado, $this>
     */
    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(Afiliado::class);
    }

    /**
     * @return BelongsTo<LoteAfiliado, $this>
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteAfiliado::class, 'lote_afiliado_id');
    }

    public function marcarPresente(bool $presente = true): void
    {
        $this->update(['presente' => $presente]);
    }
}
