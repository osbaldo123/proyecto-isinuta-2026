<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quita sufijos numéricos artificiales del import (ej. "Felicia1", "Carlos 2").
 * La distinción real es por id / CI, no por números en el nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('afiliados')) {
            return;
        }

        $rows = DB::table('afiliados')
            ->select(['id', 'nombres', 'apellidos'])
            ->get();

        foreach ($rows as $row) {
            $nombres = $this->limpiar($row->nombres);
            $apellidos = $this->limpiar($row->apellidos);

            if ($nombres === $row->nombres && $apellidos === $row->apellidos) {
                continue;
            }

            DB::table('afiliados')->where('id', $row->id)->update([
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'fecha_actualizacion' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No reversible: los sufijos eran datos de importación artificiales.
    }

    private function limpiar(?string $valor): string
    {
        $valor = trim((string) $valor);

        // Quita espacios + dígitos al final: "Carlos 2", "Flores Avila 1"
        $valor = preg_replace('/\s+\d+$/u', '', $valor) ?? $valor;

        // Quita dígitos pegados al final: "Felicia1", "Casa1"
        $valor = preg_replace('/(?<=\p{L})\d+$/u', '', $valor) ?? $valor;

        return trim(preg_replace('/\s+/u', ' ', $valor) ?? $valor);
    }
};
