<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use App\Support\TextoPresentacion;
use Database\Factories\AfiliadoFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Persona afiliada (datos personales).
 * La propiedad/lote vive en lote_afiliados (1 persona → N lotes).
 */
class Afiliado extends Model
{
    /** @use HasFactory<AfiliadoFactory> */
    use HasFactory, HasSpanishTimestamps;

    protected $fillable = [
        'nombres',
        'apellidos',
        'ci',
        'telefono',
        'direccion',
        'estado',
    ];

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_CORTADO = 'cortado';

    public const ESTADO_BAJA = 'baja';

    public const FORMA_CONTADO = 'contado';

    public const FORMA_PLAZO = 'plazo';

    /**
     * Lote activo en el contexto de la petición (detalle/cobro/edición).
     */
    public function conLote(?LoteAfiliado $lote): self
    {
        if ($lote !== null) {
            $this->setRelation('loteContexto', $lote);
        }

        return $this;
    }

    public function loteEnContexto(): ?LoteAfiliado
    {
        if ($this->relationLoaded('loteContexto')) {
            /** @var LoteAfiliado|null $lote */
            $lote = $this->getRelation('loteContexto');

            return $lote;
        }

        if ($this->relationLoaded('lotes')) {
            return $this->lotes->first();
        }

        return $this->lotes()->first();
    }

