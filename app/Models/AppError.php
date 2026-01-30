<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppError extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'muted_until' => 'datetime',
    ];
}
