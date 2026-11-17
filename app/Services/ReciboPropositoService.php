<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Servicio;
use App\Models\TipoAfiliacion;
use App\Support\TextoPresentacion;
use Illuminate\Support\Collection;

/**
 * Clasifica líneas de cobro:
 * - RECIBO DE INGRESO → servicios mensuales (agua potable y alcantarillado)
 * - RECIBO DE CAJA → resto (afiliación, multas, aportes, faltas, cambio de nombre, etc.)
 * Un recibo = un propósito (cuenta), no se mezclan.
 */
class ReciboPropositoService
{
    public const TIPO_INGRESO = 'ingreso';

    public const TIPO_CAJA = 'caja';

    /**
     * @param  array<int, array<string, mixed>>  $detalles
     * @return array<int, array{key: string, label: string, tipo: string, detalles: array<int, array<string, mixed>>}>
     */
    public function agruparDetallesParaRecibos(array $detalles): array
    {
        $expandido = [];
        foreach ($detalles as $detalle) {
            foreach ($this->expandirSiCombinado($detalle) as $linea) {
                $expandido[] = $linea;
            }
        }

        $grupos = [];

        foreach ($expandido as $detalle) {
            // Agua/alc: un recibo por gestión (año). 1 mes, varios o los 12 del año → un solo recibo.
            $cuenta = $this->conGestionDeServicio($this->clasificar($detalle), $detalle);
            $key = $cuenta['key'];

            if (! isset($grupos[$key])) {
                $grupos[$key] = [
                    'key' => $cuenta['key'],
                    'label' => $cuenta['label'],
                    'tipo' => $cuenta['tipo'],
                    'detalles' => [],
                    'claves' => [],
                ];
            }

            $claveLinea = $this->claveLineaDetalle($detalle);

            if (isset($grupos[$key]['claves'][$claveLinea])) {
                continue;
            }

            $grupos[$key]['claves'][$claveLinea] = true;
            $grupos[$key]['detalles'][] = $detalle;
        }

        foreach ($grupos as &$grupo) {
            unset($grupo['claves']);
        }
        unset($grupo);

        return array_values($grupos);
    }

