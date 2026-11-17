<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Database\Factories\ServicioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servicio extends Model
{
    /** @use HasFactory<ServicioFactory> */
    use HasFactory, HasSpanishTimestamps;

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'tarifa_mensual',
        'costo_instalacion_contado',
        'costo_instalacion_plazos',
        'descripcion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tarifa_mensual' => 'decimal:2',
            'costo_instalacion_contado' => 'decimal:2',
            'costo_instalacion_plazos' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<LoteAfiliado, $this>
     */
    public function lotes(): BelongsToMany
    {
        return $this->belongsToMany(LoteAfiliado::class, 'lote_servicio', 'servicio_id', 'lote_afiliado_id')
            ->withPivot(['tarifa_acordada', 'fecha_inicio', 'fecha_fin'])
            ->withTimestamps('fecha_creacion', 'fecha_actualizacion');
    }

    /**
     * Agua y alcantarillado guardan su tarifa en la categoría; el resto, aquí.
     *
     * @return BelongsToMany<Categoria, $this>
     */
    public function tarifasCategoria(): BelongsToMany
    {
        return $this->belongsToMany(Categoria::class, 'servicio_categoria_tarifas')
            ->withPivot('tarifa')
            ->withTimestamps('fecha_creacion', 'fecha_actualizacion');
    }

    public function tarifaParaCategoria(Categoria $categoria): float
    {
        return match ($this->tipoTarifaPorCategoria()) {
            'agua' => (float) $categoria->tarifa_agua,
            'alcantarillado' => (float) $categoria->tarifa_alcantarillado,
            default => (float) ($this->tarifasCategoria->firstWhere('id', $categoria->id)?->pivot->tarifa
                ?? $this->tarifa_mensual),
        };
    }

    /**
     * @return HasMany<LoteServicio, $this>
     */
    public function loteServicios(): HasMany
    {
        return $this->hasMany(LoteServicio::class);
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

    /**
     * @param  Builder<Servicio>  $query
     * @return Builder<Servicio>
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino): void {
            $q->where('codigo', 'like', "%{$termino}%")
                ->orWhere('nombre', 'like', "%{$termino}%")
                ->orWhere('tipo', 'like', "%{$termino}%")
                ->orWhere('descripcion', 'like', "%{$termino}%");
        });
    }

    /**
     * Agua potable y alcantarillado: servicios base cargados al instalar el sistema.
     * Su tarifa mensual depende de la categoría del lote.
     *
     * @var array<int, string>
     */
    public const TIPOS_BASE = ['agua', 'alcantarillado'];

    /**
     * @var array<string, string>
     */
    public const TIPOS = [
        'agua' => 'Agua potable',
        'alcantarillado' => 'Alcantarillado',
        'inspeccion' => 'Inspección, mantenimiento y reparación',
        'otro' => 'Otro',
    ];

    /**
     * @return array<int, string>
     */
    public static function tiposPermitidos(): array
    {
        return array_keys(self::TIPOS);
    }

    /**
     * El tipo se escribe a mano; si coincide con un tipo conocido (por valor o etiqueta) se guarda su valor.
     */
    public static function normalizarTipo(string $texto): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        $buscado = mb_strtolower($texto);

        foreach (self::TIPOS as $valor => $etiqueta) {
            if ($buscado === $valor || $buscado === mb_strtolower($etiqueta)) {
                return $valor;
            }
        }

        return $texto;
    }

    public static function etiquetaTipo(?string $tipo): string
    {
        return self::TIPOS[mb_strtolower(trim((string) $tipo))] ?? (string) $tipo;
    }

    public function esBase(): bool
    {
        return in_array(mb_strtolower(trim((string) $this->tipo)), self::TIPOS_BASE, true);
    }

    public function esAgua(): bool
    {
        return mb_strtolower(trim((string) $this->tipo)) === 'agua';
    }

    public function esAlcantarillado(): bool
    {
        return mb_strtolower(trim((string) $this->tipo)) === 'alcantarillado';
    }

    /**
     * 'agua' o 'alcantarillado' si se cobra con la tarifa de la categoría del lote; null si usa tarifa_mensual.
     */
    public function tipoTarifaPorCategoria(): ?string
    {
        return $this->esBase() ? mb_strtolower(trim((string) $this->tipo)) : null;
    }
}