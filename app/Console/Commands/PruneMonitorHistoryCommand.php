<?php

namespace App\Console\Commands;

use App\Services\Monitoring\MonitorHistoryPruner;
use Illuminate\Console\Command;

class PruneMonitorHistoryCommand extends Command
{
    protected $signature = 'monitor-history:prune';

    protected $description = 'Aggregate and prune monitor checks based on plan history limits.';

    public function handle(MonitorHistoryPruner $pruner): int
    {
        $pruner->prune();

        $this->info('Monitor history pruned and aggregated.');

        return Command::SUCCESS;
    }
}
