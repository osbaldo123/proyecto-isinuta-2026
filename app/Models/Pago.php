<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Database\Factories\PagoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Pago extends Model
{
    /** @use HasFactory<PagoFactory> */
    use HasFactory, HasSpanishTimestamps;

    protected $fillable = [
        'numero_recibo',
        'tipo_recibo',
        'proposito',
        'proposito_label',
        'afiliado_id',
        'lote_afiliado_id',
        'fecha_pago',
        'monto_total',
        'metodo_pago',
        'estado',
        'cobrado_por',
        'observaciones',
        'cobro_id',
        'anulado_en',
        'anulado_por',
        'motivo_anulacion',
    ];

    public const ESTADO_EMITIDO = 'emitido';

    public const ESTADO_ANULADO = 'anulado';

    public const METODO_EFECTIVO = 'efectivo';

    public const METODO_TRANSFERENCIA = 'transferencia';

    public const METODO_QR = 'qr';

    public const TIPO_INGRESO = 'ingreso';

    public const TIPO_CAJA = 'caja';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_pago' => 'date',
            'monto_total' => 'decimal:2',
            'anulado_en' => 'datetime',
        ];
    }

    public function estaAnulado(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /**
     * Recibos emitidos en el mismo cobro (uno por propósito), incluido este.
     *
     * @return HasMany<Pago, $this>
     */
    public function recibosDelCobro(): HasMany
    {
        return $this->hasMany(self::class, 'cobro_id', 'cobro_id');
    }

    public static function generarNumeroRecibo(): string
    {
        $year = (int) now()->format('Y');

        $sequence = ReciboSequence::query()
            ->lockForUpdate()
            ->firstOrCreate(['year' => $year], ['last_number' => 0]);

        $sequence->increment('last_number');

        return 'REC-'.$year.'-'.str_pad((string) $sequence->fresh()->last_number, 6, '0', STR_PAD_LEFT);
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
    public function afiliado(): BelongsTo
    {
        return $this->belongsTo(Afiliado::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cobrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cobrado_por');
    }

    /**
     * @return HasMany<PagoDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(PagoDetalle::class);
    }

    /**
     * @param  Builder<Pago>  $query
     * @return Builder<Pago>
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim(preg_replace('/\s+/u', ' ', (string) $termino) ?? '');

        if ($termino === '') {
            return $query;
        }

        $tokens = preg_split('/\s+/u', $termino, -1, PREG_SPLIT_NO_EMPTY) ?: [$termino];
        $ciDigitos = preg_replace('/\D+/', '', $termino) ?? '';
        $nombreSql = DB::connection()->getDriverName() === 'sqlite'
            ? "LOWER(TRIM(nombres) || ' ' || TRIM(apellidos))"
            : "LOWER(CONCAT(TRIM(nombres), ' ', TRIM(apellidos)))";

        return $query->where(function (Builder $q) use ($termino, $tokens, $ciDigitos, $nombreSql): void {
            $q->where('numero_recibo', 'like', "%{$termino}%")
                ->orWhere('proposito_label', 'like', "%{$termino}%")
                ->orWhereHas('lote', function (Builder $lote) use ($termino): void {
                    $lote->where('numero_lote', 'like', "%{$termino}%");
                })
                ->orWhereHas('afiliado', function (Builder $afiliado) use ($termino, $tokens, $ciDigitos, $nombreSql): void {
                    $afiliado->where(function (Builder $persona) use ($termino, $tokens, $ciDigitos, $nombreSql): void {
                        $persona->where('ci', 'like', "%{$termino}%")
                            ->orWhere('nombres', 'like', "%{$termino}%")
                            ->orWhere('apellidos', 'like', "%{$termino}%")
                            ->orWhereRaw("{$nombreSql} like ?", ['%'.mb_strtolower($termino).'%']);

                        if ($ciDigitos !== '' && $ciDigitos !== $termino) {
                            $persona->orWhere('ci', 'like', "%{$ciDigitos}%");
                        }

                        if (count($tokens) > 1) {
                            $persona->orWhere(function (Builder $nombreCompleto) use ($tokens): void {
                                foreach ($tokens as $token) {
                                    $nombreCompleto->where(function (Builder $parte) use ($token): void {
                                        $parte->where('nombres', 'like', "%{$token}%")
                                            ->orWhere('apellidos', 'like', "%{$token}%");
                                    });
                                }
                            });
                        }
                    });
                });
        });
    }
}
