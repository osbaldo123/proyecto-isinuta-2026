<?php

namespace App\Console\Commands;

use App\Services\AfiliadoUserService;
use Illuminate\Console\Command;

class SyncAfiliadoUsersCommand extends Command
{
    protected $signature = 'usuarios:sync-afiliados';

    protected $description = 'Crea usuarios de consulta para afiliados que aún no tienen cuenta';

    public function handle(AfiliadoUserService $afiliadoUser): int
    {
        $created = $afiliadoUser->syncMissingUsers();

        $this->info("Usuarios creados: {$created}");

        return self::SUCCESS;
    }
}
