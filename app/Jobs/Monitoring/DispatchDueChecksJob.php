<?php

namespace App\Jobs\Monitoring;

use App\Jobs\Monitoring\RunMonitorCheckJob;
use App\Models\Monitor;
use App\Services\Billing\PlanLimits;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchDueChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function __construct(public int $limit = 500)
    {
        $this->limit = max(1, min($this->limit, 5000));
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        $now = now();

        $monitors = Monitor::query()
            ->with(['team', 'owner'])
            ->where('paused', false)
            ->where(function ($q) use ($now) {
                $q->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', $now);
            })
            ->orderByRaw('next_check_at is null desc')
            ->orderBy('next_check_at')
            ->limit($this->limit)
            ->get();

        foreach ($monitors as $monitor) {
            $queue = $this->resolveCheckQueue($monitor);
            RunMonitorCheckJob::dispatch((int) $monitor->id)->onQueue($queue);
        }
    }

    private function resolveCheckQueue(Monitor $monitor): string
    {
        if ($monitor->team_id) {
            $plan = PlanLimits::planForTeam($monitor->team);
            return $plan === PlanLimits::PLAN_BUSINESS ? 'checks_priority' : 'checks_standard';
        }

        $plan = strtolower((string) ($monitor->owner?->billing_plan ?? PlanLimits::PLAN_FREE));

        return $plan === PlanLimits::PLAN_BUSINESS ? 'checks_priority' : 'checks_standard';
    }
}
