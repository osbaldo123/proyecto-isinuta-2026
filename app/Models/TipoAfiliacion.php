<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class TipoAfiliacion extends Model
{
    use HasSpanishTimestamps;

    protected $table = 'tipos_afiliacion';

    public const CODIGO_AGUA = 'agua';

    public const CODIGO_ALCANTARILLADO = 'alcantarillado';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'modalidad',
        'monto',
        'monto_plazo',
        'activo',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'monto_plazo' => 'decimal:2',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<LoteAfiliado, $this>
     */
    public function lotes(): BelongsToMany
    {
        return $this->belongsToMany(LoteAfiliado::class, 'lote_tipo_afiliacion')
            ->withPivot('monto')
            ->withTimestamps('fecha_creacion', 'fecha_actualizacion');
    }

    public function montoPlazo(): float
    {
        return (float) $this->monto_plazo > 0 ? (float) $this->monto_plazo : (float) $this->monto;
    }

    public function montoContado(): float
    {
        return (float) $this->monto > 0 ? (float) $this->monto : (float) $this->monto_plazo;
    }

    public function conceptoCobro(): string
    {
        return 'afiliacion_'.$this->codigo;
    }

    public static function codigoDesdeConcepto(string $concepto): ?string
    {
        if (! str_starts_with($concepto, 'afiliacion_')) {
            return null;
        }

        $codigo = substr($concepto, strlen('afiliacion_'));

        return $codigo !== '' ? $codigo : null;
    }

    public static function generarCodigoUnico(string $nombre, ?int $ignoreId = null): string
    {
        $base = Str::slug(mb_strtolower(trim($nombre)), '_');
        $base = preg_replace('/[^a-z0-9_]+/', '_', $base ?? '') ?: 'afiliacion';
        $base = trim($base, '_');

        $codigo = $base;
        $i = 2;
        while (self::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('codigo', $codigo)
            ->exists()) {
            $codigo = $base.'_'.$i;
            $i++;
        }

        return $codigo;
    }

    /**
     * @return list<array{id: int, codigo: string, nombre: string, descripcion: string, monto: float, monto_plazo: float, concepto: string}>
     */
    public static function opcionesActivas(): array
    {
        return self::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (self $tipo): array => [
                'id' => $tipo->id,
                'codigo' => $tipo->codigo,
                'nombre' => $tipo->nombre,
                'descripcion' => $tipo->descripcion ?? '',
                'monto' => (float) $tipo->monto,
                'monto_plazo' => $tipo->montoPlazo(),
                'concepto' => $tipo->conceptoCobro(),
            ])
            ->values()
            ->all();
    }

    public static function montoPorConcepto(string $concepto): ?float
    {
        $codigo = self::codigoDesdeConcepto($concepto);
        if ($codigo === null) {
            return null;
        }

        $tipo = self::query()->where('codigo', $codigo)->where('activo', true)->first();

        return $tipo ? (float) $tipo->monto : null;
    }

    public static function labelPorConcepto(string $concepto): ?string
    {
        $codigo = self::codigoDesdeConcepto($concepto);
        if ($codigo === null) {
            return null;
        }

        return self::query()->where('codigo', $codigo)->value('nombre');
    }
}
