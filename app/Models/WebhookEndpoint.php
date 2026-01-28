<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WebhookEndpoint extends Model
{
     use HasFactory;
     
    protected $table = 'webhook_endpoints';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',

        // Secure at rest
        'secret' => 'encrypted',

        // "retry metadata" column name (from earlier parts) is assumed as retry_meta
        'retry_meta' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
