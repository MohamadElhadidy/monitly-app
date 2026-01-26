<?php

namespace App\Services\Monitoring;

use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;

class MonitorIntervalResolver
{
    public function resolveMinutes(Monitor $monitor): int
    {
        $allowed = config('monitly.allowed_intervals', [15, 5, 2, 1]);

        if ($monitor->team_id) {
            $team = $monitor->relationLoaded('team') ? $monitor->team : Team::query()->find($monitor->team_id);

            $base = (int) config('monitly.intervals.team', 5);
            $override = $team?->interval_override_minutes;

            if (in_array((int) $override, [2, 1], true)) {
                return (int) $override;
            }

            return in_array($base, $allowed, true) ? $base : 5;
        }

        // Individual (team_id null)
        $owner = $monitor->relationLoaded('owner') ? $monitor->owner : User::query()->find($monitor->user_id);

        $plan = strtolower((string)($owner?->billing_plan ?? 'free'));
        $base = $plan === 'pro'
            ? (int) config('monitly.intervals.pro', 5)
            : (int) config('monitly.intervals.free', 15);

        $override = $owner?->interval_override_minutes;

        // Only allow 2/1 override for Pro later; for now we only validate it is 2/1.
        if (in_array((int) $override, [2, 1], true)) {
            return (int) $override;
        }

        return in_array($base, $allowed, true) ? $base : 15;
    }
}
