<?php

namespace Tests\Feature;

use App\Jobs\Notifications\SendSlackSlaBreachJob;
use App\Jobs\Sla\EvaluateMonitorSlaJob;
use App\Mail\MonitorSlaBreachMail;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SlaBreachAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_pro_breach_sends_email_but_not_slack(): void
    {
        Mail::fake();
        Bus::fake([SendSlackSlaBreachJob::class]);

        $user = User::factory()->create(['email' => 'owner@test']);
        $user->billing_plan = 'pro';
        $user->save();

        $monitor = Monitor::query()->create([
            'team_id' => null,
            'user_id' => $user->id,
            'name' => 'ProMon',
            'url' => 'https://example.com',
            'paused' => false,
            'email_alerts_enabled' => true,
            'slack_alerts_enabled' => true,
            'webhook_alerts_enabled' => true,
            'last_status' => 'up',
            'consecutive_failures' => 0,
            'next_check_at' => now(),
            'sla_breached' => false,
        ]);

        // Pro target 99.5% -> allowed downtime ~ 0.5% of 30 days ~ 12,960s; use 20,000s to breach.
        Incident::query()->create([
            'monitor_id' => $monitor->id,
            'started_at' => now()->subDays(2)->subHours(6),
            'recovered_at' => now()->subDays(2),
            'downtime_seconds' => 21600,
            'cause_summary' => 'Long outage',
            'created_by' => 'system',
            'sla_counted' => true,
        ]);

        EvaluateMonitorSlaJob::dispatchSync($monitor->id);

        Mail::assertQueued(MonitorSlaBreachMail::class, function ($m) use ($monitor) {
            return $m->monitor->id === $monitor->id;
        });

        Bus::assertNotDispatched(SendSlackSlaBreachJob::class);
    }

    public function test_team_breach_sends_email_and_dispatches_slack_once_per_transition(): void
    {
        Mail::fake();
        Bus::fake([SendSlackSlaBreachJob::class]);

        $owner = User::factory()->create(['email' => 'owner@team.test']);

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'name' => 'T1',
            'billing_plan' => 'team',
        ]);

        NotificationChannel::query()->create([
            'team_id' => $team->id,
            'email_enabled' => true,
            'slack_enabled' => true,
            'webhooks_enabled' => false,
            'slack_webhook_url' => 'https://hooks.slack.com/services/XXX/YYY/ZZZ',
        ]);

        $monitor = Monitor::query()->create([
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'name' => 'TeamMon',
            'url' => 'https://example.com',
            'paused' => false,
            'email_alerts_enabled' => true,
            'slack_alerts_enabled' => true,
            'webhook_alerts_enabled' => false,
            'last_status' => 'up',
            'consecutive_failures' => 0,
            'next_check_at' => now(),
            'sla_breached' => false,
        ]);

        // Team target 99.9% -> allowed downtime ~ 2,592s; create 1 hour to breach.
        Incident::query()->create([
            'monitor_id' => $monitor->id,
            'started_at' => now()->subDays(1)->subHour(),
            'recovered_at' => now()->subDays(1),
            'downtime_seconds' => 3600,
            'cause_summary' => 'Outage',
            'created_by' => 'system',
            'sla_counted' => true,
        ]);

        EvaluateMonitorSlaJob::dispatchSync($monitor->id);

        Mail::assertQueued(MonitorSlaBreachMail::class);

        Bus::assertDispatched(SendSlackSlaBreachJob::class, function (SendSlackSlaBreachJob $job) use ($team, $monitor) {
            return $job->teamId === $team->id && $job->monitorId === $monitor->id;
        });

        // Second evaluation while still breached should not dispatch again (dedupe)
        EvaluateMonitorSlaJob::dispatchSync($monitor->id);
        Bus::assertDispatchedTimes(SendSlackSlaBreachJob::class, 1);
    }
}
