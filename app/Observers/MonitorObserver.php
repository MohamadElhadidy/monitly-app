<?php

namespace App\Observers;

use App\Models\Monitor;
use App\Services\Audit\Audit;
use App\Services\Billing\PlanEnforcer;
use Illuminate\Validation\ValidationException;

class MonitorObserver
{
    public function creating(Monitor $monitor): void
    {
        app(PlanEnforcer::class)->assertCanCreateMonitor($monitor);

        $monitor->locked_by_plan = false;
        $monitor->locked_reason = null;
    }

    public function created(Monitor $monitor): void
    {
        Audit::log(
            action: 'monitor.created',
            subject: $monitor,
            teamId: $monitor->team_id,
            meta: [
                'name' => $monitor->name,
                'url' => $monitor->url,
                'is_public' => (bool) $monitor->is_public,
            ]
        );
    }

    public function updating(Monitor $monitor): void
    {
        if ($monitor->isDirty('paused') && $monitor->paused === false && $monitor->locked_by_plan) {
            throw ValidationException::withMessages([
                'paused' => 'This monitor is locked by your plan. Upgrade to unlock.',
            ]);
        }
    }

    public function deleted(Monitor $monitor): void
    {
        Audit::log(
            action: 'monitor.deleted',
            subject: $monitor,
            teamId: $monitor->team_id,
            meta: [
                'name' => $monitor->name,
                'url' => $monitor->url,
            ]
        );
    }
}