<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorCheckDailyAggregate extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'day',
        'total_checks',
        'ok_checks',
        'avg_response_time_ms',
    ];

    protected $casts = [
        'day' => 'date',
        'total_checks' => 'integer',
        'ok_checks' => 'integer',
        'avg_response_time_ms' => 'integer',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
