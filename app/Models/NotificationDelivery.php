<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
