<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$email = (string) config('sistema.acceso.presidente.email');
$password = (string) config('sistema.acceso.presidente.password');

$presidente = User::query()->updateOrCreate(
    ['email' => $email],
    [
        'name' => 'Enrique Sergio Nina',
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => Role::PRESIDENTE,
        'is_active' => true,
        'is_system_account' => false,
    ]
);

$presidente->assignRole(Role::PRESIDENTE);

echo "Usuario presidente creado/actualizado.\n";
echo "Email: {$email}\n";
echo "Contraseña: {$password}\n";
echo "ID: {$presidente->id}\n";
