<?php

namespace App\Services\Sla;

use App\Models\Monitor;
use App\Models\User;

class SlaTargetResolver
{
    public function targetPctForMonitor(Monitor $monitor): float
    {
        $plan = $this->planForMonitor($monitor);

        return match ($plan) {
            'team' => 99.9,
            'business' => 99.9,
            'pro'  => 99.5,
            default => 99.0, // free
        };
    }

    public function targetPctForUser(User $user): float
    {
        $plan = strtolower((string) ($user->billing_plan ?? 'free'));

        return match ($plan) {
            'pro' => 99.5,
            'business' => 99.9,
            default => 99.0,
        };
    }

    public function planForMonitor(Monitor $monitor): string
    {
        if ($monitor->team_id && $monitor->team) {
            return strtolower((string) ($monitor->team->billing_plan ?? 'team'));
        }

        if ($monitor->owner) {
            return strtolower((string) ($monitor->owner->billing_plan ?? 'free'));
        }

        return 'free';
    }
}
