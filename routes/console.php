<?php

use App\Jobs\Monitoring\DispatchDueMonitorChecksJob;
use App\Jobs\Sla\DispatchSlaEvaluationsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DispatchDueMonitorChecksJob())
    ->everyMinute()
    ->withoutOverlapping()
    ->name('monitly:dispatch-due-monitor-checks');

Schedule::job(new DispatchSlaEvaluationsJob())
    ->hourly()
    ->withoutOverlapping()
    ->name('monitly:evaluate-sla');

// Scheduler heartbeat (for admin health page)
Schedule::command('system:scheduler-heartbeat')->everyMinute();

// Enforce billing plan limits regularly
Schedule::command('billing:enforce-grace')->hourly();

// Prune monitor history and keep daily aggregates
Schedule::command('monitor-history:prune')->daily();
