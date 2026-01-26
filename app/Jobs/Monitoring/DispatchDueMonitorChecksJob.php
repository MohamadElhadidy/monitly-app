<?php

namespace App\Jobs\Monitoring;

use App\Jobs\CheckMonitorUrl;
use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchDueMonitorChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function __construct(
        public int $limit = 500
    ) {
        $this->limit = max(1, min($this->limit, 5000));
        $this->onQueue('checks'); // dispatcher queue (separate from checks)
    }

    public function handle(): void
    {
        $now = now();

        $monitors = Monitor::query()
            ->where('paused', false)
            ->where(function ($q) use ($now) {
                $q->whereNull('next_check_at')
                  ->orWhere('next_check_at', '<=', $now);
            })
            ->orderByRaw('next_check_at is null desc')
            ->orderBy('next_check_at')
            ->limit($this->limit)
            ->get(['id']);

        foreach ($monitors as $m) {
            CheckMonitorUrl::dispatch((int) $m->id)->onQueue('checks');
        }
    }
}
