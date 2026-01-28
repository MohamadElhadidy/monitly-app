<?php

namespace App\Models;

use App\Services\Monitoring\MonitorIntervalResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Monitor extends Model
{
    use SoftDeletes;
    use HasFactory;
    
    protected $table = 'monitors';

    protected $casts = [
        'is_public' => 'boolean',
        'paused' => 'boolean',
        
        'email_alerts_enabled' => 'boolean',
        'slack_alerts_enabled' => 'boolean',
        'webhook_alerts_enabled' => 'boolean',
        
         'consecutive_failures' => 'integer',
         
        'next_check_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        
        
        'sla_uptime_pct_30d' => 'decimal:4',
        'sla_downtime_seconds_30d' => 'integer',
        'sla_incident_count_30d' => 'integer',
        'sla_mttr_seconds_30d' => 'integer',
        'sla_last_calculated_at' => 'datetime',
        'sla_breached' => 'boolean',
        'sla_last_breach_alert_at' => 'datetime',
    ];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Monitor $monitor) {
            $interval = app(MonitorIntervalResolver::class)->resolveMinutes($monitor);
            $monitor->next_check_at = now()->addMinutes($interval);
            $monitor->last_status = $monitor->last_status ?: 'unknown';
            $monitor->consecutive_failures = (int) ($monitor->consecutive_failures ?? 0);
            $monitor->paused = (bool) ($monitor->paused ?? false);
            $monitor->is_public = (bool) ($monitor->is_public ?? false);
            $monitor->email_alerts_enabled = (bool) ($monitor->email_alerts_enabled ?? true);
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class, 'monitor_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'monitor_id');
    }

    public function latestCheck(): HasOne
    {
        return $this->hasOne(MonitorCheck::class, 'monitor_id')->latestOfMany('checked_at');
    }

    public function openIncident(): HasOne
    {
        return $this->hasOne(Incident::class, 'monitor_id')->whereNull('recovered_at')->latestOfMany('started_at');
    }

    public function memberPermissions(): HasMany
    {
        return $this->hasMany(MonitorMemberPermission::class, 'monitor_id');
    }

    public function isTeamMonitor(): bool
    {
        return ! is_null($this->team_id);
    }
}
