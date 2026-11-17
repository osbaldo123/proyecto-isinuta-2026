<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishTimestamps;
use Illuminate\Database\Eloquent\Model;

class ReciboSequence extends Model
{
    use HasSpanishTimestamps;

    protected $fillable = [
        'year',
        'last_number',
    ];
}
