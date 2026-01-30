<?php

namespace App\Jobs\Sla;

use App\Models\Monitor;
use App\Services\Sla\SlaCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateSlaMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public int $windowDays = 30)
    {
        $this->onQueue('sla');
    }

    public function handle(SlaCalculator $calculator): void
    {
        $now = now();

        Monitor::query()
            ->with(['team', 'owner'])
            ->chunkById(100, function ($monitors) use ($calculator, $now) {
                foreach ($monitors as $monitor) {
                    $stats = $calculator->forMonitor($monitor, $now, $this->windowDays);

                    $monitor->sla_uptime_pct_30d = $stats['uptime_pct'];
                    $monitor->sla_downtime_seconds_30d = $stats['downtime_seconds'];
                    $monitor->sla_incident_count_30d = $stats['incident_count'];
                    $monitor->sla_mttr_seconds_30d = $stats['mttr_seconds'];
                    $monitor->sla_last_calculated_at = $now;
                    $monitor->save();
                }
            });
    }
}
