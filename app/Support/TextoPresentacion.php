<?php

namespace App\Support;

/**
 * Limpia textos de documentos (recibos) para no repetir barrio, concepto u otras frases.
 */
final class TextoPresentacion
{
    private const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /**
     * Agrupa líneas de recibo con el mismo concepto SUMANDO montos (no las descarta)
     * y agrega al texto los meses cubiertos: "Consumo de agua — agosto a octubre 2026 (3 meses)".
     *
     * @param  array<int, array<string, mixed>>  $lineas  cada una con 'texto', 'monto' y opcional 'periodo'
     * @return array<int, array<string, mixed>>
     */
    public static function agruparLineasRecibo(array $lineas): array
    {
        $grupos = [];

        foreach ($lineas as $linea) {
            $texto = self::sinDuplicados((string) ($linea['texto'] ?? ''));
            $clave = mb_strtolower($texto);

            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [...$linea, 'texto' => $texto, 'monto' => 0.0, 'periodos' => [], 'montos' => []];
            }

            $grupos[$clave]['monto'] = round($grupos[$clave]['monto'] + (float) ($linea['monto'] ?? 0), 2);
            $grupos[$clave]['montos'][] = round((float) ($linea['monto'] ?? 0), 2);

            if (preg_match('/^\d{4}-\d{2}$/', (string) ($linea['periodo'] ?? '')) === 1) {
                $grupos[$clave]['periodos'][] = (string) $linea['periodo'];
            }
        }

        return array_values(array_map(function (array $grupo): array {
            $periodos = array_values(array_unique($grupo['periodos']));
            sort($periodos);

            $montos = array_values(array_unique($grupo['montos']));

            $grupo['texto_base'] = $grupo['texto'];
            $grupo['meses'] = count($periodos);
            $grupo['rango'] = $periodos !== [] ? self::rangoDePeriodos($periodos) : null;
            // Precio por mes, solo si todos los meses costaron lo mismo.
            $grupo['unitario'] = count($periodos) > 1 && count($montos) === 1 ? $montos[0] : null;

            if ($periodos !== []) {
                // 12 meses del mismo año = recibo anual de esa gestión.
                $esAnual = count($periodos) === 12
                    && substr($periodos[0], 0, 4) === substr($periodos[11], 0, 4)
                    && (int) substr($periodos[0], 5, 2) === 1
                    && (int) substr($periodos[11], 5, 2) === 12;

                $grupo['texto'] .= $esAnual
                    ? ' — gestión '.substr($periodos[0], 0, 4).' (anual)'
                    : ' — '.$grupo['rango'];
                $grupo['periodo'] = implode(',', $periodos);
            }

            unset($grupo['periodos'], $grupo['montos']);

            return $grupo;
        }, $grupos));
    }

    /**
     * "2026-07" => "julio 2026".
     */
    public static function nombreMes(string $periodo): string
    {
        [$anio, $mes] = array_map('intval', explode('-', $periodo) + [1 => 1]);

        return (self::MESES[$mes] ?? $periodo).' '.$anio;
    }

    /**
     * "2026-07" => "jul 2026".
     */
    public static function mesCorto(string $periodo): string
    {
        [$anio, $mes] = array_map('intval', explode('-', $periodo) + [1 => 1]);

        return mb_substr(self::MESES[$mes] ?? '', 0, 3).' '.$anio;
    }

    /**
     * ['2026-08','2026-09','2026-10'] => "agosto a octubre 2026 (3 meses)".
     *
     * @param  list<string>  $periodos  periodos "Y-m" ordenados
     */
    public static function rangoDePeriodos(array $periodos): string
    {
        $bloques = [];
        $inicio = $fin = null;

        foreach ($periodos as $periodo) {
            [$anio, $mes] = array_map('intval', explode('-', $periodo));
            $orden = $anio * 12 + $mes;

            if ($fin !== null && $orden === $fin[2] + 1) {
                $fin = [$anio, $mes, $orden];

                continue;
            }

            if ($inicio !== null) {
                $bloques[] = [$inicio, $fin];
            }

            $inicio = $fin = [$anio, $mes, $orden];
        }

        if ($inicio !== null) {
            $bloques[] = [$inicio, $fin];
        }

        $textos = array_map(function (array $bloque): string {
            [$i, $f] = $bloque;

            if ($i[2] === $f[2]) {
                return self::MESES[$i[1]].' '.$i[0];
            }

            return $i[0] === $f[0]
                ? self::MESES[$i[1]].' a '.self::MESES[$f[1]].' '.$f[0]
                : self::MESES[$i[1]].' '.$i[0].' a '.self::MESES[$f[1]].' '.$f[0];
        }, $bloques);

        $cantidad = count($periodos);

        return implode(', ', $textos).($cantidad > 1 ? " ({$cantidad} meses)" : '');
    }

    public static function sinDuplicados(string $texto): string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));

        if ($texto === '') {
            return '';
        }

        $prev = null;
        while ($prev !== $texto) {
            $prev = $texto;
            $texto = (string) preg_replace('/(\([^)]+\))(?:\s*\1)+/iu', '$1', $texto);
        }

        $tokens = preg_split('/\s+/u', $texto) ?: [];
        $tokens = self::colapsarTokensConsecutivos($tokens);
        $tokens = self::colapsarPrefijoRepetido($tokens);
        $tokens = self::colapsarMitadRepetida($tokens);

        return implode(' ', $tokens);
    }

    public static function componerNombreConOtb(string $nombre, ?string $otb): string
    {
        $nombre = self::sinDuplicados($nombre);
        $otb = self::sinDuplicados(trim((string) $otb));

        if ($otb === '') {
            return $nombre;
        }

        $nombreNorm = self::normalizar($nombre);
        $otbNorm = self::normalizar($otb);

        if ($otbNorm !== '' && str_contains($nombreNorm, $otbNorm)) {
            return $nombre;
        }

        return self::sinDuplicados($nombre.' ('.$otb.')');
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private static function colapsarTokensConsecutivos(array $tokens): array
    {
        $salida = [];

        foreach ($tokens as $token) {
            if ($salida !== [] && self::normalizar((string) end($salida)) === self::normalizar($token)) {
                continue;
            }

            $salida[] = $token;
        }

        return $salida;
    }

    /**
     * JOSE MER JOSE MER (BARRIO X) → JOSE MER (BARRIO X)
     *
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private static function colapsarPrefijoRepetido(array $tokens): array
    {
        $n = count($tokens);

        if ($n < 4) {
            return $tokens;
        }

        for ($len = intdiv($n, 2); $len >= 2; $len--) {
            $primera = array_slice($tokens, 0, $len);
            $segunda = array_slice($tokens, $len, $len);

            if (self::normalizar(implode(' ', $primera)) === self::normalizar(implode(' ', $segunda))) {
                return array_merge($primera, array_slice($tokens, $len * 2));
            }
        }

        return $tokens;
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private static function colapsarMitadRepetida(array $tokens): array
    {
        $n = count($tokens);

        if ($n < 2 || $n % 2 !== 0) {
            return $tokens;
        }

        $mitad = intdiv($n, 2);
        $primera = array_slice($tokens, 0, $mitad);
        $segunda = array_slice($tokens, $mitad);

        if (self::normalizar(implode(' ', $primera)) === self::normalizar(implode(' ', $segunda))) {
            return $primera;
        }

        return $tokens;
    }

    private static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $reemplazos = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ];
        $texto = strtr($texto, $reemplazos);

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $texto);
    }
}
