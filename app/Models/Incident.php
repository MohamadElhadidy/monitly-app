<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'monitor_id',
        'started_at',
        'recovered_at',
        'downtime_seconds',
        'cause_summary',
        'created_by',
        'sla_counted',
    ];

    protected $casts = [
        'monitor_id' => 'integer',
        'started_at' => 'datetime',
        'recovered_at' => 'datetime',
        'downtime_seconds' => 'integer',
        'sla_counted' => 'boolean',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function getIsOpenAttribute(): bool
    {
        return is_null($this->recovered_at);
    }
}
