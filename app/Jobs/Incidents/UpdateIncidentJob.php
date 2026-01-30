<?php

namespace App\Jobs\Incidents;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateIncidentJob implements ShouldQueue
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
        $monitor = Monitor::query()->find($this->monitorId);
        if (! $monitor) {
            return;
        }

        $incident = Incident::query()
            ->where('monitor_id', $monitor->id)
            ->whereNull('recovered_at')
            ->orderByDesc('started_at')
            ->first();

        if (! $incident) {
            return;
        }

        if ($incident->started_at) {
            $incident->downtime_seconds = $incident->started_at->diffInSeconds(now());
        }

        $incident->save();
    }
}
