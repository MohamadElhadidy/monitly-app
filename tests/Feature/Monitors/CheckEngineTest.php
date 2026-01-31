<?php

namespace Tests\Feature\Monitors;

use App\Jobs\Monitoring\DispatchDueChecksJob;
use App\Jobs\Monitoring\EvaluateMonitorStateJob;
use App\Jobs\Monitoring\RunMonitorCheckJob;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Phase 6 Test: Business plan checks route to checks_priority queue
     */
    public function test_business_checks_route_to_priority_queue(): void
    {
        Queue::fake();

        $freeUser = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => 'free',
        ]);

        $freeMonitor = Monitor::factory()->create([
            'user_id' => $freeUser->id,
            'team_id' => null,
            'paused' => false,
            'next_check_at' => now()->subMinute(),
        ]);

        $businessOwner = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_BUSINESS,
            'billing_status' => 'active',
        ]);

        $businessTeam = Team::factory()->create([
            'user_id' => $businessOwner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_BUSINESS,
            'billing_status' => 'active',
        ]);

        $businessMonitor = Monitor::factory()->create([
            'user_id' => $businessOwner->id,
            'team_id' => $businessTeam->id,
            'paused' => false,
            'next_check_at' => now()->subMinute(),
        ]);

        (new DispatchDueChecksJob())->handle();

        Queue::assertPushedOn('checks_standard', RunMonitorCheckJob::class, function ($job) use ($freeMonitor) {
            return $job->monitorId === $freeMonitor->id;
        });

        Queue::assertPushedOn('checks_priority', RunMonitorCheckJob::class, function ($job) use ($businessMonitor) {
            return $job->monitorId === $businessMonitor->id;
        });
    }

    /**
     * Phase 6 Test: Team plan checks route to checks_standard queue
     */
    public function test_team_checks_route_to_standard_queue(): void
    {
        Queue::fake();

        $owner = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_TEAM,
            'billing_status' => 'active',
        ]);

        $monitor = Monitor::factory()->create([
            'user_id' => $owner->id,
            'team_id' => $team->id,
            'paused' => false,
            'next_check_at' => now()->subMinute(),
        ]);

        (new DispatchDueChecksJob())->handle();

        Queue::assertPushedOn('checks_standard', RunMonitorCheckJob::class, function ($job) use ($monitor) {
            return $job->monitorId === $monitor->id;
        });
    }

    /**
     * Phase 6 Test: Paused monitor does not write new checks
     */
    public function test_paused_monitor_does_not_write_checks(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
            'paused' => true,
            'url' => 'https://example.com',
        ]);

        $initialCheckCount = MonitorCheck::where('monitor_id', $monitor->id)->count();

        $job = new RunMonitorCheckJob($monitor->id);
        $job->handle(
            app(\App\Services\Monitoring\MonitorHttpChecker::class),
            app(\App\Services\Monitoring\MonitorIntervalResolver::class)
        );

        $finalCheckCount = MonitorCheck::where('monitor_id', $monitor->id)->count();

        $this->assertEquals($initialCheckCount, $finalCheckCount);
    }

    /**
     * Phase 6 Test: Deleted monitor job exits safely
     */
    public function test_deleted_monitor_job_exits_safely(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
            'url' => 'https://example.com',
        ]);

        $monitorId = $monitor->id;
        $monitor->delete();

        $job = new RunMonitorCheckJob($monitorId);

        $this->assertNull(
            $job->handle(
                app(\App\Services\Monitoring\MonitorHttpChecker::class),
                app(\App\Services\Monitoring\MonitorIntervalResolver::class)
            )
        );

        $this->assertEquals(0, MonitorCheck::where('monitor_id', $monitorId)->count());
    }

    /**
     * Phase 6 Test: Redis lock prevents overlapping checks
     */
    public function test_redis_lock_prevents_overlapping_checks(): void
    {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
            'paused' => false,
            'url' => 'https://example.com',
        ]);

        $lock = Cache::store('redis')->lock('lock:monitor_check:' . $monitor->id, 55);
        $acquired = $lock->get();

        $this->assertTrue($acquired);

        $job = new RunMonitorCheckJob($monitor->id);
        $job->handle(
            app(\App\Services\Monitoring\MonitorHttpChecker::class),
            app(\App\Services\Monitoring\MonitorIntervalResolver::class)
        );

        $this->assertEquals(0, MonitorCheck::where('monitor_id', $monitor->id)->count());

        $lock->release();
    }

    /**
     * Phase 6 Test: Check interval respects plan limits
     */
    public function test_check_interval_respects_plan_limits(): void
    {
        $this->assertEquals(15, PlanLimits::baseIntervalMinutes(PlanLimits::PLAN_FREE));
        $this->assertEquals(10, PlanLimits::baseIntervalMinutes(PlanLimits::PLAN_PRO));
        $this->assertEquals(10, PlanLimits::baseIntervalMinutes(PlanLimits::PLAN_TEAM));
        $this->assertEquals(5, PlanLimits::baseIntervalMinutes(PlanLimits::PLAN_BUSINESS));
    }

    /**
     * Phase 6 Test: EvaluateMonitorStateJob dispatches to incidents queue
     */
    public function test_evaluate_state_job_uses_incidents_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
        ]);

        EvaluateMonitorStateJob::dispatch($monitor->id);

        Queue::assertPushedOn('incidents', EvaluateMonitorStateJob::class);
    }

    /**
     * Phase 6 Test: Due monitors are dispatched correctly
     */
    public function test_due_monitors_are_dispatched(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
        ]);

        $dueMonitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
            'paused' => false,
            'next_check_at' => now()->subMinutes(5),
        ]);

        $futureMonitor = Monitor::factory()->create([
            'user_id' => $user->id,
            'team_id' => null,
            'paused' => false,
            'next_check_at' => now()->addMinutes(10),
        ]);

        (new DispatchDueChecksJob())->handle();

        Queue::assertPushed(RunMonitorCheckJob::class, function ($job) use ($dueMonitor) {
            return $job->monitorId === $dueMonitor->id;
        });

        Queue::assertNotPushed(RunMonitorCheckJob::class, function ($job) use ($futureMonitor) {
            return $job->monitorId === $futureMonitor->id;
        });
    }
}
