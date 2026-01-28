<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationChannel extends Model
{
    protected $table = 'notification_channels';

    protected $guarded = [];

    protected $casts = [
        'email_enabled' => 'boolean',
        'slack_enabled' => 'boolean',
        'webhooks_enabled' => 'boolean',

        // Secure at rest
        'slack_webhook_url' => 'encrypted',

        'slack_retry_meta' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
