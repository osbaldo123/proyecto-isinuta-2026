<?php

namespace App\Http\Controllers;

use App\Enums\Permission as PermissionEnum;
use App\Models\Afiliado;
use App\Models\LoteAfiliado;
use App\Models\Multa;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Servicio;
use App\Services\CalculadorMultasService;
use App\Services\DeudaAfiliadoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DeudaAfiliadoService $deudaAfiliado,
        private readonly CalculadorMultasService $calculadorMultas,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $canViewAfiliados = $user?->hasPermission(PermissionEnum::VerAfiliados->value) ?? false;
        $esPresidente = $user?->isPresidente() === true && ! $user->isAdmin();
        $hoy = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();

        $resumen = [
            'afiliados_activos' => 0,
            'afiliados_cortados' => 0,
            'multas_pendientes' => 0,
            'pagos_hoy' => 0.0,
            'pagos_hoy_fecha' => $hoy,
            'pagos_mes' => 0.0,
            'afiliados_pagaron_mes' => 0,
            'afiliados_pendientes_pago' => 0,
            'cortados_origen' => 'corte',
        ];
        $alertasDeuda = [];
        $pagosRecientes = [];
        $pagosDelMes = [];
        $estadisticaAnual = $this->estadisticaAnualVacia();

        if ($canViewAfiliados) {
            $periodoMes = now()->format('Y-m');

            // Multas trimestrales que falten y cortes por mora de todo el padrón (como mucho cada 5 minutos).
            Cache::remember('dashboard.sync_mora.v3', 300, function (): true {
                $this->asignarServiciosOperativosFaltantes();
                $this->calculadorMultas->sincronizarMora();

                return true;
            });

            $pagosBase = Pago::query()->where('estado', Pago::ESTADO_EMITIDO);

            $conteoMes = $esPresidente
                ? $this->conteoAfiliadosPagoMes($inicioMes, $hoy, $periodoMes)
                : ['afiliados_pagaron_mes' => 0, 'afiliados_pendientes_pago' => 0];

            $cortadosEstado = (int) Afiliado::query()
                ->where(function ($query): void {
                    $query->where('estado', Afiliado::ESTADO_CORTADO)
                        ->orWhereHas('lotes', fn ($lote) => $lote->where('estado', Afiliado::ESTADO_CORTADO));
                })
                ->count();
            $sinPagoMes = $this->afiliadosSinPagoEnRango($inicioMes, $hoy);
            $cierre = $this->cierreDePagos($hoy);

            $resumen = [
                'afiliados_activos' => Afiliado::query()->where('estado', Afiliado::ESTADO_ACTIVO)->count(),
                'afiliados_cortados' => $cortadosEstado > 0 ? $cortadosEstado : $sinPagoMes,
                'cortados_origen' => $cortadosEstado > 0 ? 'corte' : 'sin_pago',
                'multas_pendientes' => Multa::query()->where('estado', Multa::ESTADO_PENDIENTE)->count(),
                'pagos_hoy' => $cierre['monto'],
                'pagos_hoy_fecha' => $cierre['fecha'],
                'pagos_mes' => round((float) (clone $pagosBase)
                    ->whereDate('fecha_pago', '>=', $inicioMes)
                    ->whereDate('fecha_pago', '<=', $hoy)
                    ->sum('monto_total'), 2),
                'afiliados_pagaron_mes' => $conteoMes['afiliados_pagaron_mes'],
                'afiliados_pendientes_pago' => $conteoMes['afiliados_pendientes_pago'],
            ];

            $estadisticaAnual = $this->estadisticaAnual((int) now()->format('Y'));

            $alertasDeuda = Cache::remember('dashboard.alertas_deuda.v3', 180, function (): array {
                $afiliados = Afiliado::query()
                    ->with(['lotes.otb:id,nombre', 'lotes.serviciosActivos'])
                    ->where('estado', Afiliado::ESTADO_ACTIVO)
                    ->orderBy('ci')
                    ->get();

                return $afiliados
                    ->map(function (Afiliado $afiliado): ?array {
                        $lote = $afiliado->lotes
                            ->first(fn ($item) => $item->estado !== Afiliado::ESTADO_BAJA)
                            ?? $afiliado->lotes->first();

                        $afiliadoConLote = $lote ? $afiliado->conLote($lote) : $afiliado;
                        $estado = $this->deudaAfiliado->calcularDeudaResumen($afiliadoConLote);

                        if ($estado['total_deuda'] <= 0) {
                            return null;
                        }

                        return [
                            'id' => $afiliado->id,
                            'lote_id' => $lote?->id,
                            'ci' => $afiliado->ci,
                            'nombre_completo' => $afiliado->nombre_completo,
                            'otb' => $lote?->otb?->nombre ?? $afiliado->otb?->nombre,
                            'total_deuda' => $estado['total_deuda'],
                        ];
                    })
                    ->filter()
                    ->sortByDesc('total_deuda')
                    ->take(5)
                    ->values()
                    ->all();
            });

            if ($esPresidente) {
                $pagosDelMes = $this->afiliadosQuePagaronEnMes($inicioMes, $hoy);
            } else {
                $pagosRecientes = Pago::query()
                    ->with(['afiliado:id,nombres,apellidos,ci', 'detalles'])
                    ->where('estado', Pago::ESTADO_EMITIDO)
                    ->latest('fecha_pago')
                    ->latest('id')
                    ->limit(5)
                    ->get()
                    ->map(fn (Pago $pago): array => $this->mapPagoReciente($pago))
                    ->all();
            }
        } elseif ($user?->afiliado_id) {
            $afiliado = Afiliado::query()->find($user->afiliado_id);

            if ($afiliado) {
                $estado = $this->deudaAfiliado->calcular($afiliado);
                $hoy = now()->toDateString();
                $inicioMes = now()->startOfMonth()->toDateString();

                $pagosAfiliado = Pago::query()
                    ->where('afiliado_id', $afiliado->id)
                    ->where('estado', Pago::ESTADO_EMITIDO);

                $cierreAfiliado = $this->cierreDePagos($hoy, $afiliado->id);

                $resumen = [
                    'afiliados_activos' => 0,
                    'afiliados_cortados' => 0,
                    'cortados_origen' => 'corte',
                    'multas_pendientes' => count($estado['multas_pendientes']),
                    'pagos_hoy' => $cierreAfiliado['monto'],
                    'pagos_hoy_fecha' => $cierreAfiliado['fecha'],
                    'pagos_mes' => round((float) (clone $pagosAfiliado)
                        ->whereDate('fecha_pago', '>=', $inicioMes)
                        ->whereDate('fecha_pago', '<=', $hoy)
                        ->sum('monto_total'), 2),
                    'afiliados_pagaron_mes' => 0,
                    'afiliados_pendientes_pago' => 0,
                ];

                $pagosRecientes = collect($estado['pagos_recientes'])
                    ->map(fn (array $pago): array => [
                        'id' => $pago['id'],
                        'numero_recibo' => $pago['numero_recibo'],
                        'monto_total' => $pago['monto_total'],
                        'fecha_pago' => $pago['fecha_pago'],
                        'afiliado' => $afiliado->nombre_completo,
                        'conceptos' => $pago['conceptos'],
                    ])
                    ->all();
            }
        }

        return Inertia::render('Dashboard', [
            'institucion' => config('app.name'),
            'usuario' => $user?->name ?? 'Usuario',
            'resumen' => $resumen,
            'alertas_deuda' => $alertasDeuda,
            'pagos_recientes' => $pagosRecientes,
            'es_presidente' => $esPresidente,
            'mes_pago_label' => $this->nombreMesActual(),
            'pagos_del_mes' => $pagosDelMes,
            'estadistica_anual' => $estadisticaAnual,
            'enlaces_resumen' => [
                'activos' => '/afiliados?estado=activo',
                'cortados' => ($resumen['cortados_origen'] ?? 'corte') === 'sin_pago'
                    ? '/afiliados?sin_pago=1'
                    : '/afiliados?estado=cortado',
                'pagos_hoy' => '/pagos?fecha_desde='.($resumen['pagos_hoy_fecha'] ?? $hoy).'&fecha_hasta='.($resumen['pagos_hoy_fecha'] ?? $hoy),
                'pagos_mes' => '/pagos?fecha_desde='.$inicioMes.'&fecha_hasta='.$hoy,
            ],
        ]);
    }

    /**
     * @return array{
     *   anio: int,
     *   total: float,
     *   recibos: int,
     *   promedio_mensual: float,
     *   mejor_mes: ?array{mes: int, label: string, monto: float, recibos: int},
     *   meses: array<int, array{mes: int, label: string, monto: float, recibos: int}>
     * }
     */
    private function estadisticaAnual(int $anio): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $filas = Pago::query()
                ->select([
                    DB::raw('CAST(strftime("%m", fecha_pago) AS INTEGER) as mes'),
                    DB::raw('SUM(monto_total) as monto'),
                    DB::raw('COUNT(*) as recibos'),
                ])
                ->where('estado', Pago::ESTADO_EMITIDO)
                ->whereYear('fecha_pago', $anio)
                ->groupBy(DB::raw('CAST(strftime("%m", fecha_pago) AS INTEGER)'))
                ->get()
                ->keyBy(fn ($fila): int => (int) $fila->mes);
        } else {
            $filas = Pago::query()
                ->select([
                    DB::raw('MONTH(fecha_pago) as mes'),
                    DB::raw('SUM(monto_total) as monto'),
                    DB::raw('COUNT(*) as recibos'),
                ])
                ->where('estado', Pago::ESTADO_EMITIDO)
                ->whereYear('fecha_pago', $anio)
                ->groupBy(DB::raw('MONTH(fecha_pago)'))
                ->get()
                ->keyBy(fn ($fila): int => (int) $fila->mes);
        }

        $labels = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        $meses = [];
        $total = 0.0;
        $recibos = 0;
        $mejorMes = null;

        for ($mes = 1; $mes <= 12; $mes++) {
            $fila = $filas->get($mes);
            $monto = round((float) ($fila->monto ?? 0), 2);
            $cant = (int) ($fila->recibos ?? 0);
            $item = [
                'mes' => $mes,
                'label' => $labels[$mes],
                'monto' => $monto,
                'recibos' => $cant,
            ];
            $meses[] = $item;
            $total += $monto;
            $recibos += $cant;

            if ($mejorMes === null || $monto > $mejorMes['monto']) {
                $mejorMes = $item;
            }
        }

        if ($mejorMes !== null && $mejorMes['monto'] <= 0) {
            $mejorMes = null;
        }

        $mesesConDatos = collect($meses)->filter(fn (array $m): bool => $m['monto'] > 0)->count();

        return [
            'anio' => $anio,
            'total' => round($total, 2),
            'recibos' => $recibos,
            'promedio_mensual' => $mesesConDatos > 0 ? round($total / $mesesConDatos, 2) : 0.0,
            'mejor_mes' => $mejorMes,
            'meses' => $meses,
        ];
    }

    /**
     * @return array{
     *   anio: int,
     *   total: float,
     *   recibos: int,
     *   promedio_mensual: float,
     *   mejor_mes: null,
     *   meses: array<int, array{mes: int, label: string, monto: float, recibos: int}>
     * }
     */
    private function estadisticaAnualVacia(): array
    {
        $labels = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        $meses = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $meses[] = [
                'mes' => $mes,
                'label' => $labels[$mes],
                'monto' => 0.0,
                'recibos' => 0,
            ];
        }

        return [
            'anio' => (int) now()->format('Y'),
            'total' => 0.0,
            'recibos' => 0,
            'promedio_mensual' => 0.0,
            'mejor_mes' => null,
            'meses' => $meses,
        ];
    }

    /**
     * @return array{afiliados_pagaron_mes: int, afiliados_pendientes_pago: int}
     */
    private function conteoAfiliadosPagoMes(string $desde, string $hasta, string $periodoMes): array
    {
        $pagaronMes = Pago::query()
            ->where('estado', Pago::ESTADO_EMITIDO)
            ->whereDate('fecha_pago', '>=', $desde)
            ->whereDate('fecha_pago', '<=', $hasta)
            ->pluck('afiliado_id')
            ->unique()
            ->count();

        $idsConCuotaDelMes = Pago::query()
            ->where('estado', Pago::ESTADO_EMITIDO)
            ->whereHas('detalles', function ($query) use ($periodoMes): void {
                $query->whereIn('concepto', PagoDetalle::conceptosServicioMensual())
                    ->where('periodo', $periodoMes);
            })
            ->pluck('afiliado_id')
            ->unique()
            ->values()
            ->all();

        $pendientes = (int) Afiliado::query()
            ->where('estado', Afiliado::ESTADO_ACTIVO)
            ->when(
                $idsConCuotaDelMes !== [],
                fn ($query) => $query->whereNotIn('id', $idsConCuotaDelMes),
            )
            ->count();

        return [
            'afiliados_pagaron_mes' => $pagaronMes,
            'afiliados_pendientes_pago' => $pendientes,
        ];
    }

    /**
     * Afiliados que emitieron al menos un pago en el rango (mes corriente).
     *
     * @return array<int, array<string, mixed>>
     */
    private function afiliadosQuePagaronEnMes(string $desde, string $hasta): array
    {
        $filas = Pago::query()
            ->select([
                'afiliado_id',
                DB::raw('SUM(monto_total) as total_pagado'),
                DB::raw('COUNT(*) as recibos'),
                DB::raw('MAX(fecha_pago) as ultima_fecha'),
            ])
            ->where('estado', Pago::ESTADO_EMITIDO)
            ->whereDate('fecha_pago', '>=', $desde)
            ->whereDate('fecha_pago', '<=', $hasta)
            ->groupBy('afiliado_id')
            ->orderByDesc('ultima_fecha')
            ->limit(12)
            ->get();

        if ($filas->isEmpty()) {
            return [];
        }

        $afiliados = Afiliado::query()
            ->with('lotes.otb:id,nombre')
            ->whereIn('id', $filas->pluck('afiliado_id'))
            ->get()
            ->keyBy('id');

        return $filas
            ->map(function ($fila) use ($afiliados): ?array {
                $afiliado = $afiliados->get($fila->afiliado_id);

                if (! $afiliado) {
                    return null;
                }

                $lote = $afiliado->lotes->first();

                return [
                    'id' => $afiliado->id,
                    'lote_id' => $lote?->id,
                    'ci' => $afiliado->ci,
                    'nombre_completo' => $afiliado->nombre_completo,
                    'otb' => $afiliado->otb?->nombre,
                    'total_pagado' => round((float) $fila->total_pagado, 2),
                    'recibos' => (int) $fila->recibos,
                    'ultima_fecha' => Carbon::parse($fila->ultima_fecha)->format('d/m/Y'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function nombreMesActual(): string
    {
        $meses = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        return $meses[(int) now()->format('n')] ?? now()->translatedFormat('F');
    }

    /**
     * @return array{monto: float, fecha: string}
     */
    private function cierreDePagos(string $hoy, ?int $afiliadoId = null): array
    {
        $base = Pago::query()->where('estado', Pago::ESTADO_EMITIDO);
        if ($afiliadoId !== null) {
            $base->where('afiliado_id', $afiliadoId);
        }

        $montoHoy = round((float) (clone $base)->whereDate('fecha_pago', $hoy)->sum('monto_total'), 2);
        if ($montoHoy > 0) {
            return ['monto' => $montoHoy, 'fecha' => $hoy];
        }

        $ultima = (clone $base)->whereDate('fecha_pago', '<=', $hoy)->max('fecha_pago');
        if (! $ultima) {
            return ['monto' => 0.0, 'fecha' => $hoy];
        }

        $fecha = Carbon::parse($ultima)->toDateString();

        return [
            'monto' => round((float) (clone $base)->whereDate('fecha_pago', $fecha)->sum('monto_total'), 2),
            'fecha' => $fecha,
        ];
    }

    private function afiliadosSinPagoEnRango(string $desde, string $hasta): int
    {
        $idsPagaron = Pago::query()
            ->where('estado', Pago::ESTADO_EMITIDO)
            ->whereDate('fecha_pago', '>=', $desde)
            ->whereDate('fecha_pago', '<=', $hasta)
            ->distinct()
            ->pluck('afiliado_id');

        return (int) Afiliado::query()
            ->where('estado', Afiliado::ESTADO_ACTIVO)
            ->when($idsPagaron->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $idsPagaron))
            ->count();
    }

    private function asignarServiciosOperativosFaltantes(): void
    {
        $servicios = Servicio::query()
            ->where('activo', true)
            ->whereIn('tipo', Servicio::TIPOS_BASE)
            ->get(['id']);

        if ($servicios->isEmpty()) {
            return;
        }

        $yaAsignados = DB::table('lote_servicio')->distinct()->pluck('lote_afiliado_id');
        $faltantes = LoteAfiliado::query()
            ->where('estado', '!=', Afiliado::ESTADO_BAJA)
            ->when($yaAsignados->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $yaAsignados))
            ->pluck('id');

        if ($faltantes->isEmpty()) {
            return;
        }

        $ahora = now();
        $filas = [];
        foreach ($faltantes as $loteId) {
            foreach ($servicios as $servicio) {
                $filas[] = [
                    'lote_afiliado_id' => $loteId,
                    'servicio_id' => $servicio->id,
                    'tarifa_acordada' => null,
                    'fecha_inicio' => $ahora->toDateString(),
                    'fecha_fin' => null,
                    'fecha_creacion' => $ahora,
                    'fecha_actualizacion' => $ahora,
                ];
            }
        }

        foreach (array_chunk($filas, 200) as $chunk) {
            DB::table('lote_servicio')->insertOrIgnore($chunk);
        }
    }

    private function mapPagoReciente(Pago $pago): array
    {
        return [
            'id' => $pago->id,
            'numero_recibo' => $pago->numero_recibo,
            'monto_total' => (float) $pago->monto_total,
            'fecha_pago' => $pago->fecha_pago->format('d/m/Y'),
            'afiliado' => $pago->afiliado?->nombre_completo ?? '—',
            'conceptos' => $pago->detalles
                ->map(fn ($detalle): string => trim($detalle->descripcion ?: $detalle->concepto))
                ->filter()
                ->values()
                ->all(),
        ];
    }
}
