<?php

use App\Jobs\Maintenance\PruneOldChecksJob;
use App\Jobs\Maintenance\PruneOldIncidentsJob;
use App\Jobs\Maintenance\PruneOldNotificationsJob;
use App\Jobs\Monitoring\DispatchDueChecksJob;
use App\Jobs\Sla\AggregateSlaMetricsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DispatchDueChecksJob())
    ->everyMinute()
    ->withoutOverlapping()
    ->name('monitly:dispatch-due-monitor-checks');

Schedule::job(new AggregateSlaMetricsJob())
    ->daily()
    ->withoutOverlapping()
    ->name('monitly:aggregate-sla');

Schedule::job(new PruneOldChecksJob())
    ->daily()
    ->withoutOverlapping()
    ->name('monitly:prune-checks');

Schedule::job(new PruneOldIncidentsJob())
    ->daily()
    ->withoutOverlapping()
    ->name('monitly:prune-incidents');

Schedule::job(new PruneOldNotificationsJob())
    ->hourly()
    ->withoutOverlapping()
    ->name('monitly:prune-notifications');


// ==============================
// PR-added scheduled commands
// ==============================

// Scheduler heartbeat (for admin health page)
Schedule::command('system:scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('monitly:scheduler-heartbeat');

// Enforce billing plan limits regularly
Schedule::command('billing:enforce-grace')
    ->hourly()
    ->withoutOverlapping()
    ->name('monitly:billing-enforce-grace');

// Prune monitor history and keep daily aggregates
Schedule::command('monitor-history:prune')
    ->daily()
    ->withoutOverlapping()
    ->name('monitly:monitor-history-prune');

