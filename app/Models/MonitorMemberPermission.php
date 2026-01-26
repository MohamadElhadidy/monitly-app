<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorMemberPermission extends Model
{
    use HasFactory;

    protected $table = 'monitor_member_permissions';

   protected $guarded = [];



    protected $casts = [
        'monitor_id' => 'integer',
        'user_id' => 'integer',
        'view_logs' => 'boolean',
        'receive_alerts' => 'boolean',
        'pause_resume' => 'boolean',
        'edit_settings' => 'boolean',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasAnyPermission(): bool
    {
        return (bool) ($this->view_logs || $this->receive_alerts || $this->pause_resume || $this->edit_settings);
    }
}
