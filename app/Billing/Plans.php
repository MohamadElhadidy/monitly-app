<?php

namespace App\Billing;

final class Plans
{
    public const PRO = 'pro';
    public const TEAM = 'team';
    public const BUSINESS = 'business';

    public static function baseLimits(string $plan): array
    {
        return match ($plan) {
            'pro' => ['monitors' => 15, 'users' => 1, 'interval' => 10],
            'team' => ['monitors' => 50, 'users' => 5, 'interval' => 10],
            'business' => ['monitors' => 150, 'users' => 15, 'interval' => 5],
            default => ['monitors' => 3, 'users' => 1, 'interval' => 15],
        };
    }
}
