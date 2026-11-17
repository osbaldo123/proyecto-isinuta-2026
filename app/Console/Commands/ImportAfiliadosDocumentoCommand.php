<?php

namespace App\Console\Commands;

use App\Models\Afiliado;
use App\Models\LoteAfiliado;
use App\Models\Otb;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAfiliadosDocumentoCommand extends Command
{
    protected $signature = 'afiliados:import-documento
                            {--file= : Ruta al JSON maestro}
                            {--dry-run : Simular sin escribir en BD}
                            {--skip-existing : Omitir si ya existe el mismo apellidos + nombres}';

    protected $description = 'Importa afiliados desde el JSON consolidado del documento Word';

    private int $ciSecuencia = 99000000;

    private int $telefonoSecuencia = 71000000;

    public function handle(): int
    {
        $path = $this->option('file')
            ?: database_path('data/afiliados_documento.json');

        if (! is_readable($path)) {
            $this->error("No se encontró el archivo: {$path}");

            return self::FAILURE;
        }

        /** @var list<array{otb:string,apellidos:string,nombres:string,notas?:string}> $registros */
        $registros = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($registros)) {
            $this->error('El JSON no contiene un arreglo válido.');

            return self::FAILURE;
        }

        $this->inicializarSecuencias();
        $dryRun = (bool) $this->option('dry-run');
        $skipExisting = (bool) $this->option('skip-existing');

        $creados = 0;
        $omitidos = 0;

        DB::transaction(function () use ($registros, $dryRun, $skipExisting, &$creados, &$omitidos): void {
            foreach ($registros as $registro) {
                $otbNombre = $this->normalizarOtb((string) ($registro['otb'] ?? $registro['zona'] ?? ''));
                $apellidos = trim($registro['apellidos'] ?? '');
                $nombres = trim($registro['nombres'] ?? '');
                $notasDoc = trim($registro['notas'] ?? '');

                if ($otbNombre === '' || $apellidos === '' || $nombres === '') {
                    $omitidos++;

                    continue;
                }

                if ($skipExisting && $this->existeAfiliado($apellidos, $nombres)) {
                    $omitidos++;

                    continue;
                }

                if ($dryRun) {
                    $creados++;

                    continue;
                }

                $otb = Otb::query()->updateOrCreate(
                    ['nombre' => $otbNombre],
                    [
                        'descripcion' => "OTB importada desde documento: {$otbNombre}",
                        'tipo' => $this->tipoOtb($otbNombre),
                        'activa' => true,
                    ],
                );

                $observaciones = 'Importado desde documento Word.';
                if ($notasDoc !== '') {
                    $observaciones .= " Notas: {$notasDoc}";
                }

                $ci = $this->siguienteCi();
                $telefono = $this->siguienteTelefono();

                $afiliado = Afiliado::query()->create([
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'ci' => $ci,
                    'telefono' => $telefono,
                    'direccion' => null,
                    'estado' => Afiliado::ESTADO_ACTIVO,
                ]);

                $afiliado->sincronizarLotePrincipal([
                    'numero_lote' => '1',
                    'otb_id' => $otb->id,
                    'fecha_afiliacion' => now()->toDateString(),
                    'forma_pago_afiliacion' => Afiliado::FORMA_CONTADO,
                    'monto_afiliacion' => 3500,
                    'saldo_afiliacion' => 0,
                    'estado' => Afiliado::ESTADO_ACTIVO,
                    'observaciones' => $observaciones,
                ]);

                $creados++;
            }
        });

        $modo = $dryRun ? ' (simulación)' : '';
        $this->info("Importación{$modo} completada: {$creados} registros, {$omitidos} omitidos.");

        return self::SUCCESS;
    }

    private function inicializarSecuencias(): void
    {
        $maxCi = (int) Afiliado::query()
            ->where('ci', 'like', '99%')
            ->max(DB::raw('CAST(ci AS UNSIGNED)'));

        if ($maxCi >= 99000001) {
            $this->ciSecuencia = $maxCi;
        }

        $maxTelefono = (int) Afiliado::query()
            ->where('telefono', 'like', '71%')
            ->max(DB::raw('CAST(telefono AS UNSIGNED)'));

        if ($maxTelefono >= 71000001) {
            $this->telefonoSecuencia = $maxTelefono;
        }
    }

    private function siguienteCi(): string
    {
        do {
            $this->ciSecuencia++;
            $ci = str_pad((string) $this->ciSecuencia, 8, '0', STR_PAD_LEFT);
        } while (Afiliado::query()->where('ci', $ci)->exists());

        return $ci;
    }

    private function siguienteTelefono(): string
    {
        do {
            $this->telefonoSecuencia++;
            $telefono = (string) $this->telefonoSecuencia;
        } while (
            Afiliado::query()->where('telefono', $telefono)->exists()
            || LoteAfiliado::query()->where('telefono', $telefono)->exists()
        );

        return $telefono;
    }

    private function normalizarOtb(string $otb): string
    {
        $otb = mb_strtoupper(trim($otb));

        return match ($otb) {
            'BARIO CENTRO' => 'BARRIO CENTRO',
            'BARIO 21' => 'BARRIO 21',
            'BARIO PRIMAVERA' => 'BARRIO PRIMAVERA',
            'S.ISINUTA', 'S. ISINUTA' => 'SINDICATO ISINUTA',
            'SIDICATO URKUPIÑA', 'SIDICATO URKUPINA' => 'SINDICATO URKUPIÑA',
            default => $otb,
        };
    }

    private function tipoOtb(string $otb): string
    {
        return str_starts_with($otb, 'SINDICATO') ? 'SINDICATO' : 'OTB';
    }

    private function existeAfiliado(string $apellidos, string $nombres): bool
    {
        return Afiliado::query()
            ->whereRaw('UPPER(apellidos) = ?', [mb_strtoupper($apellidos)])
            ->whereRaw('UPPER(nombres) = ?', [mb_strtoupper($nombres)])
            ->exists();
    }
}
