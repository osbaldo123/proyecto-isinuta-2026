<?php

namespace App\Support;

use App\Models\Multa;

/**
 * Cómo se nombra una multa en pantalla y recibos:
 * «Mora trimestral · Bs 50|100», «Mora anual · Bs 150», «Falta de trabajo · Limpieza».
 */
final class MultaTexto
{
    public static function titulo(Multa $multa): string
    {
        $descripcion = (string) $multa->descripcion;
        $bs = (int) round((float) ($multa->saldo ?? $multa->monto));

        if ($multa->motivo === Multa::MOTIVO_MORA_ANUAL || str_starts_with($descripcion, 'Mora anual')) {
            return 'Mora anual · Bs '.$bs;
        }

        if ($multa->motivo === Multa::MOTIVO_MORA_TRIMESTRAL) {
            $meses = self::mesesDeMora($multa);

            return 'Mora trimestral · Bs '.$bs.($meses ? ' · '.$meses : '');
        }

        if ($multa->motivo === Multa::MOTIVO_FALTA_TRABAJO) {
            return 'Falta de trabajo'.($multa->trabajo?->tipo ? ' · '.ucfirst((string) $multa->trabajo->tipo) : '');
        }

        return $descripcion !== '' ? $descripcion : 'Multa';
    }

    /**
     * Meses que contó la multa, tomados de su descripción.
     */
    private static function mesesDeMora(Multa $multa): ?string
    {
        $descripcion = (string) $multa->descripcion;
        if (preg_match('/^Mora[^:]+:\s*(.+)$/u', $descripcion, $coincide) !== 1) {
            return null;
        }

        $resto = preg_replace('/\s·\s*acumulado.*$/u', '', $coincide[1]) ?? $coincide[1];

        // «Bs 150 (tramo Bs 50): 3 meses…» o «Bs 50: 3 meses…»
        if (preg_match('/^(?:Bs \d+(?: \(tramo Bs \d+\))?:\s*)?(.+)$/u', $resto, $partes) === 1) {
            return trim($partes[1]);
        }

        return trim($resto);
    }

    public static function detalle(Multa $multa): string
    {
        $descripcion = (string) $multa->descripcion;
        $bs = (int) round((float) ($multa->saldo ?? $multa->monto));

        if (str_starts_with($descripcion, 'Mora anual')) {
            return 'Recibo único del año: Bs '.$bs;
        }

        if ($multa->motivo === Multa::MOTIVO_MORA_TRIMESTRAL) {
            return 'Recibo único del año (se suma si no paga): Bs '.$bs;
        }

        return implode(' · ', array_filter([
            $multa->gestion ? 'Gestión '.$multa->gestion : null,
            $multa->trabajo?->otb?->nombre ? 'OTB '.$multa->trabajo->otb->nombre : null,
            $multa->fecha_generacion ? 'desde el '.$multa->fecha_generacion->format('d/m/Y') : null,
        ]));
    }
}
