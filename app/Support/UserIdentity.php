<?php

namespace App\Support;

class UserIdentity
{
    public static function buildCiCompleto(string $ci, mixed $complemento = null): string
    {
        return $ci;
    }

    public static function buildNombreCompleto(string $nombres, string $apellidos): string
    {
        return trim(preg_replace('/\s+/', ' ', trim($nombres).' '.trim($apellidos)) ?? '');
    }

    /**
     * @return array{nombres: string, apellidos: string}
     */
    public static function splitNombreCompleto(?string $nombreCompleto): array
    {
        $nombreCompleto = trim((string) $nombreCompleto);

        if ($nombreCompleto === '') {
            return ['nombres' => '', 'apellidos' => ''];
        }

        $parts = preg_split('/\s+/', $nombreCompleto, 2) ?: [];

        return [
            'nombres' => $parts[0] ?? '',
            'apellidos' => $parts[1] ?? '',
        ];
    }
}
