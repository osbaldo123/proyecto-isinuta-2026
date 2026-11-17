<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogger
{
    public function log(?User $user, string $modulo, string $accion): void
    {
        ActivityLog::create([
            'user_id' => $user?->id,
            'usuario' => $user?->name ?? 'Sistema',
            'modulo' => $modulo,
            'accion' => $accion,
            'fecha_creacion' => now(),
        ]);
    }
}