    /**
     * @param  array<string, mixed>|PagoDetalle  $detalle
     * @return array{key: string, label: string, tipo: string}
     */
    public function clasificar(array|PagoDetalle $detalle): array
    {
        $descripcion = mb_strtolower(trim((string) $this->campo($detalle, 'descripcion', '')));
        $concepto = (string) $this->campo($detalle, 'concepto', '');
        $servicioId = $this->campo($detalle, 'servicio_id');

        $servicio = null;
        if ($detalle instanceof PagoDetalle) {
            $detalle->loadMissing('servicio');
            $servicio = $detalle->servicio;
        } elseif (filled($servicioId)) {
            $servicio = Servicio::query()->find($servicioId);
        }

        $porConcepto = PagoDetalle::clasificacionCuota($concepto);
        if ($porConcepto !== null) {
            return $porConcepto;
        }

        $tipoServicio = mb_strtolower((string) ($servicio?->tipo ?? ''));
        $codigo = mb_strtoupper((string) ($servicio?->codigo ?? ''));
        $nombreServicio = mb_strtolower((string) ($servicio?->nombre ?? ''));
        $texto = trim($descripcion.' '.$nombreServicio.' '.$tipoServicio.' '.$codigo);

        if (str_contains($texto, 'cambio de nombre') || str_contains($texto, 'cambio nombre')) {
            return [
                'key' => 'cambio_de_nombre',
                'label' => 'cambio de nombre',
                'tipo' => self::TIPO_CAJA,
            ];
        }

        $tieneReuniones = str_contains($texto, 'reunion') || str_contains($texto, 'reunión');
        $tieneTrabajos = str_contains($texto, 'trabajo');
        $esAlcantarillado = str_contains($texto, 'alcantar');

        if ($tieneReuniones && ! $tieneTrabajos) {
            return $esAlcantarillado
                ? [
                    'key' => 'falta_reuniones_alcantarillado',
                    'label' => 'Falta de reuniones de alcantarillado',
                    'tipo' => self::TIPO_CAJA,
                ]
                : [
                    'key' => 'falta_reuniones_agua_potable',
                    'label' => 'Falta de reuniones de agua potable',
                    'tipo' => self::TIPO_CAJA,
                ];
        }

        if ($tieneTrabajos && ! $tieneReuniones) {
            return $esAlcantarillado
                ? [
                    'key' => 'falta_trabajos_alcantarillado',
                    'label' => 'Falta de trabajos de alcantarillado',
                    'tipo' => self::TIPO_CAJA,
                ]
                : [
                    'key' => 'falta_trabajos_agua_potable',
                    'label' => 'Falta de trabajos de agua potable',
                    'tipo' => self::TIPO_CAJA,
                ];
        }

        // Afiliación antes que agua/alcantarillado de servicio mensual:
        // textos como «Afiliación agua potable» no deben ir a recibo de cuota.
        if (
            PagoDetalle::esConceptoAfiliacion($concepto)
            || str_contains($texto, 'afiliaci')
        ) {
            return [
                'key' => 'afiliacion',
                'label' => 'Afiliación',
                'tipo' => self::TIPO_CAJA,
            ];
        }

        if (
            $tipoServicio === 'alcantarillado'
            || $codigo === 'ALC'
            || str_contains($texto, 'alcantarillado')
        ) {
            return [
                'key' => 'servicio_alcantarillado',
                'label' => 'Servicio de Alcantarillado',
                'tipo' => self::TIPO_INGRESO,
            ];
        }

        if (
            $tipoServicio === 'agua'
            || $codigo === 'AGUA'
            || str_contains($texto, 'agua potable')
            || str_contains($texto, 'servicio agua')
            || str_contains($texto, 'cuota agua')
            || ($concepto === PagoDetalle::CONCEPTO_SERVICIO_MENSUAL && $servicio !== null)
        ) {
            return [
                'key' => 'servicio_agua_potable',
                'label' => 'Servicio Agua Potable',
                'tipo' => self::TIPO_INGRESO,
            ];
        }

        if ($concepto === PagoDetalle::CONCEPTO_APORTE || str_contains($texto, 'aporte')) {
            $label = trim((string) $this->campo($detalle, 'descripcion', '')) ?: 'Aporte';

            return [
                'key' => 'aporte:'.mb_strtolower($label),
                'label' => $label,
                'tipo' => self::TIPO_CAJA,
            ];
        }

        if ($concepto === PagoDetalle::CONCEPTO_MULTA || str_contains($texto, 'multa')) {
            $label = trim((string) $this->campo($detalle, 'descripcion', '')) ?: 'Multa';

            return [
                'key' => 'multa:'.mb_strtolower($label),
                'label' => $label,
                'tipo' => self::TIPO_CAJA,
            ];
        }

        $label = trim((string) $this->campo($detalle, 'descripcion', '')) ?: 'Otro';

        return [
            'key' => 'otro:'.mb_strtolower($label),
            'label' => $label,
            'tipo' => self::TIPO_CAJA,
        ];
    }

