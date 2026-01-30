<?php

namespace App\Console\Commands;

use App\Jobs\Monitoring\DispatchDueChecksJob;
use Illuminate\Console\Command;

class DispatchDueMonitorChecks extends Command
{
    protected $signature = 'monitly:dispatch-due-checks {--limit=500 : Max monitors to dispatch per run}';
    protected $description = 'Dispatch queued monitor checks for monitors that are due (next_check_at <= now).';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 1) $limit = 1;
        if ($limit > 5000) $limit = 5000;

        DispatchDueChecksJob::dispatch($limit)->onQueue('maintenance');

        $this->info("Queued dispatch job for {$limit} monitor check(s).");

        return self::SUCCESS;
    }
}
