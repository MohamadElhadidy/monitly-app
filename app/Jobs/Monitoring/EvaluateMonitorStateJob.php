<?php

namespace App\Jobs\Monitoring;

use App\Jobs\Incidents\OpenIncidentJob;
use App\Jobs\Incidents\ResolveIncidentJob;
use App\Jobs\Incidents\UpdateIncidentJob;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateMonitorStateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 20;

    public function __construct(public int $monitorId)
    {
        $this->onQueue('incidents');
    }

    public function handle(): void
    {
        $monitor = Monitor::query()
            ->with(['team', 'owner'])
            ->find($this->monitorId);

        if (! $monitor || $monitor->paused) {
            return;
        }

        $latestCheck = MonitorCheck::query()
            ->where('monitor_id', $monitor->id)
            ->orderByDesc('checked_at')
            ->first();

        if (! $latestCheck) {
            return;
        }

        $prevStatus = (string) ($monitor->last_status ?? 'unknown');
        $failures = (int) $monitor->consecutive_failures;

        if ($latestCheck->ok) {
            $failures = 0;
            $newStatus = 'up';
        } else {
            $failures = min(255, $failures + 1);
            $newStatus = $failures >= 2 ? 'down' : 'degraded';
        }

        $monitor->last_status = $newStatus;
        $monitor->consecutive_failures = $failures;
        $monitor->save();

        if ($newStatus === 'down' && $prevStatus !== 'down') {
            OpenIncidentJob::dispatch(
                monitorId: (int) $monitor->id,
                statusCode: $latestCheck->status_code,
                errorCode: $latestCheck->error_code,
                errorMessage: $latestCheck->error_message
            )->onQueue('incidents');
            return;
        }

        if ($newStatus === 'down' && $prevStatus === 'down') {
            UpdateIncidentJob::dispatch((int) $monitor->id)->onQueue('incidents');
            return;
        }

        if ($prevStatus === 'down' && $newStatus === 'up') {
            ResolveIncidentJob::dispatch((int) $monitor->id)->onQueue('incidents');
        }
    }
}