    /**
     * Texto de línea del recibo sin repetir el título del propósito.
     * Las fechas se muestran completas (dd/mm/aaaa), nunca solo mm/aaaa.
     *
     * @param  array<string, mixed>|PagoDetalle  $detalle
     * @param  array{key: string, label: string, tipo: string}  $cuenta
     */
    public function textoConcepto(array|PagoDetalle $detalle, array $cuenta, ?string $fechaPagoDisplay = null): string
    {
        // La fecha de pago no se antepone al concepto (queda solo en el encabezado del recibo).
        unset($fechaPagoDisplay);

        $raw = $this->sinFechaPrefijo(trim((string) $this->campo($detalle, 'descripcion', '')));
        $texto = $cuenta['label'];

        if (str_starts_with($cuenta['key'], 'servicio_agua_potable')) {
            $base = $raw !== '' ? $raw : $cuenta['label'];
            // En el cuerpo del recibo no repetir "· gestión YYYY" del título.
            $base = trim((string) preg_replace('/\s*·\s*gestión\s+\d{4}/iu', '', $base));
            $labelBase = trim((string) preg_replace('/\s*·\s*gestión\s+\d{4}/iu', '', $cuenta['label']));
            if ($base === '' || mb_strtolower($base) === mb_strtolower($labelBase)) {
                $base = $labelBase !== '' ? $labelBase : $cuenta['label'];
            }
            $texto = $base;
        } elseif (str_starts_with($cuenta['key'], 'servicio_alcantarillado')) {
            $base = $raw !== '' ? $raw : $cuenta['label'];
            $base = trim((string) preg_replace('/\s*·\s*gestión\s+\d{4}/iu', '', $base));
            $labelBase = trim((string) preg_replace('/\s*·\s*gestión\s+\d{4}/iu', '', $cuenta['label']));
            if ($base === '' || mb_strtolower($base) === mb_strtolower($labelBase)) {
                $base = $labelBase !== '' ? $labelBase : $cuenta['label'];
            }
            $texto = $base;
        } elseif ($cuenta['key'] === 'cambio_de_nombre') {
            $resto = trim((string) preg_replace('/^cambio\s+de\s+nombre\s*/iu', '', $raw));
            $texto = $resto !== '' ? 'cambio de nombre '.$resto : 'cambio de nombre';
        } elseif (str_starts_with($cuenta['key'], 'falta_reuniones_') || $cuenta['key'] === 'falta_de_reuniones') {
            $resto = trim((string) preg_replace(
                '/^falta\s+de\s+reuniones?(?:\s+de\s+(?:agua(?:\s+potable)?|alcantarillado))?\s*/iu',
                '',
                $raw,
            ));
            $texto = $resto !== '' ? $resto : $cuenta['label'];
        } elseif (str_starts_with($cuenta['key'], 'falta_trabajos_') || $cuenta['key'] === 'falta_de_trabajos') {
            $resto = trim((string) preg_replace(
                '/^falta\s+de\s+(?:reuniones?\s+y\s+)?trabajos?(?:\s+de\s+(?:agua(?:\s+potable)?|alcantarillado))?\s*/iu',
                '',
                $raw,
            ));
            $texto = $resto !== '' ? $resto : $cuenta['label'];
        } elseif ($raw !== '') {
            $texto = $raw;
        }

        return TextoPresentacion::sinDuplicados($this->sinFechaPrefijo($texto));
    }

    public function tituloDocumento(string $tipo): string
    {
        return $tipo === self::TIPO_INGRESO ? 'RECIBO DE INGRESO' : 'RECIBO DE CAJA';
    }

    /**
     * Cuenta del recibo ya guardado. Agua y alcantarillado siempre son ingreso,
     * aunque un registro viejo diga caja.
     *
     * @return array{key: string, label: string, tipo: string}
     */
    public function cuentaDesdePago(Pago $pago): array
    {
        $cuenta = $pago->proposito
            ? [
                'key' => (string) $pago->proposito,
                'label' => (string) ($pago->proposito_label ?: $pago->proposito),
                'tipo' => (string) ($pago->tipo_recibo ?: self::TIPO_CAJA),
            ]
            : $this->clasificarPagoDesdeDetalles($pago->detalles);

        return $this->normalizarTipoCuenta($cuenta);
    }

