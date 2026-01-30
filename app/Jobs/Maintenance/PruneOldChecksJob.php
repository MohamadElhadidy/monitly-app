<?php

namespace App\Jobs\Maintenance;

use App\Services\Monitoring\MonitorHistoryPruner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PruneOldChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(MonitorHistoryPruner $pruner): void
    {
        $pruner->prune();
    }
}
