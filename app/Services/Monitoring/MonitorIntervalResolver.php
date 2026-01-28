<?php

namespace App\Services\Monitoring;

use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;

class MonitorIntervalResolver
{
    public function resolveMinutes(Monitor $monitor): int
    {
        $allowed = config('monitly.allowed_intervals', [15, 10, 5, 2, 1]);

        if ($monitor->team_id) {
            $team = $monitor->relationLoaded('team') ? $monitor->team : Team::query()->find($monitor->team_id);

            $base = (int) config('monitly.intervals.team', 10);
            $override = $team?->addon_interval_override_minutes;

            // Faster checks addon: 5 minutes (upgrade from 10)
            if ((int) $override === 5) {
                return 5;
            }

            // Legacy overrides: 2 or 1 minutes
            if (in_array((int) $override, [2, 1], true)) {
                return (int) $override;
            }

            return in_array($base, $allowed, true) ? $base : 10;
        }

        // Individual (team_id null)
        $owner = $monitor->relationLoaded('owner') ? $monitor->owner : User::query()->find($monitor->user_id);

        $plan = strtolower((string)($owner?->billing_plan ?? 'free'));
        $base = $plan === 'pro'
            ? (int) config('monitly.intervals.pro', 10)
            : (int) config('monitly.intervals.free', 15);

        $override = $owner?->addon_interval_override_minutes;

        // Faster checks addon: 5 minutes (upgrade from 10)
        if ((int) $override === 5) {
            return 5;
        }

        // Legacy overrides: 2 or 1 minutes
        if (in_array((int) $override, [2, 1], true)) {
            return (int) $override;
        }

        return in_array($base, $allowed, true) ? $base : ($plan === 'free' ? 15 : 10);
    }
}
