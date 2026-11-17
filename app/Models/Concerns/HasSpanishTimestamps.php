<?php

namespace App\Models\Concerns;

trait HasSpanishTimestamps
{
    public function getCreatedAtColumn(): string
    {
        return 'fecha_creacion';
    }

    public function getUpdatedAtColumn(): string
    {
        return 'fecha_actualizacion';
    }
}
