<?php

namespace App\Jobs\Incidents;

use App\Jobs\Notifications\BuildNotificationBatchJob;
use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OpenIncidentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 20;

    public function __construct(
        public int $monitorId,
        public ?int $statusCode = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {
        $this->onQueue('incidents');
    }

    public function handle(DatabaseManager $db): void
    {
        $monitor = Monitor::query()->find($this->monitorId);
        if (! $monitor || $monitor->paused) {
            return;
        }

        $incident = $db->transaction(function () use ($monitor) {
            $open = Incident::query()
                ->where('monitor_id', $monitor->id)
                ->whereNull('recovered_at')
                ->lockForUpdate()
                ->first();

            if ($open) {
                return $open;
            }

            $incident = new Incident();
            $incident->monitor_id = $monitor->id;
            $incident->started_at = now();
            $incident->recovered_at = null;
            $incident->downtime_seconds = null;
            $incident->cause_summary = $this->truncateCause($this->causeSummary());
            $incident->created_by = 'system';
            $incident->sla_counted = true;
            $incident->save();

            return $incident;
        });

        if ($incident && $incident->wasRecentlyCreated) {
            BuildNotificationBatchJob::dispatch((int) $monitor->id, (int) $incident->id, 'monitor.down')
                ->onQueue('notifications');
        }
    }

    private function causeSummary(): string
    {
        if ($this->statusCode) {
            return "HTTP {$this->statusCode}";
        }

        if ($this->errorCode) {
            return $this->errorCode;
        }

        return $this->errorMessage ?: 'DOWN';
    }

    private function truncateCause(string $msg): string
    {
        $max = (int) config('monitly.http.max_cause_len', 255);
        $msg = trim($msg);
        if ($msg === '') return 'Incident';
        if (mb_strlen($msg) <= $max) return $msg;
        return mb_substr($msg, 0, $max);
    }
}
