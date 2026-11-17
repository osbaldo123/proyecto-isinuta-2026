<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Afiliado;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AfiliadoUserService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$user = User::query()
    ->whereHas('roles', fn ($q) => $q->where('nombre', Role::SECRETARIA))
    ->first()
    ?? User::query()->where('role', Role::SECRETARIA)->first();

if (! $user) {
    echo "No hay usuario secretaria\n";
    exit(1);
}

Auth::login($user);
echo "Auth as: {$user->email}\n";

$ci = (string) random_int(20000000, 29999999);

try {
    DB::beginTransaction();

    $afiliado = Afiliado::create([
        'codigo' => Afiliado::generarCodigo(),
        'nombres' => 'Debug',
        'apellidos' => 'Store',
        'ci' => $ci,
        'telefono' => null,
        'direccion' => null,
        'estado' => 'activo',
        'observaciones' => 'debug',
    ]);
    echo "Afiliado OK id={$afiliado->id} codigo={$afiliado->codigo}\n";

    $svc = app(AfiliadoUserService::class);
    $creds = $svc->createForAfiliado($afiliado);
    echo "User OK id={$creds['user']->id} email={$creds['user']->email}\n";

    app(ActivityLogger::class)->log($user, 'Afiliados', 'debug create');
    echo "ActivityLogger OK\n";

    DB::rollBack();
    echo "Rolled back (solo prueba)\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo 'FAIL: '.get_class($e).' '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
    echo $e->getTraceAsString()."\n";
}
