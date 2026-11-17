<?php

namespace App\Services;

use App\Models\Afiliado;
use App\Models\Configuracion;
use App\Models\Cuota;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\PagoDetalle;
use App\Models\User;
use App\Support\TextoPresentacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CalculadorMultasService
{
    /** La multa anual es de la regla anterior: ya no se genera, pero las registradas siguen contando. */
    private const MOTIVOS_MORA = [Multa::MOTIVO_MORA_TRIMESTRAL, Multa::MOTIVO_MORA_ANUAL];

    public function __construct(
        private readonly DeudaAfiliadoService $deudaAfiliado,
        private readonly CuotaMensualService $cuotas,
    ) {}

    /**
     * Aplica la multa trimestral a cada lote del afiliado (ver evaluarLote).
     *
     * @return int Multas nuevas creadas
     */
    public function evaluarYGenerar(Afiliado $afiliado, ?User $usuario = null): int
    {
        $afiliado->loadMissing('lotes');
        $creadas = 0;

        foreach ($afiliado->lotes as $lote) {
            $creadas += $this->evaluarLote($lote, $usuario);
        }

        return $creadas;
    }

    /**
     * Regla Isinuta de la multa trimestral (Bs 50 y 3 meses, valores de Configuración):
     *
     *  - 3 meses sin pagar → el día 1 del mes siguiente: recibo del año en Bs 50 (ene–mar → 1 abr).
     *  - Si no pagó ese recibo, el siguiente tramo (may–jul → 1 ago) SUMA en el mismo recibo → Bs 100.
     *  - Si sigue sin pagar, el tramo anual (sep–nov → 1 dic) deja el mismo recibo en Bs 150.
     *  - Un solo recibo de multa por año y lote (máx. Bs 150). Al año siguiente, otro recibo aparte
     *    que otra vez va 50 → 100 → 150. Si pagó el recibo a mitad de año, el siguiente tramo abre uno nuevo.
     *  - La mensualidad se cobra aparte cada mes.
     *
     * @return int Multas nuevas o actualizadas (monto subió)
     */
    public function evaluarLote(LoteAfiliado $lote, ?User $usuario = null): int
    {
        if ($lote->estado === Afiliado::ESTADO_BAJA || ! $lote->afiliado_id) {
            return 0;
        }

        $this->cuotas->emitirLote($lote);

        $periodos = $this->mesesSinPagar($lote);
        $this->recalcularMultasDeReglaAnterior($lote, $periodos);
        $this->ubicarMultasSinMes($lote, $periodos);
        $this->consolidarRecibosMoraPorAnio($lote);

        $monto = (float) Configuracion::get(Configuracion::MULTA_TRIMESTRAL, 50);
        $creadas = 0;

        if ($periodos !== []) {
            foreach ($this->multasQueCorresponden($periodos, $this->ultimoMesConMulta($lote)) as $mora) {
                $creadas += $this->crearMoraSiFalta($lote, $mora, $monto, $usuario);
            }
        }

        // Alinea etiquetas del recibo único del año (50 / 100 / 150).
        $this->actualizarEtiquetasMoraVigentes($lote, $monto);

        return $creadas;
    }

    /**
     * Etiquetas del recibo de mora pendiente: el monto del recibo ES el acumulado (50/100/150).
     * Solo toca pendientes sin abono parcial.
     */
    private function actualizarEtiquetasMoraVigentes(LoteAfiliado $lote, float $base): void
    {
        $base = round($base, 2);

        $this->multasDeMora($lote)
            ->filter(fn (Multa $multa): bool => $multa->motivo === Multa::MOTIVO_MORA_TRIMESTRAL
                && $multa->estado === Multa::ESTADO_PENDIENTE
                && filled($multa->periodo)
                && abs((float) ($multa->saldo ?? 0) - (float) $multa->monto) < 0.001)
            ->each(function (Multa $multa) use ($base): void {
                $acumulado = (int) round((float) $multa->monto);
                $tramo = max(1, (int) round($acumulado / max(1, (int) round($base))));
                $tramo = min(3, $tramo);
                $rango = $this->rangoDesdeDescripcion((string) $multa->descripcion)
                    ?? ((int) $multa->cuotas_vencidas.' meses sin pagar');
                $descripcion = $this->descripcionMoraTramo($tramo, $base, $acumulado, $rango);

                if ($multa->descripcion !== $descripcion) {
                    $multa->update(['descripcion' => $descripcion]);
                }
            });
    }

    private function rangoDesdeDescripcion(string $descripcion): ?string
    {
        if (preg_match('/\d+ meses? sin pagar \([^)]+\)/u', $descripcion, $coincide) === 1) {
            return $coincide[0];
        }

        if (preg_match('/\(([^)]+)\)/u', $descripcion, $coincide) === 1) {
            return '3 meses sin pagar ('.$coincide[1].')';
        }

        return null;
    }

    /**
     * Quita la multa trimestral pendiente que se quedó sin sus meses. Se usa solo al corregir datos
     * del lote (p. ej. una fecha de afiliación mal cargada que generó meses que nunca existieron), no
     * al cobrar: pagar los meses no borra la multa.
     *
     * La multa sigue si, entre la multa anterior y el mes en que se aplicó, el lote todavía tiene los
     * 3 meses que la generaron (pagados o no).
     */
    public function retirarMoraQueYaNoCorresponde(LoteAfiliado $lote): int
    {
        $plazo = $this->mesesParaMulta();
        $meses = Cuota::query()
            ->where('lote_afiliado_id', $lote->id)
            ->distinct()
            ->orderBy('periodo')
            ->pluck('periodo')
            ->map(fn ($periodo): string => (string) $periodo)
            ->all();

        $multas = $this->multasDeMora($lote)
            ->sortBy(fn (Multa $multa): string => $this->mesDeMulta($multa).'|'.sprintf('%012d', $multa->id))
            ->values();

        $anterior = null;
        $retiradas = 0;

        foreach ($multas as $multa) {
            $mes = $this->mesDeMulta($multa);
            $base = count(array_filter(
                $meses,
                fn (string $periodo): bool => ($anterior === null || $periodo > $anterior) && $periodo < $mes,
            ));

            if ($base < $plazo && $multa->estado === Multa::ESTADO_PENDIENTE) {
                $multa->update([
                    'estado' => Multa::ESTADO_ANULADA,
                    'saldo' => 0,
                ]);
                $retiradas++;

                continue;
            }

            $anterior = $mes;
        }

        return $retiradas;
    }

    /**
     * Actualiza el estado del lote (y del afiliado) según los meses vencidos sin pagar.
     * Cortado con 12 mensualidades vencidas (Configuración › corte del servicio); vuelve a Activo
     * al pagar esas cuotas y las multas pendientes. Cada lote se evalúa por su deuda.
     */
    public function actualizarEstadoPorMora(Afiliado $afiliado): void
    {
        if ($afiliado->estado === Afiliado::ESTADO_BAJA) {
            return;
        }

        $afiliado->loadMissing('lotes');

        foreach ($this->lotesParaEstado($afiliado) as $lote) {
            $this->aplicarEstadoLotePorMora($lote);
        }

        $this->sincronizarEstadoAfiliadoDesdeLotes($afiliado->fresh() ?? $afiliado);
    }

    /**
     * Revisa la mora de todo el padrón: aplica las multas trimestrales que falten y actualiza los
     * cortes. La usa el Panel (como mucho cada 5 minutos), así la mora se aplica aunque no esté
     * corriendo la tarea diaria (cuotas:emitir).
     *
     * @return array{multas: int, cortes: int}
     */
    public function sincronizarMora(): array
    {
        $cortes = $this->sincronizarCortesPorMora();

        return ['multas' => $this->aplicarMultasPendientes(), 'cortes' => $cortes];
    }

    /**
     * Aplica las multas trimestrales que falten en el padrón: revisa solo los lotes con 3 meses sin
     * pagar después de su última multa o con multas sin pagar de la regla anterior.
     *
     * @return int Multas nuevas creadas
     */
    public function aplicarMultasPendientes(): int
    {
        $multas = 0;

        foreach ($this->lotesConMoraPorRevisar() as $lote) {
            $multas += $this->evaluarLote($lote);
        }

        return $multas;
    }

    /**
     * Recorre el padrón y registra como cortados a quienes ya tienen mora real (12 meses vencidos).
     * Un lote cortado vuelve a Activo cuando paga todas sus cuotas y multas.
     */
    public function sincronizarCortesPorMora(): int
    {
        return $this->recorrerPadronParaCortes(false);
    }

    /**
     * Vuelve a calcular los cortes solo con la regla: Cortado si tiene 12 meses vencidos sin pagar,
     * Activo si no, aunque todavía deba algo. Se usa cuando cambian los datos con los que se cortó
     * (se corrigió el lote o cambiaron los meses del corte en Configuración) y para corregir cortes
     * que quedaron de datos o configuraciones anteriores.
     *
     * @param  iterable<LoteAfiliado>|null  $lotes  null = todo el padrón
     * @return int Lotes o afiliados cuyo estado cambió
     */
    public function recalcularCortes(?iterable $lotes = null): int
    {
        if ($lotes === null) {
            return $this->recorrerPadronParaCortes(true);
        }

        $actualizados = 0;
        foreach ($lotes as $lote) {
            $afiliado = $lote->afiliado_id ? Afiliado::query()->find($lote->afiliado_id) : null;
            if ($this->aplicarEstadoLotePorMora($lote, true)) {
                $actualizados++;
            }
            if ($afiliado) {
                $this->sincronizarEstadoAfiliadoDesdeLotes($afiliado);
            }
        }

        return $actualizados;
    }

    private function recorrerPadronParaCortes(bool $estricto): int
    {
        $actualizados = 0;

        Afiliado::query()
            ->with(['lotes.serviciosActivos'])
            ->where('estado', '!=', Afiliado::ESTADO_BAJA)
            ->orderBy('id')
            ->chunkById(80, function ($afiliados) use (&$actualizados, $estricto): void {
                foreach ($afiliados as $afiliado) {
                    $antes = $afiliado->lotes->pluck('estado', 'id')->all();
                    $estadoAfiliadoAntes = $afiliado->estado;

                    foreach ($afiliado->lotes as $lote) {
                        $this->aplicarEstadoLotePorMora($lote, $estricto);
                    }
                    $this->sincronizarEstadoAfiliadoDesdeLotes($afiliado->fresh() ?? $afiliado);

                    $afiliado = $afiliado->fresh(['lotes']) ?? $afiliado;
                    $despues = $afiliado->lotes->pluck('estado', 'id')->all();
                    if ($antes !== $despues || $estadoAfiliadoAntes !== $afiliado->estado) {
                        $actualizados++;
                    }
                }
            });

        return $actualizados;
    }

    /**
     * @return Collection<int, LoteAfiliado>
     */
    private function lotesParaEstado(Afiliado $afiliado): Collection
    {
        if ($afiliado->relationLoaded('loteContexto')) {
            $lote = $afiliado->getRelation('loteContexto');
            if ($lote instanceof LoteAfiliado) {
                return collect([$lote]);
            }
        }

        return $afiliado->lotes
            ->filter(fn (LoteAfiliado $lote): bool => $lote->estado !== Afiliado::ESTADO_BAJA)
            ->values();
    }

    /**
     * Corte por mora: el lote pasa a Cortado al llegar a 12 meses vencidos sin pagar (cada mes de
     * servicio se cobra desde el 1 del mes siguiente; días 1–2 de gracia y desde el 3 queda vencido).
     * Vuelve a Activo cuando paga todas sus cuotas y multas. En modo estricto también vuelve a Activo
     * si ya no llega a los meses del corte, aunque todavía deba algo.
     */
    private function aplicarEstadoLotePorMora(LoteAfiliado $lote, bool $estricto = false): bool
    {
        if ($lote->estado === Afiliado::ESTADO_BAJA || ! $lote->afiliado_id) {
            return false;
        }

        $afiliado = $lote->relationLoaded('afiliado')
            ? $lote->afiliado
            : Afiliado::query()->find($lote->afiliado_id);

        if (! $afiliado || $afiliado->estado === Afiliado::ESTADO_BAJA) {
            return false;
        }

        $resumen = $this->deudaAfiliado->calcularDeudaResumen($afiliado->conLote($lote));
        // Corte por meses vencidos sin pagar (por defecto 12). La multa trimestral no corta el servicio.
        $umbralCorte = max(1, (int) Configuracion::get(Configuracion::MESES_CORTE_SERVICIO, 12));
        $mesesPendientes = (int) $resumen['meses_pendientes'];
        // Reactivar al ponerse al día en mensualidades y multas (no exige saldo de afiliación).
        $deudaServicio = round(
            (float) $resumen['total_cuotas_pendientes'] + (float) $resumen['total_multas_pendientes'],
            2
        );

        if ($mesesPendientes >= $umbralCorte) {
            if ($lote->estado !== Afiliado::ESTADO_CORTADO) {
                $lote->update(['estado' => Afiliado::ESTADO_CORTADO]);

                return true;
            }

            return false;
        }

        if ($lote->estado === Afiliado::ESTADO_CORTADO && ($deudaServicio <= 0 || $estricto)) {
            $lote->update(['estado' => Afiliado::ESTADO_ACTIVO]);

            return true;
        }

        return false;
    }

    private function sincronizarEstadoAfiliadoDesdeLotes(Afiliado $afiliado): void
    {
        if ($afiliado->estado === Afiliado::ESTADO_BAJA) {
            return;
        }

        $tieneCortado = $afiliado->lotes()
            ->where('estado', Afiliado::ESTADO_CORTADO)
            ->exists();

        $nuevo = $tieneCortado ? Afiliado::ESTADO_CORTADO : Afiliado::ESTADO_ACTIVO;

        if ($afiliado->estado !== $nuevo) {
            $afiliado->update(['estado' => $nuevo]);
        }
    }

    /**
     * Meses de servicio ya cerrados que el lote todavía debe (saldo > 0), del más antiguo al más
     * reciente. Sirve para la multa trimestral (día 1 tras 3 meses sin pagar).
     *
     * Distinto del estado «vencido» en caja: ahí hay gracia los días 1–2 del mes siguiente y
     * recién desde el 3 la cuota cuenta como vencida (Cuota::vencida / fecha_vencimiento).
     *
     * @return list<string> Periodos 'Y-m'
     */
    private function mesesSinPagar(LoteAfiliado $lote): array
    {
        return Cuota::query()
            ->where('lote_afiliado_id', $lote->id)
            ->where('saldo', '>', 0)
            ->where('periodo', '<', now()->format('Y-m'))
            ->distinct()
            ->orderBy('periodo')
            ->pluck('periodo')
            ->map(fn ($periodo): string => (string) $periodo)
            ->values()
            ->all();
    }

    /**
     * Trimestres fijos de mora (no cualquier 3 meses seguidos):
     *  - ene–feb–mar → multa el 1 de abril
     *  - may–jun–jul → multa el 1 de agosto (abril no cuenta)
     *  - sep–oct–nov → multa el 1 de diciembre (agosto no cuenta)
     *
     * Solo aplica si los 3 meses del tramo están sin pagar y ya llegó el mes de aplicación.
     *
     * @param  list<string>  $periodos  meses sin pagar 'Y-m', ordenados
     * @return list<array{periodo: string, meses: list<string>, adeudados: int}>
     */
    private function multasQueCorresponden(array $periodos, ?string $desde): array
    {
        if ($periodos === []) {
            return [];
        }

        $sinPagar = array_fill_keys($periodos, true);
        $hoy = now()->format('Y-m');
        $anios = [];

        foreach ($periodos as $periodo) {
            $anios[(int) substr($periodo, 0, 4)] = true;
        }

        // Incluye el año del mes de aplicación por si solo hay deuda a fin de año.
        foreach (array_keys($anios) as $anio) {
            $anios[$anio] = true;
        }

        ksort($anios);
        $moras = [];

        foreach (array_keys($anios) as $anio) {
            foreach ($this->tramosMoraDelAnio((int) $anio) as $tramo) {
                $aplica = $tramo['aplica'];

                // Todavía no llegó el mes de aplicación (p. ej. sep–nov solo el 1 de dic).
                if ($aplica > $hoy) {
                    continue;
                }

                // Ya aplicado este tramo (o uno posterior del mismo ciclo): no volver a sumar.
                if ($desde !== null && $aplica <= $desde) {
                    continue;
                }

                $meses = $tramo['meses'];
                $impagos = array_values(array_filter($meses, fn (string $p): bool => isset($sinPagar[$p])));

                // Los 3 meses del trimestre deben estar sin pagar.
                if (count($impagos) < 3) {
                    continue;
                }

                $moras[] = [
                    'periodo' => $aplica,
                    'meses' => $meses,
                    'adeudados' => 3,
                ];
            }
        }

        return $moras;
    }

    /**
     * @return list<array{meses: list<string>, aplica: string}>
     */
    private function tramosMoraDelAnio(int $anio): array
    {
        $y = sprintf('%04d', $anio);

        return [
            ['meses' => ["{$y}-01", "{$y}-02", "{$y}-03"], 'aplica' => "{$y}-04"],
            ['meses' => ["{$y}-05", "{$y}-06", "{$y}-07"], 'aplica' => "{$y}-08"],
            ['meses' => ["{$y}-09", "{$y}-10", "{$y}-11"], 'aplica' => "{$y}-12"],
        ];
    }

    /**
     * Multas sin pagar de la regla anterior (Bs 50 a los 3 meses y Bs 150 al año, una por gestión):
     * si el lote todavía debe los meses con los que se generaron, se anulan y la regla nueva aplica en
     * su lugar las que corresponden (por ejemplo, un año sin pagar queda en Bs 150 y no en Bs 200).
     * Las que tienen algún pago, o cuyos meses ya se pagaron, se mantienen (ver ubicarMultasSinMes).
     *
     * @param  list<string>  $periodos  meses sin pagar del lote
     */
    private function recalcularMultasDeReglaAnterior(LoteAfiliado $lote, array $periodos): void
    {
        $sinPagos = $this->multasDeMora($lote)
            ->filter(fn (Multa $multa): bool => $this->esDeReglaAnterior($multa)
                && $multa->estado === Multa::ESTADO_PENDIENTE
                && round((float) ($multa->saldo ?? $multa->monto), 2) >= round((float) $multa->monto, 2)
                && ! PagoDetalle::query()->where('multa_id', $multa->id)->exists())
            ->values();

        if ($sinPagos->isEmpty()) {
            return;
        }

        // Ningún mes que estaba vencido cuando se generaron se pagó después: la deuda sigue igual.
        $deudaIgual = $sinPagos->every(function (Multa $multa) use ($periodos): bool {
            $generada = $multa->fecha_generacion->format('Y-m');
            $vencidas = (int) $multa->cuotas_vencidas;

            return $vencidas > 0
                && count(array_filter($periodos, fn (string $periodo): bool => $periodo < $generada)) >= $vencidas;
        });

        if (! $deudaIgual) {
            return;
        }

        foreach ($sinPagos as $multa) {
            $multa->update([
                'estado' => Multa::ESTADO_ANULADA,
                'saldo' => 0,
                'descripcion' => Str::limit((string) $multa->descripcion, 200, '').' · recalculada con la regla trimestral',
            ]);
        }
    }

    /** Multa por mora generada con la regla anterior: la anual suelta o una mora sin el formato actual. */
    private function esDeReglaAnterior(Multa $multa): bool
    {
        $descripcion = (string) $multa->descripcion;

        if ($multa->motivo === Multa::MOTIVO_MORA_ANUAL) {
            return true;
        }

        // Formato actual: «Mora trimestral Bs 50|100: …» o «Mora anual Bs 150: …»
        return preg_match('/^Mora (trimestral Bs \d+|anual Bs \d+)/u', $descripcion) !== 1;
    }

    /**
     * Lotes con una multa trimestral por aplicar (3 meses sin pagar después de su última multa) o con
     * multas sin pagar de la regla anterior. Solo esos se revisan al sincronizar el padrón.
     *
     * @return Collection<int, LoteAfiliado>
     */
    private function lotesConMoraPorRevisar(): Collection
    {
        $ultimas = Multa::query()
            ->selectRaw('lote_afiliado_id, MAX(periodo) as ultimo_mes')
            ->whereIn('motivo', self::MOTIVOS_MORA)
            ->where('estado', '!=', Multa::ESTADO_ANULADA)
            ->whereNotNull('lote_afiliado_id')
            ->groupBy('lote_afiliado_id');

        $conMesesSinPagar = DB::table('cuotas')
            ->leftJoinSub($ultimas, 'ultimas', 'ultimas.lote_afiliado_id', '=', 'cuotas.lote_afiliado_id')
            ->where('cuotas.saldo', '>', 0)
            ->where('cuotas.periodo', '<', now()->format('Y-m'))
            ->where(fn ($q) => $q->whereNull('ultimas.ultimo_mes')->orWhereColumn('cuotas.periodo', '>', 'ultimas.ultimo_mes'))
            ->groupBy('cuotas.lote_afiliado_id')
            ->havingRaw('COUNT(DISTINCT cuotas.periodo) >= ?', [$this->mesesParaMulta()])
            ->pluck('cuotas.lote_afiliado_id');

        $conMultasAnteriores = Multa::query()
            ->whereIn('motivo', self::MOTIVOS_MORA)
            ->where('estado', Multa::ESTADO_PENDIENTE)
            ->whereNotNull('lote_afiliado_id')
            ->where(function ($q): void {
                $q->where('motivo', Multa::MOTIVO_MORA_ANUAL)
                    ->orWhereNull('descripcion')
                    ->orWhere(function ($texto): void {
                        $texto->where('descripcion', 'not like', 'Mora trimestral Bs %')
                            ->where('descripcion', 'not like', 'Mora anual Bs %');
                    });
            })
            ->distinct()
            ->pluck('lote_afiliado_id');

        // Incluye pendientes para realinear montos 50/100/150 al sincronizar el padrón.
        $conPendientes = Multa::query()
            ->where('motivo', Multa::MOTIVO_MORA_TRIMESTRAL)
            ->where('estado', Multa::ESTADO_PENDIENTE)
            ->whereNotNull('lote_afiliado_id')
            ->distinct()
            ->pluck('lote_afiliado_id');

        return LoteAfiliado::query()
            ->whereIn('id', $conMesesSinPagar->merge($conMultasAnteriores)->merge($conPendientes)->unique()->values())
            ->where('estado', '!=', Afiliado::ESTADO_BAJA)
            ->whereNotNull('afiliado_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * Ubica en la regla nueva las multas por mora que no tienen mes de aplicación: las que generó la
     * regla anterior (una trimestral por gestión y una anual de Bs 150) o las registradas a mano. Se
     * hace una sola vez por multa y solo se llena su mes: el monto y el estado no cambian.
     *
     * En orden, cada multa ocupa los meses en que la regla nueva la habría aplicado con la deuda que
     * el lote todavía tiene, sin pasar del mes en que se generó, y tantos como multas de Bs 50 vale
     * (la anual de Bs 150 vale tres). Así el afiliado no paga dos veces por los mismos meses. Si no
     * hay un mes que le corresponda (por ejemplo, porque ya pagó esos meses), queda en el mes en que
     * se generó, y los meses siguientes se cuentan desde ahí.
     *
     * @param  list<string>  $periodos  meses sin pagar del lote
     */
    private function ubicarMultasSinMes(LoteAfiliado $lote, array $periodos): void
    {
        $multas = $this->multasDeMora($lote);
        $sinMes = $multas
            ->filter(fn (Multa $multa): bool => blank($multa->periodo))
            ->sortBy(fn (Multa $multa): string => sprintf(
                '%04d|%012d',
                $multa->gestion ?: $multa->fecha_generacion->year,
                $multa->id,
            ))
            ->values();

        if ($sinMes->isEmpty()) {
            return;
        }

        // Meses ya ocupados por motivo (el índice único también cuenta las multas anuladas).
        $ocupados = Multa::query()
            ->where('lote_afiliado_id', $lote->id)
            ->whereIn('motivo', self::MOTIVOS_MORA)
            ->whereNotNull('periodo')
            ->get(['motivo', 'periodo'])
            ->groupBy('motivo')
            ->map(fn (Collection $grupo): array => $grupo->pluck('periodo')->map(fn ($p): string => (string) $p)->all())
            ->all();

        $desde = $multas->filter(fn (Multa $multa): bool => filled($multa->periodo))->max('periodo');
        $meses = array_column($this->multasQueCorresponden($periodos, $desde), 'periodo');
        $siguiente = 0;
        $monto = (float) Configuracion::get(Configuracion::MULTA_TRIMESTRAL, 50);
        $ciclo = $this->mesesParaMulta() + 1;

        foreach ($sinMes as $multa) {
            $generada = $multa->fecha_generacion->format('Y-m');
            $vale = $monto > 0 ? max(1, min(12, (int) round((float) $multa->monto / $monto))) : 1;
            $mes = null;
            $usados = 0;

            while ($usados < $vale && isset($meses[$siguiente]) && $meses[$siguiente] <= $generada) {
                $mes = $meses[$siguiente];
                $siguiente++;
                $usados++;
            }

            if ($mes === null) {
                $mes = $generada;
            } elseif ($usados < $vale) {
                // Vale por más multas de las que caben en la deuda actual: cubre también las siguientes.
                $mes = $this->sumarMeses($mes, $ciclo * ($vale - $usados));
                while (isset($meses[$siguiente]) && $meses[$siguiente] <= $mes) {
                    $siguiente++;
                }
            }

            while (in_array($mes, $ocupados[$multa->motivo] ?? [], true)) {
                $mes = $this->sumarMeses($mes, -1);
            }

            $ocupados[$multa->motivo][] = $mes;
            $multa->forceFill(['periodo' => $mes])->save();
        }
    }

    /**
     * Multas por mora vigentes del lote (pendientes o cobradas), de la regla actual y de la anterior.
     *
     * @return Collection<int, Multa>
     */
    private function multasDeMora(LoteAfiliado $lote): Collection
    {
        return Multa::query()
            ->where('lote_afiliado_id', $lote->id)
            ->whereIn('motivo', self::MOTIVOS_MORA)
            ->where('estado', '!=', Multa::ESTADO_ANULADA)
            ->orderBy('id')
            ->get();
    }

    /**
     * Mes de la multa por mora más reciente del lote: desde ahí se cuentan los meses de la siguiente.
     */
    private function ultimoMesConMulta(LoteAfiliado $lote): ?string
    {
        return $this->multasDeMora($lote)
            ->map(fn (Multa $multa): string => $this->mesDeMulta($multa))
            ->max();
    }

    /** Mes en que se aplicó la multa: su periodo o, si no lo tiene, el mes en que se generó. */
    private function mesDeMulta(Multa $multa): string
    {
        return filled($multa->periodo)
            ? (string) $multa->periodo
            : $multa->fecha_generacion->format('Y-m');
    }

    private function mesesParaMulta(): int
    {
        return max(1, (int) Configuracion::get(Configuracion::MESES_MULTA_TRIMESTRAL, 3));
    }

    /**
     * Texto del recibo único del año: el monto a cobrar es el acumulado (50 / 100 / 150).
     */
    private function descripcionMoraTramo(int $tramo, float $base, int $acumulado, string $rango): string
    {
        return $tramo >= 3
            ? "Mora anual Bs {$acumulado}: {$rango}"
            : "Mora trimestral Bs {$acumulado}: {$rango}";
    }

    private function sumarMeses(string $periodo, int $meses): string
    {
        return Carbon::createFromFormat('!Y-m', $periodo)->addMonthsNoOverflow($meses)->format('Y-m');
    }

    /**
     * Varias multas pendientes de Bs 50 del mismo año (regla anterior) → un solo recibo acumulado.
     */
    private function consolidarRecibosMoraPorAnio(LoteAfiliado $lote): void
    {
        $base = round((float) Configuracion::get(Configuracion::MULTA_TRIMESTRAL, 50), 2);
        $tope = round($base * 3, 2);

        $pendientes = Multa::query()
            ->where('lote_afiliado_id', $lote->id)
            ->where('motivo', Multa::MOTIVO_MORA_TRIMESTRAL)
            ->where('estado', Multa::ESTADO_PENDIENTE)
            ->whereRaw('ABS(saldo - monto) < 0.001')
            ->whereNotNull('periodo')
            ->orderBy('periodo')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Multa $m): string => substr((string) $m->periodo, 0, 4));

        foreach ($pendientes as $delAnio) {
            if ($delAnio->count() < 2) {
                continue;
            }

            $acumulado = min($tope, round((float) $delAnio->sum('monto'), 2));
            /** @var Multa $principal */
            $principal = $delAnio->last();
            $tramo = min(3, max(1, (int) round($acumulado / max(0.01, $base))));
            $rango = $this->rangoDesdeDescripcion((string) $principal->descripcion)
                ?? ((int) $principal->cuotas_vencidas.' meses sin pagar');

            $principal->update([
                'monto' => $acumulado,
                'saldo' => $acumulado,
                'gestion' => (int) substr((string) $principal->periodo, 0, 4),
                'descripcion' => $this->descripcionMoraTramo($tramo, $base, (int) round($acumulado), $rango),
            ]);

            foreach ($delAnio->where('id', '!=', $principal->id) as $extra) {
                $extra->update([
                    'estado' => Multa::ESTADO_ANULADA,
                    'saldo' => 0,
                    'descripcion' => trim((string) $extra->descripcion).' · consolidada en un solo recibo anual',
                ]);
            }
        }
    }

    /** Libera el índice único lote+motivo+periodo si quedó una multa anulada en ese mes. */
    private function liberarPeriodoMora(int $loteId, string $periodo, int $exceptoId): void
    {
        Multa::query()
            ->where('lote_afiliado_id', $loteId)
            ->where('motivo', Multa::MOTIVO_MORA_TRIMESTRAL)
            ->where('periodo', $periodo)
            ->where('id', '!=', $exceptoId)
            ->where('estado', Multa::ESTADO_ANULADA)
            ->update(['periodo' => null]);
    }

    /**
     * Un recibo por año: si hay uno pendiente sin abono, suma Bs 50 (hasta 150).
     * Si ya pagó el del año, abre otro recibo de Bs 50.
     *
     * @param  array{periodo: string, meses: list<string>, adeudados: int}  $mora
     */
    private function crearMoraSiFalta(LoteAfiliado $lote, array $mora, float $monto, ?User $usuario): int
    {
        $gestion = (int) substr($mora['periodo'], 0, 4);
        $base = round($monto, 2);
        $topeAnual = round($base * 3, 2);

        // Misma fila del mes de aplicación (reactivación tras anular).
        $mismaFila = Multa::query()
            ->where('lote_afiliado_id', $lote->id)
            ->where('motivo', Multa::MOTIVO_MORA_TRIMESTRAL)
            ->where('periodo', $mora['periodo'])
            ->first();

        if ($mismaFila && $mismaFila->estado !== Multa::ESTADO_ANULADA
            && abs((float) $mismaFila->monto - (float) $mismaFila->saldo) < 0.001
            && (float) $mismaFila->monto + 0.001 >= $base) {
            return 0;
        }

        $delAnio = Multa::query()
            ->where('lote_afiliado_id', $lote->id)
            ->where('motivo', Multa::MOTIVO_MORA_TRIMESTRAL)
            ->where('estado', Multa::ESTADO_PENDIENTE)
            ->where(function ($q) use ($gestion): void {
                $q->where('gestion', $gestion)
                    ->orWhere('periodo', 'like', sprintf('%04d-%%', $gestion));
            })
            ->orderByDesc('periodo')
            ->orderByDesc('id')
            ->get();

        // Abono parcial en el recibo del año: no se suma ni se abre otro hasta liquidar.
        if ($delAnio->contains(fn (Multa $m): bool => abs((float) ($m->saldo ?? 0) - (float) $m->monto) >= 0.001)) {
            return 0;
        }

        // Recibo abierto del mismo año sin abono → se suma aquí (50 → 100 → 150).
        $abierto = $delAnio->first(fn (Multa $m): bool => abs((float) ($m->saldo ?? 0) - (float) $m->monto) < 0.001);

        $cantidad = count($mora['meses']);
        $rango = (string) preg_replace('/ \(\d+ meses\)$/u', '', TextoPresentacion::rangoDePeriodos($mora['meses']));
        $baseMeses = ($cantidad === 1 ? '1 mes' : "{$cantidad} meses")." sin pagar ({$rango})";

        if ($abierto && (float) $abierto->monto + 0.001 < $topeAnual) {
            $acumulado = min($topeAnual, round((float) $abierto->monto + $base, 2));
            if (abs($acumulado - (float) $abierto->monto) < 0.001) {
                return 0;
            }

            $tramo = (int) round($acumulado / $base);
            $this->liberarPeriodoMora($lote->id, $mora['periodo'], (int) $abierto->id);
            $abierto->update([
                'periodo' => $mora['periodo'],
                'fecha_generacion' => $mora['periodo'].'-01',
                'gestion' => $gestion,
                'cuotas_vencidas' => $mora['adeudados'],
                'descripcion' => $this->descripcionMoraTramo($tramo, $base, (int) round($acumulado), $baseMeses),
                'monto' => $acumulado,
                'saldo' => $acumulado,
                'generada_por' => $usuario?->id ?? $abierto->generada_por,
            ]);

            return 1;
        }

        if ($abierto && (float) $abierto->monto + 0.001 >= $topeAnual) {
            // Año ya cerrado en Bs 150; este tramo no abre otro en el mismo año.
            return 0;
        }

        $datos = [
            'afiliado_id' => $lote->afiliado_id,
            'fecha_generacion' => $mora['periodo'].'-01',
            'gestion' => $gestion,
            'cuotas_vencidas' => $mora['adeudados'],
            'descripcion' => $this->descripcionMoraTramo(1, $base, (int) round($base), $baseMeses),
            'monto' => $base,
            'saldo' => $base,
            'estado' => Multa::ESTADO_PENDIENTE,
            'generada_por' => $usuario?->id,
        ];

        if ($mismaFila) {
            $mismaFila->update($datos);

            return 1;
        }

        Multa::query()->create($datos + [
            'lote_afiliado_id' => $lote->id,
            'motivo' => Multa::MOTIVO_MORA_TRIMESTRAL,
            'periodo' => $mora['periodo'],
        ]);

        return 1;
    }
}
