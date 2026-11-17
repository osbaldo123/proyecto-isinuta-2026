<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Database\Factories\TrabajoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trabajo extends Model
{
    /** @use HasFactory<TrabajoFactory> */
    use HasFactory, HasSpanishTimestamps;

    protected $fillable = [
        'otb_id',
        'tipo',
        'descripcion',
        'fecha_inicio',
        'monto_multa',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'monto_multa' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Otb, $this>
     */
    public function otb(): BelongsTo
    {
        return $this->belongsTo(Otb::class);
    }

    /**
     * @return HasMany<Asistencia, $this>
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * @return HasMany<Multa, $this>
     */
    public function multas(): HasMany
    {
        return $this->hasMany(Multa::class);
    }

    /**
     * @param  Builder<Trabajo>  $query
     * @return Builder<Trabajo>
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino): void {
            $q->where('tipo', 'like', "%{$termino}%")
                ->orWhere('descripcion', 'like', "%{$termino}%")
                ->orWhereHas('otb', function (Builder $otb) use ($termino): void {
                    $otb->where('nombre', 'like', "%{$termino}%");
                });
        });
    }
}
