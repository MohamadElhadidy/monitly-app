<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingRefund extends Model
{
    protected $guarded = [];

    protected $casts = [
        'refunded_at' => 'datetime',
    ];
}
