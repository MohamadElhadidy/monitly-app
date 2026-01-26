<?php

namespace App\Console\Commands;

use App\Jobs\CheckMonitorUrl;
use App\Models\Monitor;
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

        $now = now();

        $monitors = Monitor::query()
            ->where('paused', false)
            ->where(function ($q) use ($now) {
                $q->whereNull('next_check_at')
                  ->orWhere('next_check_at', '<=', $now);
            })
            ->orderByRaw('next_check_at is null desc')
            ->orderBy('next_check_at')
            ->limit($limit)
            ->get(['id']);

        $count = 0;
        foreach ($monitors as $m) {
            CheckMonitorUrl::dispatch((int) $m->id)->onQueue('checks');
            $count++;
        }

        $this->info("Dispatched {$count} monitor check job(s).");

        return self::SUCCESS;
    }
}