    /**
     * @param  array{key: string, label: string, tipo: string}  $cuenta
     * @return array{key: string, label: string, tipo: string}
     */
    public function normalizarTipoCuenta(array $cuenta): array
    {
        $key = (string) ($cuenta['key'] ?? '');

        if ($this->esCuentaServicioMensual($key)) {
            $cuenta['tipo'] = self::TIPO_INGRESO;
        }

        return $cuenta;
    }

    /** Agua o alcantarillado (con o sin sufijo :YYYY de la gestión). */
    public function esCuentaServicioMensual(string $key): bool
    {
        return $key === 'servicio_agua_potable'
            || str_starts_with($key, 'servicio_agua_potable:')
            || $key === 'servicio_alcantarillado'
            || str_starts_with($key, 'servicio_alcantarillado:');
    }

    /**
     * Separa recibos de servicio por año: p. ej. servicio_agua_potable:2026.
     * Así un cobro de 2025+2026 emite dos recibos; los meses del mismo año van juntos.
     *
     * @param  array{key: string, label: string, tipo: string}  $cuenta
     * @param  array<string, mixed>|PagoDetalle  $detalle
     * @return array{key: string, label: string, tipo: string}
     */
    public function conGestionDeServicio(array $cuenta, array|PagoDetalle $detalle): array
    {
        if (! $this->esCuentaServicioMensual($cuenta['key'])) {
            return $cuenta;
        }

        // Si ya trae gestión, no duplicar.
        if (str_contains($cuenta['key'], ':')) {
            return $cuenta;
        }

        $periodo = (string) $this->campo($detalle, 'periodo', '');
        if (preg_match('/^(\d{4})-\d{2}$/', $periodo, $m) !== 1) {
            return $cuenta;
        }

        $anio = $m[1];
        $cuenta['key'] = $cuenta['key'].':'.$anio;
        if (! str_contains(mb_strtolower($cuenta['label']), 'gestión '.$anio)) {
            $cuenta['label'] = trim($cuenta['label'].' · gestión '.$anio);
        }

        return $cuenta;
    }

    /**
     * "Son: CINCUENTA Y SIETE BOLIVIANOS"
     * Con centavos: "Son: CINCUENTA Y SIETE BOLIVIANOS 50/100"
     */
    public function montoEnLetras(float $monto): string
    {
        $formateado = number_format(round($monto, 2), 2, '.', '');
        [$enterosStr, $centavosStr] = explode('.', $formateado);
        $enteros = (int) $enterosStr;
        $moneda = $enteros === 1 ? 'BOLIVIANO' : 'BOLIVIANOS';
        $base = 'Son: '.$this->numeroALetras($enteros).' '.$moneda;

        if ((int) $centavosStr === 0) {
            return $base;
        }

        return $base.' '.$centavosStr.'/100';
    }

