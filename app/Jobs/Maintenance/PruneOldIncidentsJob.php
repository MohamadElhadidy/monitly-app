<?php

namespace App\Jobs\Maintenance;

use App\Models\Incident;
use App\Models\Monitor;
use App\Services\Billing\PlanLimits;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PruneOldIncidentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        Monitor::query()
            ->with(['team', 'owner'])
            ->chunkById(100, function ($monitors) {
                foreach ($monitors as $monitor) {
                    $plan = $monitor->team
                        ? PlanLimits::planForTeam($monitor->team)
                        : strtolower((string) ($monitor->owner?->billing_plan ?? PlanLimits::PLAN_FREE));

                    $retentionDays = PlanLimits::incidentsRetentionDays($plan);
                    $cutoff = now()->subDays($retentionDays);

                    Incident::query()
                        ->where('monitor_id', $monitor->id)
                        ->whereNotNull('recovered_at')
                        ->where('created_at', '<', $cutoff)
                        ->delete();
                }
            });
    }
}
