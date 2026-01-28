<?php

namespace App\Services\Notifications;

use App\Models\Monitor;
use App\Models\MonitorMemberPermission;
use App\Models\User;

class MonitorAlertRecipientResolver
{
    /**
     * Returns unique email addresses that should receive alerts for this monitor (Team rules).
     *
     * Rules:
     * - Individual: owner email
     * - Team:
     *   - Owner + Admin always
     *   - Members only if per-monitor permission receive_alerts = true
     */
    public function resolveEmails(Monitor $monitor): array
    {
        if (! $monitor->team_id) {
            $owner = $monitor->relationLoaded('owner') ? $monitor->owner : User::query()->find($monitor->user_id);
            if (! $owner || ! $owner->email) return [];
            return [$owner->email];
        }

        $team = $monitor->relationLoaded('team') ? $monitor->team : $monitor->team()->first();
        if (! $team) return [];

        $owner = User::query()->find($team->user_id);

        $teamUsers = $team->users()->get(); // includes membership role on pivot
        $all = $teamUsers;
        if ($owner && ! $teamUsers->contains('id', $owner->id)) {
            $all = $all->concat(collect([$owner]));
        }

        $memberAllowedIds = MonitorMemberPermission::query()
            ->where('monitor_id', $monitor->id)
            ->where('receive_alerts', true)
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $emails = [];
        foreach ($all as $u) {
            if (! $u || ! is_string($u->email) || $u->email === '') continue;

            $isOwner = $owner && ((int) $u->id === (int) $owner->id);
            $role = $u->membership?->role; // 'admin'|'member' for attached users; null for owner if injected separately
            $isAdmin = ($role === 'admin');

            if ($isOwner || $isAdmin) {
                $emails[] = $u->email;
                continue;
            }

            // Members must be explicitly granted receive_alerts
            if (in_array((int) $u->id, $memberAllowedIds, true)) {
                $emails[] = $u->email;
            }
        }

        return array_values(array_unique($emails));
    }
}
