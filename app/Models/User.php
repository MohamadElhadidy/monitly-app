<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Paddle\Billable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use Billable;
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'next_bill_at' => 'datetime',
             'last_bill_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'first_paid_at' => 'datetime',
            'refund_override_until' => 'datetime',
            'checkout_in_progress_until' => 'datetime',
        ];
    }
    
    


    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Individual monitors owned by this user (team_id is null).
     */
    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }

    /**
     * Get user's current billing plan
     */
    public function getBillingPlan(): string
    {
        return $this->billing_plan ?? 'free';
    }

    /**
     * Get user's current billing status
     */
    public function getBillingStatus(): string
    {
        return $this->billing_status ?? 'free';
    }

    /**
     * Check if user is subscribed
     */
    public function isSubscribed(): bool
    {
        return in_array($this->billing_status, ['active', 'past_due', 'canceling'], true);
    }

    /**
     * Check if user is in grace period
     */
    public function isInGrace(): bool
    {
        return $this->billing_status === 'past_due';
    }
}
