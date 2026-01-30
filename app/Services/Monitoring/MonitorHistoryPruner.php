<?php

namespace App\Services\Monitoring;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorCheckDailyAggregate;
use App\Services\Billing\PlanLimits;

class MonitorHistoryPruner
{
    public function prune(): void
    {
        Monitor::query()
            ->with(['owner', 'team'])
            ->chunkById(100, function ($monitors) {
                foreach ($monitors as $monitor) {
                    $plan = $monitor->team
                        ? PlanLimits::planForTeam($monitor->team)
                        : strtolower((string) ($monitor->owner?->billing_plan ?? PlanLimits::PLAN_FREE));

                    $historyDays = PlanLimits::historyDays($plan);
                    if (! $historyDays) {
                        continue;
                    }

                    $cutoff = now()->subDays($historyDays);

                    $this->aggregateChecks($monitor->id, $cutoff);

                    MonitorCheck::query()
                        ->where('monitor_id', $monitor->id)
                        ->where('checked_at', '<', $cutoff)
                        ->delete();
                }
            });
    }

    private function aggregateChecks(int $monitorId, $cutoff): void
    {
        $rows = MonitorCheck::query()
            ->selectRaw('DATE(checked_at) as day')
            ->selectRaw('COUNT(*) as total_checks')
            ->selectRaw('SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) as ok_checks')
            ->selectRaw('AVG(response_time_ms) as avg_response_time_ms')
            ->where('monitor_id', $monitorId)
            ->where('checked_at', '<', $cutoff)
            ->groupBy('day')
            ->get();

        foreach ($rows as $row) {
            MonitorCheckDailyAggregate::updateOrCreate(
                [
                    'monitor_id' => $monitorId,
                    'day' => $row->day,
                ],
                [
                    'total_checks' => (int) $row->total_checks,
                    'ok_checks' => (int) $row->ok_checks,
                    'avg_response_time_ms' => $row->avg_response_time_ms !== null
                        ? (int) round($row->avg_response_time_ms)
                        : null,
                ]
            );
        }
    }
}
