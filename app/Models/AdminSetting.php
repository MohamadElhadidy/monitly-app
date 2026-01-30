<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'read_only_mode' => 'boolean',
        'maintenance_mode' => 'boolean',
    ];
}
