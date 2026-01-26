<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SystemSchedulerHeartbeatCommand extends Command
{
    protected $signature = 'system:scheduler-heartbeat';
    protected $description = 'Writes a heartbeat timestamp for the scheduler health view.';

    public function handle(): int
    {
        Cache::put('system:scheduler_heartbeat', now()->toIso8601String(), now()->addMinutes(10));

        $this->info('Heartbeat written.');

        return self::SUCCESS;
    }
}