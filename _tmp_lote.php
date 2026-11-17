<?php

use App\Models\LoteAfiliado;
use App\Models\Multa;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = LoteAfiliado::query()
    ->whereHas('multas', fn ($q) => $q->where('estado', Multa::ESTADO_PENDIENTE)->where('motivo', 'like', 'mora%'))
    ->orderByDesc('id')
    ->value('id');

echo $id ?: LoteAfiliado::query()->orderByDesc('id')->value('id');
