<?php

namespace Tests\Feature;

use App\Jobs\Monitoring\DispatchDueChecksJob;
use App\Jobs\Monitoring\RunMonitorCheckJob;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuePriorityRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_plan_routes_to_priority_queue(): void
    {
        Queue::fake();

        $owner = User::factory()->create(['billing_plan' => 'free']);
        $freeMonitor = Monitor::factory()->individual($owner)->create([
            'paused' => false,
            'next_check_at' => now()->subMinute(),
        ]);

        $teamOwner = User::factory()->create(['billing_plan' => 'business']);
        $team = Team::factory()->create(['user_id' => $teamOwner->id, 'billing_plan' => 'business']);
        $businessMonitor = Monitor::factory()->forTeam($team, $teamOwner)->create([
            'paused' => false,
            'next_check_at' => now()->subMinute(),
        ]);

        (new DispatchDueChecksJob())->handle();

        Queue::assertPushedOn('checks_standard', RunMonitorCheckJob::class, function (RunMonitorCheckJob $job) use ($freeMonitor) {
            return $job->monitorId === $freeMonitor->id;
        });

        Queue::assertPushedOn('checks_priority', RunMonitorCheckJob::class, function (RunMonitorCheckJob $job) use ($businessMonitor) {
            return $job->monitorId === $businessMonitor->id;
        });
    }
}
