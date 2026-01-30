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