    /**
     * @param  array<string, mixed>  $detalle
     * @return array<int, array<string, mixed>>
     */
    private function expandirSiCombinado(array $detalle): array
    {
        $texto = mb_strtolower(trim((string) ($detalle['descripcion'] ?? '')));
        $tieneReuniones = str_contains($texto, 'reunion') || str_contains($texto, 'reunión');
        $tieneTrabajos = str_contains($texto, 'trabajo');

        if (! ($tieneReuniones && $tieneTrabajos)) {
            return [$detalle];
        }

        $periodo = trim((string) preg_replace(
            '/falta\s+de\s+reuniones?\s+y\s+trabajos?/iu',
            '',
            (string) ($detalle['descripcion'] ?? ''),
        ));
        $periodo = trim($periodo, " \t\n\r\0\x0B-–");

        $montoTotal = round((float) ($detalle['monto'] ?? 0), 2);
        $mitad = round($montoTotal / 2, 2);
        $resto = round($montoTotal - $mitad, 2);

        $reuniones = $detalle;
        $reuniones['descripcion'] = trim('falta de reuniones '.($periodo !== '' ? $periodo : ''));
        $reuniones['monto'] = $mitad;

        $trabajos = $detalle;
        $trabajos['descripcion'] = trim('falta de trabajos '.($periodo !== '' ? $periodo : ''));
        $trabajos['monto'] = $resto;

        return [$reuniones, $trabajos];
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private function claveLineaDetalle(array $detalle): string
    {
        return implode('|', [
            (string) ($detalle['concepto'] ?? ''),
            (string) ($detalle['periodo'] ?? ''),
            (string) ($detalle['servicio_id'] ?? ''),
            (string) ($detalle['multa_id'] ?? ''),
            mb_strtolower(trim((string) ($detalle['descripcion'] ?? ''))),
        ]);
    }

    private function sinFechaPrefijo(string $texto): string
    {
        $texto = (string) preg_replace('/^\d{2}\/\d{2}\/\d{4}\s+/u', '', $texto);
        $texto = (string) preg_replace('/^\d{2}\/\d{4}\s+/u', '', $texto);

        return trim($texto);
    }

    private function campo(array|PagoDetalle $detalle, string $key, mixed $default = null): mixed
    {
        if ($detalle instanceof PagoDetalle) {
            return $detalle->{$key} ?? $default;
        }

        return $detalle[$key] ?? $default;
    }

    private function numeroALetras(int $n): string
    {
        if ($n < 0) {
            return 'MENOS '.$this->numeroALetras(abs($n));
        }

        $unidades = [
            0 => 'CERO', 1 => 'UN', 2 => 'DOS', 3 => 'TRES', 4 => 'CUATRO',
            5 => 'CINCO', 6 => 'SEIS', 7 => 'SIETE', 8 => 'OCHO', 9 => 'NUEVE',
            10 => 'DIEZ', 11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE',
            15 => 'QUINCE', 16 => 'DIECISEIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE',
            20 => 'VEINTE', 21 => 'VEINTIUNO', 22 => 'VEINTIDOS', 23 => 'VEINTITRES', 24 => 'VEINTICUATRO',
            25 => 'VEINTICINCO', 26 => 'VEINTISEIS', 27 => 'VEINTISIETE', 28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE',
        ];

        $decenas = [
            3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA',
            6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA',
        ];

        $centenas = [
            1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS',
            5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS',
        ];

        if ($n < 30) {
            return $unidades[$n] === 'UN' ? 'UN' : $unidades[$n];
        }

        if ($n < 100) {
            $d = intdiv($n, 10);
            $u = $n % 10;
            if ($u === 0) {
                return $decenas[$d];
            }

            return $decenas[$d].' Y '.$unidades[$u];
        }

        if ($n === 100) {
            return 'CIEN';
        }

        if ($n < 1000) {
            $c = intdiv($n, 100);
            $r = $n % 100;
            $pref = $c === 1 ? 'CIENTO' : $centenas[$c];

            return $r === 0 ? ($c === 1 ? 'CIEN' : $centenas[$c]) : $pref.' '.$this->numeroALetras($r);
        }

        if ($n < 1000000) {
            $m = intdiv($n, 1000);
            $r = $n % 1000;
            $pref = $m === 1 ? 'MIL' : $this->numeroALetras($m).' MIL';

            return $r === 0 ? $pref : $pref.' '.$this->numeroALetras($r);
        }

        $millones = intdiv($n, 1000000);
        $r = $n % 1000000;
        $pref = $millones === 1 ? 'UN MILLON' : $this->numeroALetras($millones).' MILLONES';

        return $r === 0 ? $pref : $pref.' '.$this->numeroALetras($r);
    }

    /**
     * Inferir tipo/propósito de un pago ya guardado (datos legacy sin columna).
     *
     * @return array{key: string, label: string, tipo: string}
     */
    public function clasificarPagoDesdeDetalles(Collection $detalles): array
    {
        if ($detalles->isEmpty()) {
            return ['key' => 'otro', 'label' => 'Otro', 'tipo' => self::TIPO_CAJA];
        }

        return $this->clasificar($detalles->first());
    }
}
