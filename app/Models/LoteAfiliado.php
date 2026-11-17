<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use App\Services\CategoriaTarifaService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoteAfiliado extends Model
{
    use HasFactory, HasSpanishTimestamps;

    protected $table = 'lote_afiliados';

    public const CATEGORIA_ALOJAMIENTO = 'alojamiento';

    public const CATEGORIA_COMERCIO = 'comercio';

    public const CATEGORIA_INQUILINOS = 'inquilinos';

    /** Montos base sugeridos (Bs) por categoría — cobro mensual. */
    public const MONTO_ALOJAMIENTO = 17.0;

    public const MONTO_COMERCIO = 17.0;

    public const MONTO_POR_INQUILINO = 25.0;

    protected $fillable = [
        'afiliado_id',
        'numero_lote',
        'otb_id',
        'direccion',
        'telefono',
        'fecha_afiliacion',
        'forma_pago_afiliacion',
        'monto_afiliacion',
        'monto_afiliacion_agua',
        'monto_afiliacion_alcantarillado',
        'saldo_afiliacion_agua',
        'saldo_afiliacion_alcantarillado',
        'saldo_afiliacion',
        'estado',
        'observaciones',
        'categoria_id',
        'categoria_propiedad',
        'monto_categorizado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_afiliacion' => 'date',
            'monto_afiliacion' => 'decimal:2',
            'monto_afiliacion_agua' => 'decimal:2',
            'monto_afiliacion_alcantarillado' => 'decimal:2',
            'saldo_afiliacion_agua' => 'decimal:2',
            'saldo_afiliacion_alcantarillado' => 'decimal:2',
            'saldo_afiliacion' => 'decimal:2',
            'monto_categorizado' => 'decimal:2',
        ];
    }

    public static function labelCategoria(?string $categoria): string
    {
        if ($categoria === null || $categoria === '') {
            return '—';
        }

        $nombre = Categoria::query()->where('slug', $categoria)->value('nombre');

        return $nombre ?? ucfirst(str_replace('-', ' ', $categoria));
    }

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    /**
     * @return BelongsTo<Afiliado, $this>
     */
    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(Afiliado::class);
    }

    /**
     * @return BelongsTo<Otb, $this>
     */
    public function otb(): BelongsTo
    {
        return $this->belongsTo(Otb::class);
    }

    /**
     * @return HasMany<Pago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'lote_afiliado_id');
    }

    /**
     * Historial de transferencias (cesiones) del lote, la más reciente primero.
     *
     * @return HasMany<CesionLote, $this>
     */
    public function cesiones(): HasMany
    {
        return $this->hasMany(CesionLote::class, 'lote_afiliado_id')
            ->orderByDesc('fecha_cesion')
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<Multa, $this>
     */
    public function multas(): HasMany
    {
        return $this->hasMany(Multa::class, 'lote_afiliado_id');
    }

    /**
     * @return HasMany<LoteServicio, $this>
     */
    public function loteServicios(): HasMany
    {
        return $this->hasMany(LoteServicio::class, 'lote_afiliado_id');
    }

    /**
     * @return BelongsToMany<Servicio, $this>
     */
    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'lote_servicio', 'lote_afiliado_id')
            ->withPivot(['tarifa_acordada', 'fecha_inicio', 'fecha_fin'])
            ->withTimestamps('fecha_creacion', 'fecha_actualizacion');
    }

    /**
     * @return BelongsToMany<Servicio, $this>
     */
    public function serviciosActivos(): BelongsToMany
    {
        return $this->servicios()
            ->where('servicios.activo', true)
            ->where(function ($query): void {
                $query->whereNull('lote_servicio.fecha_fin')
                    ->orWhereDate('lote_servicio.fecha_fin', '>=', now()->toDateString());
            });
    }

    /**
     * @return BelongsToMany<TipoAfiliacion, $this>
     */
    public function tiposAfiliacion(): BelongsToMany
    {
        return $this->belongsToMany(TipoAfiliacion::class, 'lote_tipo_afiliacion', 'lote_afiliado_id', 'tipo_afiliacion_id')
            ->withPivot('monto')
            ->withTimestamps('fecha_creacion', 'fecha_actualizacion');
    }

    /**
     * @param  list<int|string>  $tipoIds
     * @param  array<int, float|int|string>  $montosPorTipoId
     */
    public function sincronizarTiposAfiliacion(array $tipoIds, array $montosPorTipoId = []): void
    {
        $ids = collect($tipoIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $tipos = TipoAfiliacion::query()
            ->whereIn('id', $ids)
            ->where('activo', true)
            ->get()
            ->keyBy('id');

        $sync = [];
        foreach ($ids as $id) {
            $tipo = $tipos->get($id);
            if (! $tipo) {
                continue;
            }
            $montoOverride = $montosPorTipoId[$id] ?? $montosPorTipoId[(string) $id] ?? null;
            $sync[$id] = [
                'monto' => $montoOverride !== null
                    ? round((float) $montoOverride, 2)
                    : (float) $tipo->monto,
            ];
        }

        $this->tiposAfiliacion()->sync($sync);

        $aguaTipo = $tipos->firstWhere('codigo', TipoAfiliacion::CODIGO_AGUA);
        $alcTipo = $tipos->firstWhere('codigo', TipoAfiliacion::CODIGO_ALCANTARILLADO);

        $this->update([
            'monto_afiliacion_agua' => ($aguaTipo && isset($sync[$aguaTipo->id])) ? (float) $sync[$aguaTipo->id]['monto'] : 0,
            'monto_afiliacion_alcantarillado' => ($alcTipo && isset($sync[$alcTipo->id])) ? (float) $sync[$alcTipo->id]['monto'] : 0,
            'monto_afiliacion' => round(collect($sync)->sum('monto'), 2),
        ]);
    }

    /**
     * El mismo número puede repetirse en los lotes de una persona.
     * No puede usarlo otra persona ni el usuario de otra persona.
     */
    public static function telefonoUsadoPorOtraPersona(?string $telefono, ?int $afiliadoId = null, ?string $ci = null): bool
    {
        $telefono = preg_replace('/\D/', '', (string) $telefono);

        if ($telefono === '') {
            return false;
        }

        $otraPersona = Afiliado::query()
            ->where('telefono', $telefono)
            ->when($afiliadoId, fn ($query) => $query->where('id', '!=', $afiliadoId))
            ->exists();

        if ($otraPersona) {
            return true;
        }

        $otroLote = self::query()
            ->where('telefono', $telefono)
            ->when($afiliadoId, fn ($query) => $query->where('afiliado_id', '!=', $afiliadoId))
            ->exists();

        if ($otroLote) {
            return true;
        }

        return User::query()
            ->where('telefono', $telefono)
            ->when($ci || $afiliadoId, function ($query) use ($ci, $afiliadoId): void {
                if ($ci) {
                    $query->where(function ($inner) use ($ci): void {
                        $inner->whereNull('ci')->orWhere('ci', '!=', $ci);
                    });
                }

                if ($afiliadoId) {
                    $query->where(function ($inner) use ($afiliadoId): void {
                        $inner->whereNull('afiliado_id')->orWhere('afiliado_id', '!=', $afiliadoId);
                    });
                }
            })
            ->exists();
    }

    /**
     * El número de lote debe ser único en todo el sistema.
     */
    public static function existeNumeroLote(string $numeroLote, ?int $ignorarLoteId = null): bool
    {
        return self::query()
            ->where('numero_lote', $numeroLote)
            ->when($ignorarLoteId !== null, fn ($q) => $q->where('id', '!=', $ignorarLoteId))
            ->exists();
    }

    /**
     * Primer número de lote libre (sin repetir en ninguna OTB).
     */
    public static function sugerirNumeroLote(?int $ignorarLoteId = null): string
    {
        $normalizar = static fn (mixed $valor): string => (string) preg_replace('/\D/', '', trim((string) $valor));

        $bloqueados = self::query()
            ->when($ignorarLoteId !== null, fn ($q) => $q->where('id', '!=', $ignorarLoteId))
            ->pluck('numero_lote')
            ->map($normalizar)
            ->filter(fn (string $n): bool => $n !== '')
            ->unique();

        $siguiente = 1;
        while ($bloqueados->contains((string) $siguiente)) {
            $siguiente++;
        }

        return (string) $siguiente;
    }

    /**
     * Duplicidad global: misma OTB + dirección + número de lote.
     */
    public static function existeUbicacionDuplicada(
        int $otbId,
        string $numeroLote,
        ?string $direccion,
        ?int $ignorarLoteId = null,
    ): bool {
        $direccionNorm = mb_strtolower(trim((string) $direccion));

        return self::query()
            ->where('otb_id', $otbId)
            ->where('numero_lote', $numeroLote)
            ->when($ignorarLoteId !== null, fn ($q) => $q->where('id', '!=', $ignorarLoteId))
            ->get()
            ->contains(fn (self $lote): bool => mb_strtolower(trim((string) $lote->direccion)) === $direccionNorm);
    }

    public function mismaUbicacion(int $otbId, string $numeroLote, ?string $direccion): bool
    {
        $direccionNorm = mb_strtolower(trim((string) $direccion));

        return (int) $this->otb_id === $otbId
            && (string) $this->numero_lote === $numeroLote
            && mb_strtolower(trim((string) $this->direccion)) === $direccionNorm;
    }

    /**
     * @param  array<int>  $servicioIds
     * @param  string|null  $fechaAfiliacionAnterior  null en el alta; en la edición, la fecha que tenía el lote
     */
    public function sincronizarServicios(array $servicioIds, string $fechaInicio, ?string $fechaAfiliacionAnterior = null): void
    {
        $ids = array_values(array_unique(array_map('intval', $servicioIds)));
        $sync = [];
        $this->loadMissing('categoria');
        $tarifaService = app(CategoriaTarifaService::class);
        $actuales = $this->servicios()->get()->keyBy('id');

        foreach ($ids as $servicioId) {
            $servicio = Servicio::query()->find($servicioId);

            if (! $servicio) {
                continue;
            }

            $tarifa = $this->categoria
                ? $tarifaService->tarifaParaServicio($this->categoria, $servicio)
                : (float) $servicio->tarifa_mensual;

            // Inicio de cobro POR SERVICIO:
            // - alta del lote: todos desde la fecha de afiliación;
            // - servicio que ya tenía el lote: conserva su inicio, salvo que haya empezado con la
            //   afiliación (entonces acompaña la corrección de la fecha);
            // - servicio agregado en una edición (p. ej. alcantarillado conectado después): desde hoy.
            $inicio = $fechaInicio;
            $pivot = $actuales->get($servicioId)?->pivot;

            if ($pivot && $fechaAfiliacionAnterior !== null) {
                $inicioActual = $pivot->fecha_inicio ? \Carbon\Carbon::parse($pivot->fecha_inicio)->toDateString() : null;
                $anterior = \Carbon\Carbon::parse($fechaAfiliacionAnterior)->toDateString();
                $inicio = ($inicioActual !== null && $inicioActual !== $anterior) ? $inicioActual : $fechaInicio;
            } elseif (! $pivot && $fechaAfiliacionAnterior !== null) {
                $inicio = max(\Carbon\Carbon::parse($fechaInicio)->toDateString(), now()->toDateString());
            }

            $sync[$servicioId] = [
                'tarifa_acordada' => $tarifa,
                'fecha_inicio' => $inicio,
                'fecha_fin' => null,
            ];
        }

        $this->servicios()->sync($sync);

        if ($this->categoria) {
            $this->update([
                'categoria_propiedad' => $this->categoria->slug,
                'monto_categorizado' => round(array_sum(array_column($sync, 'tarifa_acordada')), 2),
            ]);
        }

        $this->alinearTiposAfiliacionBaseConServicios();
    }

    public function tieneServicioAgua(): bool
    {
        $this->loadMissing('servicios');

        return $this->servicios->contains(fn (Servicio $servicio): bool => $servicio->esAgua());
    }

    public function tieneServicioAlcantarillado(): bool
    {
        $this->loadMissing('servicios');

        return $this->servicios->contains(fn (Servicio $servicio): bool => $servicio->esAlcantarillado());
    }

    public function montoMensualPorServiciosContratados(): float
    {
        $this->loadMissing(['categoria', 'servicios']);

        if (! $this->categoria) {
            return round(
                $this->servicios->sum(fn (Servicio $s) => (float) ($s->pivot->tarifa_acordada ?? $s->tarifa_mensual)),
                2,
            );
        }

        $tarifaService = app(CategoriaTarifaService::class);

        return round(
            $this->servicios->sum(fn (Servicio $s) => $tarifaService->tarifaParaServicio($this->categoria, $s)),
            2,
        );
    }

    /**
     * Agua y alcantarillado: si el lote tiene el servicio, debe tener la afiliación
     * (y al revés, si no tiene el servicio, no debe figurar esa afiliación base).
     * Otros tipos de afiliación personalizados se conservan.
     */
    public function alinearTiposAfiliacionBaseConServicios(): void
    {
        $this->loadMissing('servicios');

        $tieneAgua = $this->servicios->contains(fn (Servicio $servicio): bool => $servicio->esAgua());

        $tieneAlc = $this->servicios->contains(fn (Servicio $servicio): bool => $servicio->esAlcantarillado());

        $tipoAgua = TipoAfiliacion::query()->where('codigo', TipoAfiliacion::CODIGO_AGUA)->first();
        $tipoAlc = TipoAfiliacion::query()->where('codigo', TipoAfiliacion::CODIGO_ALCANTARILLADO)->first();

        $actuales = $this->tiposAfiliacion()
            ->get()
            ->keyBy('id');

        $sync = [];
        foreach ($actuales as $id => $tipo) {
            if (in_array($tipo->codigo, [TipoAfiliacion::CODIGO_AGUA, TipoAfiliacion::CODIGO_ALCANTARILLADO], true)) {
                continue;
            }
            $sync[(int) $id] = ['monto' => (float) ($tipo->pivot->monto ?? $tipo->monto)];
        }

        if ($tieneAgua && $tipoAgua) {
            $monto = (float) ($this->monto_afiliacion_agua > 0
                ? $this->monto_afiliacion_agua
                : ($actuales->get($tipoAgua->id)?->pivot?->monto ?? $tipoAgua->monto));
            $sync[$tipoAgua->id] = ['monto' => $monto];
        }

        if ($tieneAlc && $tipoAlc) {
            $monto = (float) ($this->monto_afiliacion_alcantarillado > 0
                ? $this->monto_afiliacion_alcantarillado
                : ($actuales->get($tipoAlc->id)?->pivot?->monto ?? $tipoAlc->monto));
            $sync[$tipoAlc->id] = ['monto' => $monto];
        }

        $this->tiposAfiliacion()->sync($sync);

        $aguaMonto = ($tieneAgua && $tipoAgua && isset($sync[$tipoAgua->id]))
            ? (float) $sync[$tipoAgua->id]['monto']
            : 0.0;
        $alcMonto = ($tieneAlc && $tipoAlc && isset($sync[$tipoAlc->id]))
            ? (float) $sync[$tipoAlc->id]['monto']
            : 0.0;

        $this->update([
            'monto_afiliacion_agua' => $aguaMonto,
            'monto_afiliacion_alcantarillado' => $alcMonto,
            'monto_afiliacion' => round($aguaMonto + $alcMonto, 2),
        ]);
    }
}
