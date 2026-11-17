<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Database\Factories\OtbFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Otb extends Model
{
    /** @use HasFactory<OtbFactory> */
    use HasFactory, HasSpanishTimestamps;

    protected $table = 'otbs';

    protected $fillable = ['nombre', 'descripcion', 'tipo', 'activa'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    /**
     * @return HasMany<LoteAfiliado, $this>
     */
    public function lotes(): HasMany
    {
        return $this->hasMany(LoteAfiliado::class, 'otb_id');
    }

    /**
     * @deprecated Use lotes(); se mantiene por compatibilidad.
     *
     * @return HasMany<LoteAfiliado, $this>
     */
    public function afiliados(): HasMany
    {
        return $this->lotes();
    }
}
