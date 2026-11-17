<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$keepEmails = [
    (string) config('sistema.acceso.secretaria.email'),
    (string) config('sistema.acceso.presidente.email'),
];

$toDelete = User::query()
    ->whereNotIn('email', $keepEmails)
    ->get();

foreach ($toDelete as $user) {
    echo "Eliminando: {$user->id} {$user->email}\n";
    $user->delete();
}

$remaining = User::query()->orderBy('id')->get(['id', 'email', 'role']);

echo "\nUsuarios restantes:\n";
foreach ($remaining as $user) {
    echo "  {$user->id} | {$user->email} | {$user->role}\n";
}

echo "\nTotal: {$remaining->count()}\n";
