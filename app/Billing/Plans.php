<?php

namespace App\Billing;

final class Plans
{
    // Paddle Price IDs
    public const PRO  = 'pri_pro_9';
    public const TEAM = 'pri_team_29';

    // Add-ons
    public const ADDON_MONITOR_PACK = 'pri_addon_monitor_5';
    public const ADDON_SEAT_PACK    = 'pri_addon_seat_6';

    public const INTERVAL_4M = 'pri_interval_4m';
    public const INTERVAL_3M = 'pri_interval_3m';
    public const INTERVAL_2M = 'pri_interval_2m';

    public static function baseLimits(string $plan): array
    {
        return match ($plan) {
            'pro' => ['monitors' => 5,  'users' => 1, 'interval' => 10],
            'team'=> ['monitors' => 20, 'users' => 5, 'interval' => 10],
            default => ['monitors' => 1, 'users' => 1, 'interval' => 15],
        };
    }
}