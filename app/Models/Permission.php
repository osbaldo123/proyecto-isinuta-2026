<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasSpanishTimestamps;

    protected $fillable = ['nombre', 'descripcion', 'grupo'];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role')->withTimestamps('fecha_creacion', 'fecha_actualizacion');
    }
}
