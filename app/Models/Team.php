<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Paddle\Billable;


class Team extends JetstreamTeam
{
    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;
    use Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
            
            
            // Billing timestamps
            'next_bill_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'first_paid_at' => 'datetime',
            'refund_override_until' => 'datetime',
        ];
    }
    
    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }

    public function notificationChannel(): HasOne
    {
        return $this->hasOne(NotificationChannel::class);
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }
    
    public function getBillingPlan(): string
{
    return $this->billing_plan ?? 'free';
}

public function getBillingStatus(): string
{
    return $this->billing_status ?? 'free';
}

public function isSubscribed(): bool
{
    return in_array($this->billing_status, ['active']);
}

public function isInGrace(): bool
{
    if ($this->billing_status !== 'grace') {
        return false;
    }

    if (!$this->grace_ends_at) {
        return true;
    }

    return now()->isBefore($this->grace_ends_at);
}
}
