<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasSpanishTimestamps;

    public const SLUG_COMERCIAL = 'comercial';

    public const SLUG_DOMICILIARIA = 'domiciliaria';

    protected $fillable = [
        'slug',
        'nombre',
        'descripcion',
        'tarifa_agua',
        'tarifa_alcantarillado',
        'activo',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tarifa_agua' => 'decimal:2',
            'tarifa_alcantarillado' => 'decimal:2',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * @return HasMany<LoteAfiliado, $this>
     */
    public function lotes(): HasMany
    {
        return $this->hasMany(LoteAfiliado::class, 'categoria_id');
    }

    public function tarifaMensualTotal(): float
    {
        return round((float) $this->tarifa_agua + (float) $this->tarifa_alcantarillado, 2);
    }

    public static function existeNombre(string $nombre, ?int $ignoreId = null): bool
    {
        $clave = mb_strtolower(trim($nombre));

        if ($clave === '') {
            return false;
        }

        return self::query()
            ->whereRaw('LOWER(TRIM(nombre)) = ?', [$clave])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    public static function generarSlugUnico(string $nombre, ?int $ignoreId = null): string
    {
        $base = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre) ?: $nombre));
        $base = trim($base, '-') ?: 'categoria';
        $base = substr($base, 0, 35);
        $candidato = $base;
        $n = 1;

        while (
            self::query()
                ->where('slug', $candidato)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $sufijo = (string) $n;
            $candidato = substr($base, 0, max(1, 40 - strlen($sufijo))).$sufijo;
            $n++;
        }

        return $candidato;
    }
}
