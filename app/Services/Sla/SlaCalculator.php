<?php

namespace App\Services\Sla;

use App\Models\Incident;
use App\Models\Monitor;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Carbon\CarbonInterface;


class SlaCalculator
{
    /**
     * Returns:
     * [
     *   'window_start' => Carbon,
     *   'window_end' => Carbon,
     *   'window_seconds' => int,
     *   'downtime_seconds' => int,
     *   'uptime_pct' => float (4dp),
     *   'incident_count' => int,
     *   'mttr_seconds' => int|null,
     * ]
     */
 

public function forMonitor(
    Monitor $monitor,
    ?CarbonInterface $now = null,
    int $windowDays = 30
): array {
    $stats = $this->forMonitors(collect([$monitor]), $now, $windowDays);

    return $stats['per_monitor'][$monitor->id]
        ?? $this->emptyStats(
            $this->windowStart($now, $windowDays),
            $now ?? now()
        );
}


public function forMonitors(Collection $monitors, ?CarbonInterface $now = null, int $windowDays = 30): array
{
    $now = $now ? $now->toMutable() : now();
    $start = $this->windowStart($now, $windowDays);

    $monitors = $monitors->filter(fn ($m) => $m instanceof Monitor)->values();

    $perMonitor = [];
    if ($monitors->count() === 0) {
        return [
            'window_start' => $start,
            'window_end' => $now,
            'per_monitor' => [],
            'aggregate' => $this->aggregateFromPerMonitor([]),
        ];
    }

    $effectiveStartById = [];
    $windowSecondsById = [];

    foreach ($monitors as $m) {
        $mStart = $start->copy();

        if ($m->created_at) {
            $created = $m->created_at instanceof CarbonInterface
                ? $m->created_at->toMutable()
                : Carbon::parse($m->created_at);

            if ($created->greaterThan($mStart)) {
                $mStart = $created;
            }
        }

        $effectiveStartById[$m->id] = $mStart;
        $windowSecondsById[$m->id] = max(1, $mStart->diffInSeconds($now));
    }

    $ids = $monitors->pluck('id')->all();

    $incidents = Incident::query()
        ->whereIn('monitor_id', $ids)
        ->where('sla_counted', true)
        ->where('started_at', '<=', $now)
        ->where(function ($q) use ($start) {
            $q->whereNull('recovered_at')
              ->orWhere('recovered_at', '>=', $start);
        })
        ->get(['id', 'monitor_id', 'started_at', 'recovered_at']);

    $grouped = $incidents->groupBy('monitor_id');

    foreach ($monitors as $m) {
        $mStart = $effectiveStartById[$m->id];
        $mWindowSeconds = $windowSecondsById[$m->id];

        $downSeconds = 0;
        $incidentCount = 0;

        $mttrSum = 0;
        $mttrCount = 0;

        /** @var Collection<int, Incident> $rows */
        $rows = $grouped->get($m->id, collect());

        foreach ($rows as $inc) {
            if (! $inc->started_at) continue;

            $iStart = $inc->started_at instanceof CarbonInterface
                ? $inc->started_at->toMutable()
                : Carbon::parse($inc->started_at);

            $iEnd = $inc->recovered_at
                ? ($inc->recovered_at instanceof CarbonInterface
                    ? $inc->recovered_at->toMutable()
                    : Carbon::parse($inc->recovered_at))
                : $now->copy();

            if ($iEnd->lessThan($mStart)) {
                continue;
            }

            $effStart = $iStart->lessThan($mStart) ? $mStart->copy() : $iStart;
            $effEnd = $iEnd->greaterThan($now) ? $now->copy() : $iEnd;

            if ($effEnd->lessThanOrEqualTo($effStart)) {
                continue;
            }

            $incidentCount++;
            $dur = $effStart->diffInSeconds($effEnd);
            $downSeconds += $dur;

            if ($inc->recovered_at) {
                $mttrSum += $dur;
                $mttrCount++;
            }
        }

        $uptimeRatio = 1 - min(1, max(0, $downSeconds) / $mWindowSeconds);
        $uptimePct = round($uptimeRatio * 100, 4);

        $perMonitor[$m->id] = [
            'window_start' => $mStart,
            'window_end' => $now,
            'window_seconds' => $mWindowSeconds,
            'downtime_seconds' => (int) $downSeconds,
            'uptime_pct' => (float) $uptimePct,
            'incident_count' => (int) $incidentCount,
            'mttr_seconds' => $mttrCount > 0 ? (int) round($mttrSum / $mttrCount) : null,
        ];
    }

    return [
        'window_start' => $start,
        'window_end' => $now,
        'per_monitor' => $perMonitor,
        'aggregate' => $this->aggregateFromPerMonitor($perMonitor),
    ];
}


    private function windowStart(Carbon $now, int $windowDays): Carbon
    {
        return $now->copy()->subDays($windowDays);
    }

    private function emptyStats(Carbon $start, Carbon $now): array
    {
        return [
            'window_start' => $start,
            'window_end' => $now,
            'window_seconds' => max(1, $start->diffInSeconds($now)),
            'downtime_seconds' => 0,
            'uptime_pct' => 100.0000,
            'incident_count' => 0,
            'mttr_seconds' => null,
        ];
    }

    private function aggregateFromPerMonitor(array $perMonitor): array
    {
        $totalWindow = 0;
        $totalDown = 0;

        $totalIncidents = 0;

        $mttrSum = 0;
        $mttrCount = 0;

        foreach ($perMonitor as $stats) {
            $totalWindow += (int) ($stats['window_seconds'] ?? 0);
            $totalDown += (int) ($stats['downtime_seconds'] ?? 0);
            $totalIncidents += (int) ($stats['incident_count'] ?? 0);

            if (! is_null($stats['mttr_seconds'])) {
                $mttrSum += (int) $stats['mttr_seconds'];
                $mttrCount++;
            }
        }

        $totalWindow = max(1, $totalWindow);
        $uptimeRatio = 1 - min(1, max(0, $totalDown) / $totalWindow);
        $uptimePct = round($uptimeRatio * 100, 4);

        return [
            'window_seconds' => $totalWindow,
            'downtime_seconds' => (int) $totalDown,
            'uptime_pct' => (float) $uptimePct,
            'incident_count' => (int) $totalIncidents,
            'mttr_seconds' => $mttrCount > 0 ? (int) round($mttrSum / $mttrCount) : null,
        ];
    }
}
