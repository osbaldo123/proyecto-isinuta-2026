<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Http\Requests\StoreAfiliadoRequest;
use App\Models\Afiliado;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo 'DB: '.config('database.connections.'.config('database.default').'.database').PHP_EOL;
echo 'Afiliados: '.Afiliado::count().PHP_EOL;
echo 'Users: '.User::count().PHP_EOL;
echo 'foto_perfil: '.(Schema::hasColumn('afiliados', 'foto_perfil') ? 'yes' : 'no').PHP_EOL;

foreach (DB::select('SHOW INDEX FROM users WHERE Column_name = ?', ['ci']) as $i) {
    echo "users index: {$i->Key_name} unique=".($i->Non_unique ? '0' : '1').PHP_EOL;
}

// Simulate store validation + create like the form does
try {
    $payload = [
        'nombres' => 'Prueba',
        'apellidos' => 'Registro Test',
        'ci' => '55556666',
        'complemento' => null,
        'telefono' => '71112233',
        'email' => null,
        'direccion' => null,
        'zona_id' => null,
        'fecha_afiliacion' => now()->toDateString(),
        'forma_pago_afiliacion' => 'contado',
        'monto_afiliacion' => 3500,
        'saldo_afiliacion' => 0,
        'estado' => 'activo',
        'observaciones' => null,
        'servicios' => [],
    ];

    $request = Request::create('/afiliados', 'POST', $payload);
    $formRequest = StoreAfiliadoRequest::createFrom($request);
    $formRequest->setContainer($app)->setRedirector($app->make('redirect'));
    $formRequest->validateResolved();
    echo "Validation OK\n";
} catch (Throwable $e) {
    echo 'Validation FAIL: '.$e->getMessage().PHP_EOL;
}

// Check phone uniqueness conflicts from inventados
$dupPhones = DB::table('afiliados')
    ->select('telefono', DB::raw('COUNT(*) as c'))
    ->whereNotNull('telefono')
    ->groupBy('telefono')
    ->having('c', '>', 1)
    ->limit(5)
    ->get();
echo 'Dup phones: '.$dupPhones->count().PHP_EOL;

// Check if inventados phones block new ones
echo 'Phone 71112233 exists: '.(Afiliado::where('telefono', '71112233')->exists() ? 'yes' : 'no').PHP_EOL;
echo 'CI 55556666 exists: '.(Afiliado::where('ci', '55556666')->exists() ? 'yes' : 'no').PHP_EOL;
