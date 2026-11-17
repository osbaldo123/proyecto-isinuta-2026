<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAfiliadoRequest;
use App\Http\Requests\UpdateAfiliadoRequest;
use App\Models\Afiliado;
use App\Models\Categoria;
use App\Models\LoteAfiliado;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Servicio;
use App\Models\TipoAfiliacion;
use App\Models\Otb;
use App\Services\ActivityLogger;
use App\Services\AfiliadoUserService;
use App\Services\CalculadorMultasService;
use App\Services\TransferenciaLoteService;
use App\Services\EmitirReciboAfiliacionService;
use App\Services\DeudaAfiliadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AfiliadosController extends Controller
{
    public function __construct(
        private readonly DeudaAfiliadoService $deudaAfiliado,
        private readonly AfiliadoUserService $afiliadoUser,
        private readonly ActivityLogger $activityLogger,
        private readonly EmitirReciboAfiliacionService $reciboAfiliacion,
        private readonly CalculadorMultasService $calculadorMultas,
    ) {}

    public function index(Request $request): Response
    {
        $busqueda = trim((string) $request->query('q', ''));
        $estado = trim((string) $request->query('estado', ''));
        $otbId = $request->query('otb_id');
        $sinPago = $request->boolean('sin_pago');

        $consulta = LoteAfiliado::query()
            ->when($busqueda !== '', function ($query) use ($busqueda): void {
                $termino = mb_strtolower($busqueda);

                $query->where(function ($q) use ($busqueda, $termino): void {
                    $q->where('numero_lote', 'like', "%{$busqueda}%")
                        ->orWhere('telefono', 'like', "%{$busqueda}%")
                        ->orWhere('direccion', 'like', "%{$busqueda}%")
                        ->orWhere('estado', 'like', "%{$termino}%")
                        ->orWhereHas('afiliado', function ($afiliado) use ($busqueda, $termino): void {
                            $afiliado->where('ci', 'like', "%{$busqueda}%")
                                ->orWhere('nombres', 'like', "%{$busqueda}%")
                                ->orWhere('apellidos', 'like', "%{$busqueda}%")
                                ->orWhere('direccion', 'like', "%{$busqueda}%")
                                ->orWhere('telefono', 'like', "%{$busqueda}%")
                                ->orWhere('estado', 'like', "%{$termino}%")
                                ->orWhereRaw("CONCAT(nombres, ' ', apellidos) like ?", ["%{$busqueda}%"]);
                        });
                });
            })
            ->when(filled($otbId), fn ($query) => $query->where('otb_id', (int) $otbId))
            ->when($sinPago, function ($query): void {
                $inicioMes = now()->startOfMonth()->toDateString();
                $hoy = now()->toDateString();
                $idsPagaron = Pago::query()
                    ->where('estado', Pago::ESTADO_EMITIDO)
                    ->whereDate('fecha_pago', '>=', $inicioMes)
                    ->whereDate('fecha_pago', '<=', $hoy)
                    ->distinct()
                    ->pluck('afiliado_id');

                $query->whereHas('afiliado', function ($afiliado) use ($idsPagaron): void {
                    $afiliado->where('estado', Afiliado::ESTADO_ACTIVO)
                        ->when($idsPagaron->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $idsPagaron));
                });
            })
            ->when(! $sinPago && in_array($estado, [Afiliado::ESTADO_ACTIVO, Afiliado::ESTADO_CORTADO, Afiliado::ESTADO_BAJA], true), function ($query) use ($estado): void {
                $query->where(function ($q) use ($estado): void {
                    $q->where('estado', $estado)
                        ->orWhereHas('afiliado', fn ($afiliado) => $afiliado->where('estado', $estado));
                });
            });

        $totalAfiliados = (clone $consulta)->distinct()->count('lote_afiliados.afiliado_id');

        $pagina = $consulta
            ->with(['afiliado', 'otb:id,nombre'])
            ->orderByRaw('lote_afiliados.numero_lote + 0')
            ->orderBy('lote_afiliados.numero_lote')
            ->paginate(15)
            ->withQueryString();

        $grupos = [];
        foreach ($pagina->getCollection() as $lote) {
            $afiliado = $lote->afiliado;
            $otbNombre = $lote->getRelationValue('otb')?->nombre;

            $propiedad = [
                'id' => $lote->id,
                'otb_id' => $lote->otb_id ? (int) $lote->otb_id : null,
                'otb' => $otbNombre,
                'numero_lote' => $lote->numero_lote,
                'direccion' => $lote->direccion ?: $afiliado->direccion,
                'telefono' => $lote->telefono,
                'estado' => $lote->estado,
                'fecha_afiliacion' => optional($lote->fecha_afiliacion)?->format('Y-m-d'),
            ];

            $ultimo = array_key_last($grupos);
            if ($ultimo !== null && $grupos[$ultimo]['id'] === $afiliado->id) {
                $grupos[$ultimo]['propiedades'][] = $propiedad;

                continue;
            }

            $grupos[] = [
                'id' => $afiliado->id,
                'nombre_completo' => $afiliado->nombre_completo,
                'ci' => $afiliado->ci,
                'telefono' => $afiliado->telefono,
                'propiedades' => [$propiedad],
            ];
        }

        $afiliados = $pagina->toArray();
        $afiliados['data'] = $grupos;
        $afiliados['total'] = $totalAfiliados;

        return Inertia::render('afiliados/Index', [
            'afiliados' => $afiliados,
            'otbs' => Otb::query()->where('activa', true)->orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => [
                'q' => $busqueda,
                'estado' => $sinPago ? '' : $estado,
                'sin_pago' => $sinPago,
                'otb_id' => filled($otbId) ? (int) $otbId : null,
            ],
        ]);
    }

    /**
     * Autocomplete: una opción por lote (id = lote_afiliado_id).
     *
     * @return JsonResponse
     */
    public function buscar(Request $request)
    {
        $busqueda = trim((string) $request->query('q', ''));

        $query = LoteAfiliado::query()
            ->with(['afiliado', 'otb:id,nombre'])
            ->where('estado', '!=', Afiliado::ESTADO_BAJA)
            ->whereHas('afiliado', fn ($q) => $q->where('estado', '!=', Afiliado::ESTADO_BAJA));

        if ($busqueda !== '') {
            $query->where(function ($q) use ($busqueda): void {
                $q->where('numero_lote', 'like', "%{$busqueda}%")
                    ->orWhereHas('afiliado', function ($a) use ($busqueda): void {
                        $a->where('ci', 'like', "%{$busqueda}%")
                            ->orWhere('nombres', 'like', "%{$busqueda}%")
                            ->orWhere('apellidos', 'like', "%{$busqueda}%")
                            ->orWhereRaw("CONCAT(nombres, ' ', apellidos) like ?", ["%{$busqueda}%"]);
                    });
            });
        }

        $items = $query
            ->orderBy('afiliado_id')
            ->orderByRaw('CAST(numero_lote AS UNSIGNED), numero_lote')
            ->limit(30)
            ->get()
            ->map(function (LoteAfiliado $lote): array {
                $afiliado = $lote->afiliado->conLote($lote);

                return [
                    'id' => $lote->id,
                    'afiliado_id' => $afiliado->id,
                    'lote_afiliado_id' => $lote->id,
                    'nombre_completo' => $afiliado->nombre_completo,
                    'etiqueta' => $afiliado->etiquetaSelector(),
                    'label' => $afiliado->etiquetaSelector(),
                    'numero_lote' => $lote->numero_lote,
                    'otb' => $lote->otb?->nombre,
                    'ci' => $afiliado->ci,
                ];
            });

        return response()->json(['data' => $items]);
    }

    /**
     * @return JsonResponse
     */
    public function siguienteLote(Request $request)
    {
        $ci = preg_replace('/\D/', '', (string) $request->query('ci', ''));

        if (strlen($ci) !== 8) {
            return response()->json([
                'existe' => false,
                'siguiente_lote' => LoteAfiliado::sugerirNumeroLote(),
                'propiedades' => 0,
                'nombre_completo' => null,
            ]);
        }

        $persona = Afiliado::query()
            ->with('lotes')
            ->where('ci', $ci)
            ->orderBy('id')
            ->first();

        $lotes = $persona?->lotes ?? collect();
        $siguienteLote = LoteAfiliado::sugerirNumeroLote();

        return response()->json([
            'existe' => $persona !== null,
            'siguiente_lote' => $siguienteLote,
            'propiedades' => $lotes->count(),
            'nombre_completo' => $persona?->nombre_completo,
            'nombres' => $persona?->nombres,
            'apellidos' => $persona?->apellidos,
            'telefono' => $lotes->sortByDesc('id')->first()?->telefono ?: $persona?->telefono,
            'primer_id' => $persona?->id,
            'lotes_usados' => $lotes->pluck('numero_lote')->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $prefijoNuevoLote = null;
        $numeroLoteSugerido = LoteAfiliado::sugerirNumeroLote();
        $desdeId = (int) $request->query('desde', 0);

        if ($desdeId > 0) {
            [$base, $loteOrigen] = $this->resolverAfiliadoYLote($desdeId, false);

            if ($base) {
                $hermanos = $base->lotes()->orderByRaw('CAST(numero_lote AS UNSIGNED), numero_lote')->get(['numero_lote']);

                $prefijoNuevoLote = [
                    'desde_id' => $base->id,
                    'nombres' => $base->nombres,
                    'apellidos' => $base->apellidos,
                    'ci' => $base->ci,
                    'telefono' => $loteOrigen?->telefono ?: $base->telefono,
                    'nombre_completo' => $base->nombre_completo,
                    'numero_lote' => $numeroLoteSugerido,
                    'lotes_usados' => $hermanos->pluck('numero_lote')->values()->all(),
                    'propiedades' => $hermanos->count(),
                ];
            }
        }

        return Inertia::render('afiliados/Create', [
            ...$this->formData(),
            'numero_lote_sugerido' => $numeroLoteSugerido,
            'prefijo_nuevo_lote' => $prefijoNuevoLote,
        ]);
    }

    public function store(StoreAfiliadoRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        // Alta: siempre Activo. Cortado/Activo por mora anual lo aplica el sistema después.
        $validated['estado'] = Afiliado::ESTADO_ACTIVO;

        $afiliado = null;
        $lote = null;
        $credenciales = null;
        $avisoUsuario = null;

        try {
            DB::transaction(function () use ($validated, &$afiliado, &$lote, $request): void {
                $afiliado = Afiliado::query()
                    ->where('ci', $validated['ci'])
                    ->orderBy('id')
                    ->first();

                if ($afiliado) {
                    $afiliado->update([
                        'nombres' => $validated['nombres'],
                        'apellidos' => $validated['apellidos'],
                        'direccion' => $validated['direccion'] ?? $afiliado->direccion,
                    ]);
                } else {
                    $afiliado = Afiliado::create([
                        'nombres' => $validated['nombres'],
                        'apellidos' => $validated['apellidos'],
                        'ci' => $validated['ci'],
                        'telefono' => $validated['telefono'] ?? null,
                        'direccion' => $validated['direccion'] ?? null,
                        'estado' => Afiliado::ESTADO_ACTIVO,
                    ]);
                }

                $lote = $afiliado->sincronizarLotePrincipal([
                    'numero_lote' => $validated['numero_lote'],
                    'otb_id' => $validated['otb_id'],
                    'direccion' => $validated['direccion'] ?? null,
                    'fecha_afiliacion' => $validated['fecha_afiliacion'],
                    'forma_pago_afiliacion' => $validated['forma_pago_afiliacion'],
                    'monto_afiliacion' => $validated['monto_afiliacion'],
                    'monto_afiliacion_agua' => $validated['monto_afiliacion_agua'],
                    'monto_afiliacion_alcantarillado' => $validated['monto_afiliacion_alcantarillado'],
                    'saldo_afiliacion_agua' => $validated['saldo_afiliacion_agua'] ?? 0,
                    'saldo_afiliacion_alcantarillado' => $validated['saldo_afiliacion_alcantarillado'] ?? 0,
                    'saldo_afiliacion' => $validated['saldo_afiliacion'] ?? 0,
                    'estado' => Afiliado::ESTADO_ACTIVO,
                    'observaciones' => $validated['observaciones'] ?? null,
                    'categoria_id' => $validated['categoria_id'],
                    'telefono' => $validated['telefono'] ?? null,
                ]);

                $afiliado->refrescarTelefonoDesdeLotes();

                $lote->load('categoria');
                $validated = $this->alinearServiciosYAfiliacionesBase($validated);
                $lote->sincronizarTiposAfiliacion(
                    $validated['tipos_afiliacion'] ?? [],
                    $this->montosPorTipoAfiliacion($validated),
                );
                $lote->sincronizarServicios($validated['servicios'] ?? [], $validated['fecha_afiliacion']);

                // sincronizarServicios puede poner montos en 0 si aún no hay servicios
                // catalogados; reaplicar los montos validados del alta.
                $lote->update([
                    'monto_afiliacion_agua' => (float) ($validated['monto_afiliacion_agua'] ?? 0),
                    'monto_afiliacion_alcantarillado' => (float) ($validated['monto_afiliacion_alcantarillado'] ?? 0),
                    'monto_afiliacion' => (float) ($validated['monto_afiliacion'] ?? 0),
                    'saldo_afiliacion_agua' => (float) ($validated['saldo_afiliacion_agua'] ?? 0),
                    'saldo_afiliacion_alcantarillado' => (float) ($validated['saldo_afiliacion_alcantarillado'] ?? 0),
                    'saldo_afiliacion' => (float) ($validated['saldo_afiliacion'] ?? 0),
                ]);

                $this->reciboAfiliacion->emitirSiCorresponde(
                    $afiliado,
                    $lote->fresh(['categoria']),
                    $request->user(),
                );
            });
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No se pudo registrar el afiliado. Verifique los datos e intente de nuevo.');
        }

        try {
            if (config('sistema.usuarios_afiliados') && $afiliado && ! $afiliado->user) {
                $credenciales = $this->afiliadoUser->createForAfiliado($afiliado);
            }
        } catch (\Throwable $e) {
            report($e);
            $avisoUsuario = 'El afiliado se guardó, pero no se pudo crear su usuario de consulta.';
        }

        $mensaje = 'Afiliado creado correctamente.';

        if ($credenciales) {
            $mensaje .= ' Se generó su usuario de consulta (acceso por CI).';
        }

        if ($avisoUsuario) {
            $mensaje .= ' '.$avisoUsuario;
        }

        $this->activityLogger->log(
            $request->user(),
            'Afiliados',
            "Alta de afiliado: {$validated['nombres']} {$validated['apellidos']}",
        );

        return redirect()
            ->route('afiliados.show', $lote?->id ?? $afiliado->id)
            ->with('success', $mensaje);
    }

    public function show(int $id): Response
    {
        [$afiliado, $lote] = $this->resolverAfiliadoYLote($id);

        $lote?->load(['otb', 'serviciosActivos', 'categoria']);

        $estado = $this->deudaAfiliado->calcular($afiliado);

        $propiedades = $afiliado->lotes()
            ->with('otb:id,nombre')
            ->get()
            ->map(fn (LoteAfiliado $propiedad): array => [
                'id' => $propiedad->id,
                'numero_lote' => $propiedad->numero_lote,
                'otb' => $propiedad->otb?->nombre,
                'direccion' => $propiedad->direccion,
                'estado' => $propiedad->estado,
                'activa' => $lote !== null && $propiedad->id === $lote->id,
            ])
            ->values()
            ->all();

        return Inertia::render('afiliados/Show', [
            'afiliado' => [
                'id' => $lote?->id ?? $afiliado->id,
                'afiliado_id' => $afiliado->id,
                'nombre_completo' => $afiliado->nombre_completo,
                'nombres' => $afiliado->nombres,
                'apellidos' => $afiliado->apellidos,
                'ci' => $afiliado->ci,
                'telefono' => $lote?->telefono ?: $afiliado->telefono,
                'direccion' => $lote?->direccion ?? $afiliado->direccion,
                'numero_lote' => $lote?->numero_lote,
                'otb' => $lote?->otb?->nombre,
                'fecha_afiliacion' => optional($lote?->fecha_afiliacion)?->format('d/m/Y'),
                'forma_pago_afiliacion' => $lote?->forma_pago_afiliacion,
                'monto_afiliacion' => (float) ($lote?->monto_afiliacion ?? 0),
                'monto_afiliacion_agua' => (float) ($lote?->monto_afiliacion_agua ?? 0),
                'monto_afiliacion_alcantarillado' => (float) ($lote?->monto_afiliacion_alcantarillado ?? 0),
                'saldo_afiliacion_agua' => (float) ($lote?->saldo_afiliacion_agua ?? 0),
                'saldo_afiliacion_alcantarillado' => (float) ($lote?->saldo_afiliacion_alcantarillado ?? 0),
                'saldo_afiliacion' => (float) ($lote?->saldo_afiliacion ?? 0),
                'categoria_id' => $lote?->categoria_id,
                'categoria_nombre' => $lote?->categoria?->nombre ?? LoteAfiliado::labelCategoria($lote?->categoria_propiedad),
                'tiene_agua' => (bool) ($lote?->tieneServicioAgua()),
                'tiene_alcantarillado' => (bool) ($lote?->tieneServicioAlcantarillado()),
                'tarifa_agua' => ($lote?->tieneServicioAgua() && $lote?->categoria)
                    ? (float) $lote->categoria->tarifa_agua
                    : null,
                'tarifa_alcantarillado' => ($lote?->tieneServicioAlcantarillado() && $lote?->categoria)
                    ? (float) $lote->categoria->tarifa_alcantarillado
                    : null,
                'monto_categorizado' => $lote
                    ? $lote->montoMensualPorServiciosContratados()
                    : null,
                'estado' => $lote?->estado ?? $afiliado->estado,
                'observaciones' => $lote?->observaciones,
                'servicios' => ($lote?->serviciosActivos ?? collect())
                    ->map(fn (Servicio $servicio): array => [
                        'id' => $servicio->id,
                        'codigo' => $servicio->codigo,
                        'nombre' => $servicio->nombre,
                        'tarifa' => (float) ($servicio->pivot->tarifa_acordada ?? $servicio->tarifa_mensual),
                    ])
                    ->values()
                    ->all(),
            ],
            'propiedades' => $propiedades,
            'historial_titulares' => $lote ? app(TransferenciaLoteService::class)->historial($lote) : [],
            'resumen' => [
                'total_deuda' => $estado['total_deuda'],
                'total_cuotas_pendientes' => $estado['total_cuotas_pendientes'],
                'total_multas_pendientes' => $estado['total_multas_pendientes'],
                'meses_pagados' => $estado['meses_pagados'],
                'meses_esperados' => $estado['meses_esperados'],
            ],
        ]);
    }

    public function edit(int $id): Response
    {
        [$afiliado, $lote] = $this->resolverAfiliadoYLote($id);
        $lote?->load(['servicios', 'categoria']);

        return Inertia::render('afiliados/Edit', [
            ...$this->formData(),
            'afiliado' => [
                'id' => $lote?->id ?? $afiliado->id,
                'afiliado_id' => $afiliado->id,
                'nombres' => $afiliado->nombres,
                'apellidos' => $afiliado->apellidos,
                'ci' => $afiliado->ci,
                'telefono' => $lote?->telefono ?: $afiliado->telefono,
                'cantidad_lotes' => $afiliado->lotes()->count(),
                'direccion' => $lote?->direccion ?? $afiliado->direccion,
                'numero_lote' => $lote?->numero_lote,
                'otb_id' => $lote?->otb_id,
                'fecha_afiliacion' => optional($lote?->fecha_afiliacion)?->format('Y-m-d'),
                'forma_pago_afiliacion' => $lote?->forma_pago_afiliacion,
                'monto_afiliacion' => (float) ($lote?->monto_afiliacion ?? 0),
                'monto_afiliacion_agua' => (float) ($lote?->monto_afiliacion_agua ?? 0),
                'monto_afiliacion_alcantarillado' => (float) ($lote?->monto_afiliacion_alcantarillado ?? 0),
                'saldo_afiliacion_agua' => (float) ($lote?->saldo_afiliacion_agua ?? 0),
                'saldo_afiliacion_alcantarillado' => (float) ($lote?->saldo_afiliacion_alcantarillado ?? 0),
                'saldo_afiliacion' => (float) ($lote?->saldo_afiliacion ?? 0),
                'categoria_id' => $lote?->categoria_id ?? Categoria::query()->where('slug', Categoria::SLUG_DOMICILIARIA)->value('id'),
                'estado' => $lote?->estado ?? $afiliado->estado,
                'observaciones' => $lote?->observaciones,
                'servicios' => $lote?->servicios->pluck('id')->all() ?? [],
                'tipos_afiliacion' => $lote?->tiposAfiliacion()->pluck('tipos_afiliacion.id')->all() ?? [],
            ],
        ]);
    }

    public function update(UpdateAfiliadoRequest $request, int $id): RedirectResponse
    {
        [$afiliado, $lote] = $this->resolverAfiliadoYLote($id);
        $validated = $request->validated();
        $fechaAfiliacionAnterior = $lote?->fecha_afiliacion?->toDateString();

        // Activo/Cortado son automáticos por mora anual; solo Baja se puede forzar a mano.
        $estadoActual = $lote?->estado ?? $afiliado->estado;
        if ($validated['estado'] === Afiliado::ESTADO_BAJA) {
            $validated['estado'] = Afiliado::ESTADO_BAJA;
        } elseif ($estadoActual === Afiliado::ESTADO_BAJA) {
            $validated['estado'] = Afiliado::ESTADO_ACTIVO;
        } else {
            $validated['estado'] = in_array($estadoActual, [Afiliado::ESTADO_ACTIVO, Afiliado::ESTADO_CORTADO], true)
                ? $estadoActual
                : Afiliado::ESTADO_ACTIVO;
        }

        DB::transaction(function () use ($afiliado, &$lote, $validated, $fechaAfiliacionAnterior): void {
            $afiliado->update([
                'nombres' => $validated['nombres'],
                'apellidos' => $validated['apellidos'],
                'ci' => $validated['ci'],
                'direccion' => $validated['direccion'] ?? null,
                'estado' => $validated['estado'],
            ]);

            $datosLote = [
                'numero_lote' => $validated['numero_lote'],
                'otb_id' => $validated['otb_id'],
                'direccion' => $validated['direccion'] ?? null,
                'fecha_afiliacion' => $validated['fecha_afiliacion'],
                'forma_pago_afiliacion' => $validated['forma_pago_afiliacion'],
                'monto_afiliacion' => $validated['monto_afiliacion'],
                'monto_afiliacion_agua' => $validated['monto_afiliacion_agua'],
                'monto_afiliacion_alcantarillado' => $validated['monto_afiliacion_alcantarillado'],
                'saldo_afiliacion_agua' => $validated['saldo_afiliacion_agua'] ?? 0,
                'saldo_afiliacion_alcantarillado' => $validated['saldo_afiliacion_alcantarillado'] ?? 0,
                'saldo_afiliacion' => $validated['saldo_afiliacion'] ?? 0,
                'estado' => $validated['estado'],
                'observaciones' => $validated['observaciones'] ?? null,
                'categoria_id' => $validated['categoria_id'],
                'telefono' => $validated['telefono'] ?? null,
            ];

            if ($lote) {
                $lote->update($datosLote);
                $lote = $lote->fresh();
                $afiliado->conLote($lote);
            } else {
                $lote = $afiliado->sincronizarLotePrincipal($datosLote);
            }

            $lote->load('categoria');
            $validated = $this->alinearServiciosYAfiliacionesBase($validated);
            $lote->sincronizarTiposAfiliacion(
                $validated['tipos_afiliacion'] ?? [],
                $this->montosPorTipoAfiliacion($validated),
            );
            $lote->sincronizarServicios(
                $validated['servicios'] ?? [],
                $validated['fecha_afiliacion'],
                $fechaAfiliacionAnterior,
            );
            // Si la fecha de afiliación (o el inicio de un servicio) se movió hacia adelante,
            // se quitan las cuotas sin pagos que quedaron antes del nuevo inicio de cobro, la
            // mora que solo existía por esos meses y el corte, si ya no llega a los meses del corte.
            if (app(\App\Services\CuotaMensualService::class)->depurarCuotasFueraDeVigencia($lote->fresh()) > 0) {
                app(\App\Services\CalculadorMultasService::class)->retirarMoraQueYaNoCorresponde($lote->fresh());
                app(\App\Services\CalculadorMultasService::class)->recalcularCortes([$lote->fresh()]);
            }
            $afiliado->refrescarTelefonoDesdeLotes();
            $this->afiliadoUser->syncFromAfiliado($afiliado->fresh());
        });

        if ($validated['estado'] !== Afiliado::ESTADO_BAJA) {
            $afiliadoFresh = $afiliado->fresh();
            $loteFresh = $lote?->fresh();
            $this->calculadorMultas->actualizarEstadoPorMora(
                $loteFresh ? $afiliadoFresh->conLote($loteFresh) : $afiliadoFresh
            );
        }

        $this->activityLogger->log(
            $request->user(),
            'Afiliados',
            "Actualización de afiliado: {$afiliado->nombre_completo}",
        );

        $destinoId = $lote?->id ?? $afiliado->id;

        return redirect()
            ->route('afiliados.show', $destinoId)
            ->with('success', 'Afiliado actualizado correctamente.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        [$afiliado, $lote] = $this->resolverAfiliadoYLote($id);
        $loteObjetivo = $lote ?? $afiliado->lotes()->first();

        if (! $loteObjetivo) {
            return redirect()
                ->route('afiliados.index')
                ->with('error', 'No se encontró un lote asociado para eliminar.');
        }

        if ($loteObjetivo->pagos()->exists() || $loteObjetivo->multas()->exists()) {
            return redirect()
                ->back()
                ->with('error', 'No se puede eliminar: el lote tiene registros operativos (pagos o multas).');
        }

        $nombreAfiliado = $afiliado->nombre_completo;
        $afiliadoId = $loteObjetivo->afiliado_id;

        DB::transaction(function () use ($loteObjetivo, $afiliadoId): void {
            $loteObjetivo->servicios()->detach();
            $loteObjetivo->loteServicios()->delete();
            $loteObjetivo->delete();

            $afiliado = Afiliado::query()->withCount('lotes')->find($afiliadoId);

            if ($afiliado && $afiliado->lotes_count === 0) {
                $afiliado->user()?->delete();
                $afiliado->delete();
            }
        });

        $this->activityLogger->log(
            $request->user(),
            'Afiliados',
            "Eliminación de lote/afiliado: {$nombreAfiliado}",
        );

        return redirect()
            ->route('afiliados.index')
            ->with('success', 'Lote eliminado correctamente.');
    }

    public function changeStatus(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'estado' => ['required', Rule::in([
                Afiliado::ESTADO_ACTIVO,
                Afiliado::ESTADO_CORTADO,
                Afiliado::ESTADO_BAJA,
            ])],
        ]);

        [$afiliado, $lote] = $this->resolverAfiliadoYLote($id);

        if ($lote) {
            $lote->update(['estado' => $validated['estado']]);
        }

        $afiliado->update(['estado' => $validated['estado']]);
        $this->afiliadoUser->syncFromAfiliado($afiliado->fresh());

        $this->activityLogger->log(
            $request->user(),
            'Afiliados',
            "Cambio de estado a {$validated['estado']}: {$afiliado->nombre_completo}",
        );

        return redirect()->back()->with('success', 'Estado del afiliado actualizado correctamente.');
    }

    /**
     * Resuelve id de ruta: prioriza lote_afiliados, fallback a afiliados (p. ej. ?desde=).
     *
     * @return array{0: Afiliado, 1: LoteAfiliado|null}
     */
    private function resolverAfiliadoYLote(int $id, bool $fail = true): array
    {
        $lote = LoteAfiliado::query()->with(['afiliado', 'otb'])->find($id);

        if ($lote) {
            return [$lote->afiliado->conLote($lote), $lote];
        }

        $query = Afiliado::query()->with(['lotes.otb']);
        $afiliado = $fail ? $query->findOrFail($id) : $query->find($id);

        if (! $afiliado) {
            return [null, null];
        }

        $lote = $afiliado->lotes->first();

        return [$afiliado->conLote($lote), $lote];
    }

    /**
     * Agua/alcantarillado: afiliación y servicio del lote deben ir juntos.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function alinearServiciosYAfiliacionesBase(array $validated): array
    {
        $servicioIds = collect($validated['servicios'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $tipoIds = collect($validated['tipos_afiliacion'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $servicios = Servicio::query()->whereIn('id', $servicioIds)->get();
        $esAgua = static fn (Servicio $servicio): bool => $servicio->esAgua();
        $esAlc = static fn (Servicio $servicio): bool => $servicio->esAlcantarillado();

        $esPlazo = ($validated['forma_pago_afiliacion'] ?? null) === Afiliado::FORMA_PLAZO;
        $tipoAguaId = TipoAfiliacion::query()->where('codigo', TipoAfiliacion::CODIGO_AGUA)->where('activo', true)->value('id');
        $tipoAlcId = TipoAfiliacion::query()->where('codigo', TipoAfiliacion::CODIGO_ALCANTARILLADO)->where('activo', true)->value('id');
        $idsServicioAgua = Servicio::query()->where('activo', true)->get()->filter($esAgua)->pluck('id');
        $idsServicioAlc = Servicio::query()->where('activo', true)->get()->filter($esAlc)->pluck('id');

        $quiereAgua = $servicios->contains($esAgua)
            || ($tipoAguaId && $tipoIds->contains((int) $tipoAguaId))
            || (float) ($validated['monto_afiliacion_agua'] ?? 0) > 0;

        $quiereAlc = $servicios->contains($esAlc)
            || ($tipoAlcId && $tipoIds->contains((int) $tipoAlcId))
            || (float) ($validated['monto_afiliacion_alcantarillado'] ?? 0) > 0;

        $servicioIds = $servicioIds
            ->reject(fn (int $id) => $idsServicioAgua->contains($id) || $idsServicioAlc->contains($id))
            ->values();
        $tipoIds = $tipoIds
            ->reject(fn (int $id) => $id === (int) $tipoAguaId || $id === (int) $tipoAlcId)
            ->values();

        if ($quiereAgua) {
            $servicioIds = $servicioIds->merge($idsServicioAgua)->unique()->values();
            if ($tipoAguaId) {
                $tipoIds = $tipoIds->push((int) $tipoAguaId)->unique()->values();
            }
            if ((float) ($validated['monto_afiliacion_agua'] ?? 0) <= 0) {
                $validated['monto_afiliacion_agua'] = $this->montoCatalogoAfiliacion($tipoAguaId, $esPlazo)
                    ?? PagoDetalle::MONTO_AFILIACION_AGUA;
            }
        } else {
            $validated['monto_afiliacion_agua'] = 0;
        }

        if ($quiereAlc) {
            $servicioIds = $servicioIds->merge($idsServicioAlc)->unique()->values();
            if ($tipoAlcId) {
                $tipoIds = $tipoIds->push((int) $tipoAlcId)->unique()->values();
            }
            if ((float) ($validated['monto_afiliacion_alcantarillado'] ?? 0) <= 0) {
                $validated['monto_afiliacion_alcantarillado'] = $this->montoCatalogoAfiliacion($tipoAlcId, $esPlazo)
                    ?? PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO;
            }
        } else {
            $validated['monto_afiliacion_alcantarillado'] = 0;
        }

        $validated['servicios'] = $servicioIds->all();
        $validated['tipos_afiliacion'] = $tipoIds->all();
        $validated['monto_afiliacion'] = round(
            (float) $validated['monto_afiliacion_agua'] + (float) $validated['monto_afiliacion_alcantarillado'],
            2,
        );

        return $validated;
    }

    private function montoCatalogoAfiliacion(mixed $tipoId, bool $esPlazo): ?float
    {
        $tipo = $tipoId ? TipoAfiliacion::query()->find($tipoId) : null;
        if (! $tipo) {
            return null;
        }

        return $esPlazo ? $tipo->montoPlazo() : $tipo->montoContado();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, float>
     */
    private function montosPorTipoAfiliacion(array $validated): array
    {
        $tipos = TipoAfiliacion::query()
            ->whereIn('id', $validated['tipos_afiliacion'] ?? [])
            ->get()
            ->keyBy('id');

        $montos = [];
        foreach ($tipos as $id => $tipo) {
            $montos[(int) $id] = match ($tipo->codigo) {
                TipoAfiliacion::CODIGO_AGUA => (float) ($validated['monto_afiliacion_agua'] ?? $tipo->monto),
                TipoAfiliacion::CODIGO_ALCANTARILLADO => (float) ($validated['monto_afiliacion_alcantarillado'] ?? $tipo->monto),
                default => (float) $tipo->monto,
            };
        }

        return $montos;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'otbs' => Otb::query()->where('activa', true)->orderBy('nombre')->get(['id', 'nombre']),
            'servicios' => Servicio::query()
                ->with('tarifasCategoria')
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'tipo', 'tarifa_mensual'])
                ->map(fn (Servicio $servicio): array => [
                    'id' => $servicio->id,
                    'codigo' => $servicio->codigo,
                    'nombre' => $servicio->nombre,
                    'tipo' => $servicio->tipo,
                    'tarifa_mensual' => (float) $servicio->tarifa_mensual,
                    'tarifas_categoria' => $servicio->tarifasCategoria
                        ->mapWithKeys(fn (Categoria $categoria): array => [$categoria->id => (float) $categoria->pivot->tarifa])
                        ->all(),
                ]),
            'montos_afiliacion' => [
                'agua' => (float) (TipoAfiliacion::query()->where('codigo', TipoAfiliacion::CODIGO_AGUA)->value('monto')
                    ?? PagoDetalle::MONTO_AFILIACION_AGUA),
                'alcantarillado' => (float) (TipoAfiliacion::query()->where('codigo', TipoAfiliacion::CODIGO_ALCANTARILLADO)->value('monto')
                    ?? PagoDetalle::MONTO_AFILIACION_ALCANTARILLADO),            ],
            'tipos_afiliacion' => TipoAfiliacion::opcionesActivas(),
            'categorias' => Categoria::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion', 'tarifa_agua', 'tarifa_alcantarillado'])
                ->map(fn (Categoria $categoria): array => [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'descripcion' => $categoria->descripcion ?? '',
                    'tarifa_agua' => (float) $categoria->tarifa_agua,
                    'tarifa_alcantarillado' => (float) $categoria->tarifa_alcantarillado,
                    'total_mensual' => $categoria->tarifaMensualTotal(),
                ])
                ->values()
                ->all(),
            'estados' => [
                Afiliado::ESTADO_ACTIVO,
                Afiliado::ESTADO_CORTADO,
                Afiliado::ESTADO_BAJA,
            ],
        ];
    }
}
