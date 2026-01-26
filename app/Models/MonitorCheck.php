<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'checked_at',
        'ok',
        'status_code',
        'response_time_ms',
        'error_code',
        'error_message',
        'resolved_ip',
        'resolved_host',
        'raw_response_meta',
    ];

    protected $casts = [
        'monitor_id' => 'integer',
        'checked_at' => 'datetime',
        'ok' => 'boolean',
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
        'raw_response_meta' => 'array',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