    /**
     * @return Attribute<string, never>
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->nombres} {$this->apellidos}"));
    }

    /** Accesos de compatibilidad: leen del lote en contexto. */
    protected function numeroLote(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->loteEnContexto()?->numero_lote);
    }

    protected function otbId(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->loteEnContexto()?->otb_id);
    }

    protected function fechaAfiliacion(): Attribute
    {
        return Attribute::get(fn () => $this->loteEnContexto()?->fecha_afiliacion);
    }

    protected function formaPagoAfiliacion(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->loteEnContexto()?->forma_pago_afiliacion);
    }

    protected function montoAfiliacion(): Attribute
    {
        return Attribute::get(fn () => $this->loteEnContexto()?->monto_afiliacion ?? 0);
    }

    protected function saldoAfiliacion(): Attribute
    {
        return Attribute::get(fn () => $this->loteEnContexto()?->saldo_afiliacion ?? 0);
    }

    protected function observaciones(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->loteEnContexto()?->observaciones);
    }

    public static function normalizarNombreParte(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }

    public static function conflictoNombreDistintoCi(
        string $nombres,
        string $apellidos,
        string $ci,
    ): ?self {
        $nombresKey = self::normalizarNombreParte($nombres);
        $apellidosKey = self::normalizarNombreParte($apellidos);

        return self::query()
            ->whereRaw('LOWER(TRIM(nombres)) = ?', [$nombresKey])
            ->whereRaw('LOWER(TRIM(apellidos)) = ?', [$apellidosKey])
            ->where('ci', '!=', $ci)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return HasMany<LoteAfiliado, $this>
     */
    public function lotes(): HasMany
    {
        return $this->hasMany(LoteAfiliado::class)->orderByRaw('CAST(numero_lote AS UNSIGNED), numero_lote');
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return HasMany<Pago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * @return HasMany<Multa, $this>
     */
    public function multas(): HasMany
    {
        return $this->hasMany(Multa::class);
    }

    /**
     * @return HasMany<Multa, $this>
     */
    public function multasPendientes(): HasMany
    {
        return $this->multas()->where('estado', Multa::ESTADO_PENDIENTE);
    }

    public function ciCompletoDisplay(): string
    {
        return (string) $this->ci;
    }

    public function identidadDocumento(): string
    {
        $lote = $this->loteEnContexto();
        $lote?->loadMissing('otb');

        return TextoPresentacion::componerNombreConOtb(
            (string) $this->nombre_completo,
            $lote?->otb?->nombre,
        );
    }

    public function codigoPropiedadDisplay(): string
    {
        $lote = $this->loteEnContexto()?->numero_lote;

        return filled($lote) ? (string) $lote : (string) $this->ci;
    }

    public static function siguienteNumeroLote(string $ci): string
    {
        $afiliado = static::query()
            ->where('ci', $ci)
            ->orderBy('id')
            ->first();

        if (! $afiliado) {
            return '1';
        }

        $lotes = $afiliado->lotes()->pluck('numero_lote');

        if ($lotes->isEmpty()) {
            return '1';
        }

        $max = 0;
        foreach ($lotes as $lote) {
            if (preg_match('/^(\d+)/', trim((string) $lote), $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return (string) ($max > 0 ? $max + 1 : $lotes->count() + 1);
    }

    public function etiquetaSelector(): string
    {
        $lote = $this->loteEnContexto();
        $num = $lote?->numero_lote;
        $etiquetaLote = filled($num) ? 'Lote '.$num : '';

        return trim('CI '.$this->ci.(filled($etiquetaLote) ? ' · '.$etiquetaLote : '').' — '.$this->nombre_completo);
    }

    /**
     * Crea o actualiza un lote de la persona.
     *
     * @param  array<string, mixed>  $datos
     */
    public function sincronizarLotePrincipal(array $datos = []): LoteAfiliado
    {
        $contexto = $this->relationLoaded('loteContexto') ? $this->getRelation('loteContexto') : null;
        $numeroLote = (string) ($datos['numero_lote'] ?? $contexto?->numero_lote ?? '1');

        $lote = LoteAfiliado::query()->updateOrCreate(
            [
                'afiliado_id' => $this->id,
                'numero_lote' => $numeroLote,
            ],
            [
                'otb_id' => $datos['otb_id'] ?? $contexto?->otb_id,
                'direccion' => $datos['direccion'] ?? $contexto?->direccion ?? $this->direccion,
                'fecha_afiliacion' => $datos['fecha_afiliacion'] ?? $contexto?->fecha_afiliacion ?? now()->toDateString(),
                'forma_pago_afiliacion' => $datos['forma_pago_afiliacion'] ?? $contexto?->forma_pago_afiliacion ?? self::FORMA_CONTADO,
                'monto_afiliacion' => $datos['monto_afiliacion'] ?? $contexto?->monto_afiliacion ?? 0,
                'monto_afiliacion_agua' => $datos['monto_afiliacion_agua'] ?? $contexto?->monto_afiliacion_agua ?? 0,
                'monto_afiliacion_alcantarillado' => $datos['monto_afiliacion_alcantarillado'] ?? $contexto?->monto_afiliacion_alcantarillado ?? 0,
                'saldo_afiliacion_agua' => $datos['saldo_afiliacion_agua'] ?? $contexto?->saldo_afiliacion_agua ?? 0,
                'saldo_afiliacion_alcantarillado' => $datos['saldo_afiliacion_alcantarillado'] ?? $contexto?->saldo_afiliacion_alcantarillado ?? 0,
                'saldo_afiliacion' => $datos['saldo_afiliacion'] ?? $contexto?->saldo_afiliacion ?? 0,
                'estado' => $datos['estado'] ?? $contexto?->estado ?? self::ESTADO_ACTIVO,
                'observaciones' => $datos['observaciones'] ?? $contexto?->observaciones,
                'categoria_id' => $datos['categoria_id'] ?? $contexto?->categoria_id,
                'categoria_propiedad' => $datos['categoria_propiedad'] ?? $contexto?->categoria_propiedad,
                'monto_categorizado' => array_key_exists('monto_categorizado', $datos)
                    ? $datos['monto_categorizado']
                    : $contexto?->monto_categorizado,
            ],
        );

        if (array_key_exists('telefono', $datos)) {
            $lote->telefono = $datos['telefono'] !== '' ? $datos['telefono'] : null;
            $lote->save();
        } elseif (blank($lote->telefono) && filled($this->telefono)) {
            $lote->telefono = $this->telefono;
            $lote->save();
        }

        $this->conLote($lote);

        return $lote;
    }

    /**
     * Si todos los lotes comparten número, la persona queda con ese teléfono.
     * Si cada lote tiene el suyo, la persona conserva el del lote más antiguo
     * para el usuario de consulta.
     */
    public function refrescarTelefonoDesdeLotes(): void
    {
        $telefonos = $this->lotes()
            ->orderBy('id')
            ->pluck('telefono')
            ->map(fn ($telefono) => filled($telefono) ? (string) $telefono : null);

        $distintos = $telefonos->filter()->unique()->values();

        $this->telefono = $distintos->count() <= 1
            ? $distintos->first()
            : $telefonos->first(fn ($telefono) => filled($telefono));

        if ($this->isDirty('telefono')) {
            $this->save();
        }
    }

    /**
     * OTB del lote en contexto (compatibilidad con $afiliado->otb).
     *
     * @return Attribute<Otb|null, never>
     */
    protected function otb(): Attribute
    {
        return Attribute::get(function (): ?Otb {
            $lote = $this->loteEnContexto();

            if (! $lote) {
                return null;
            }

            $lote->loadMissing('otb');

            return $lote->otb;
        });
    }
}
