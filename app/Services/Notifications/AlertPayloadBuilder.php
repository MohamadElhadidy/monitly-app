<?php

namespace App\Services\Notifications;

use App\Models\Incident;
use App\Models\Monitor;

class AlertPayloadBuilder
{
    /**
     * Event names:
     * - monitor.down
     * - monitor.recovered
     */
    public function build(string $event, Monitor $monitor, ?Incident $incident): array
    {
        $incidentId = $incident?->id;
        $startedAt = $incident?->started_at?->toIso8601String();
        $recoveredAt = $incident?->recovered_at?->toIso8601String();

        $downtimeSeconds = $incident?->downtime_seconds;
        if (is_null($downtimeSeconds) && $incident?->started_at && $incident?->recovered_at) {
            $downtimeSeconds = $incident->started_at->diffInSeconds($incident->recovered_at);
        }

        return [
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            'monitor' => [
                'id' => (int) $monitor->id,
                'name' => (string) $monitor->name,
                'url' => (string) $monitor->url,
                'status' => (string) ($monitor->last_status ?? 'unknown'),
                'team_id' => $monitor->team_id ? (int) $monitor->team_id : null,
                'user_id' => (int) $monitor->user_id,
                'is_public' => (bool) $monitor->is_public,
            ],
            'incident' => [
                'id' => $incidentId ? (int) $incidentId : null,
                'started_at' => $startedAt,
                'recovered_at' => $recoveredAt,
                'downtime_seconds' => is_null($downtimeSeconds) ? null : (int) $downtimeSeconds,
                'cause_summary' => $incident?->cause_summary,
                'sla_counted' => $incident ? (bool) $incident->sla_counted : null,
            ],
        ];
    }

    public function humanDuration(?int $seconds): string
    {
        $seconds = (int) ($seconds ?? 0);
        if ($seconds <= 0) return '0s';

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        $parts = [];
        if ($h > 0) $parts[] = "{$h}h";
        if ($m > 0) $parts[] = "{$m}m";
        if ($s > 0) $parts[] = "{$s}s";

        return implode(' ', $parts);
    }
}
