<?php

namespace App\Services\Monitoring;

use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\PlanLimits;

class MonitorIntervalResolver
{
    public function resolveMinutes(Monitor $monitor): int
    {
        $allowed = config('monitly.allowed_intervals', [15, 10, 5, 2, 1]);

        if ($monitor->team_id) {
            $team = $monitor->relationLoaded('team') ? $monitor->team : Team::query()->find($monitor->team_id);
            $plan = PlanLimits::planForTeam($team);
            $base = (int) config("monitly.intervals.{$plan}", PlanLimits::baseIntervalMinutes($plan));

            return in_array($base, $allowed, true) ? $base : PlanLimits::baseIntervalMinutes($plan);
        }

        // Individual (team_id null)
        $owner = $monitor->relationLoaded('owner') ? $monitor->owner : User::query()->find($monitor->user_id);

        $plan = strtolower((string)($owner?->billing_plan ?? PlanLimits::PLAN_FREE));
        $base = (int) config("monitly.intervals.{$plan}", PlanLimits::baseIntervalMinutes($plan));

        return in_array($base, $allowed, true) ? $base : PlanLimits::baseIntervalMinutes($plan);
    }
}
