<?php

namespace App\Jobs\Sla;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchSlaEvaluationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('sla');
    }

    public function handle(): void
    {
        Monitor::query()
            ->where('paused', false)
            ->orderBy('id')
            ->chunkById(200, function ($chunk) {
                foreach ($chunk as $monitor) {
                    EvaluateMonitorSlaJob::dispatch((int) $monitor->id)->onQueue('sla');
                }
            });
    }
}
