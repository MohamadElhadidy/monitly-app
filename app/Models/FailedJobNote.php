<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedJobNote extends Model
{
    protected $guarded = [];

    protected $casts = [
        'ignored_at' => 'datetime',
    ];
}
