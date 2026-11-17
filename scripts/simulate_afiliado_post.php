<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Afiliado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$user = User::query()->where('role', Role::SECRETARIA)->orWhereHas('roles', fn ($q) => $q->where('nombre', Role::SECRETARIA))->first();
if (! $user) {
    echo "No secretaria user\n";
    exit(1);
}
Auth::login($user);

$ci = (string) random_int(10000000, 99999999);

// Simulate multipart without foto (empty string like FormData null)
$request = Request::create('/afiliados', 'POST', [
    'nombres' => 'Test FormData',
    'apellidos' => 'Sin Foto',
    'ci' => $ci,
    'complemento' => '',
    'telefono' => '',
    'email' => '',
    'foto_perfil' => '',
    'direccion' => '',
    'zona_id' => '',
    'fecha_afiliacion' => now()->toDateString(),
    'forma_pago_afiliacion' => 'contado',
    'monto_afiliacion' => '3500',
    'saldo_afiliacion' => '0',
    'estado' => 'activo',
    'observaciones' => '',
    'servicios' => [],
], [], [], [
    'CONTENT_TYPE' => 'multipart/form-data',
    'HTTP_ACCEPT' => 'text/html, application/xhtml+xml',
    'HTTP_X_INERTIA' => 'true',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
]);
$request->setLaravelSession($app['session.store']);
$request->setUserResolver(fn () => $user);

try {
    $response = $kernel->handle($request);
    echo 'Status: '.$response->getStatusCode().PHP_EOL;
    echo 'Location: '.$response->headers->get('Location').PHP_EOL;
    $content = $response->getContent();
    if (str_contains((string) $content, 'errors')) {
        $json = json_decode((string) $content, true);
        if (isset($json['props']['errors'])) {
            echo 'Errors: '.json_encode($json['props']['errors'], JSON_UNESCAPED_UNICODE).PHP_EOL;
        }
        if (isset($json['errors'])) {
            echo 'Inertia errors: '.json_encode($json['errors'], JSON_UNESCAPED_UNICODE).PHP_EOL;
        }
    }
    echo 'Exists after: '.(Afiliado::where('ci', $ci)->exists() ? 'yes' : 'no').PHP_EOL;
} catch (Throwable $e) {
    echo 'EXCEPTION: '.$e->getMessage().PHP_EOL;
    echo $e->getFile().':'.$e->getLine().PHP_EOL;
}
