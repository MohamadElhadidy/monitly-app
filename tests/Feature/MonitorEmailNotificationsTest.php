<?php

namespace Tests\Feature;

use App\Jobs\CheckMonitorUrl;
use App\Mail\MonitorDownMail;
use App\Mail\MonitorRecoveredMail;
use App\Models\Monitor;
use App\Models\MonitorMemberPermission;
use App\Models\Team;
use App\Models\User;
use App\Services\Monitoring\CheckResult;
use App\Services\Monitoring\MonitorIntervalResolver;
use App\Services\Notifications\MonitorAlertRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MonitorEmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeIndividualMonitor(User $owner, array $overrides = []): Monitor
    {
        return Monitor::query()->create(array_merge([
            'team_id' => null,
            'user_id' => $owner->id,
            'name' => 'Ind Monitor',
            'url' => 'https://example.com',
            'is_public' => false,
            'paused' => false,
            'email_alerts_enabled' => true,
            'last_status' => 'up',
            'consecutive_failures' => 0,
            'next_check_at' => now()->subMinute(),
        ], $overrides));
    }

    private function makeTeamMonitor(Team $team, User $owner, array $overrides = []): Monitor
    {
        return Monitor::query()->create(array_merge([
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'name' => 'Team Monitor',
            'url' => 'https://example.com',
            'is_public' => false,
            'paused' => false,
            'email_alerts_enabled' => true,
            'last_status' => 'up',
            'consecutive_failures' => 0,
            'next_check_at' => now()->subMinute(),
        ], $overrides));
    }

    public function test_only_triggers_on_state_transitions_and_is_queued(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['email' => 'owner@example.test']);

        $monitor = $this->makeIndividualMonitor($owner);

        // Fake checker results: fail, fail (DOWN), fail (no extra DOWN), ok (RECOVERED)
        $checker = new class {
            public array $results;
            public function __construct()
            {
                $this->results = [
                    new CheckResult(false, 500, 120, 'HTTP_STATUS', '500', '93.184.216.34', 'example.com', 'https://example.com', []),
                    new CheckResult(false, 500, 130, 'HTTP_STATUS', '500', '93.184.216.34', 'example.com', 'https://example.com', []),
                    new CheckResult(false, 500, 140, 'HTTP_STATUS', '500', '93.184.216.34', 'example.com', 'https://example.com', []),
                    new CheckResult(true, 200, 110, null, null, '93.184.216.34', 'example.com', 'https://example.com', []),
                ];
            }
            public function check(string $url): CheckResult
            {
                return array_shift($this->results);
            }
        };

        $intervalResolver = new class extends MonitorIntervalResolver {
            public function resolveMinutes(\App\Models\Monitor $monitor): int { return 5; }
        };

        $recipientResolver = new MonitorAlertRecipientResolver();

        // 1) first failure => degraded, NO emails
        (new CheckMonitorUrl($monitor->id))->handle($checker, $intervalResolver, $recipientResolver);

        Mail::assertNothingQueued();

        // 2) second failure => DOWN transition => 1 DOWN email queued
        (new CheckMonitorUrl($monitor->id))->handle($checker, $intervalResolver, $recipientResolver);

        Mail::assertQueued(MonitorDownMail::class, 1);
        Mail::assertQueued(MonitorDownMail::class, function (MonitorDownMail $mail) use ($owner) {
            return $mail->hasTo($owner->email);
        });

        // 3) third failure while already down => NO extra DOWN email
        (new CheckMonitorUrl($monitor->id))->handle($checker, $intervalResolver, $recipientResolver);

        Mail::assertQueued(MonitorDownMail::class, 1);

        // 4) ok => RECOVERED transition => 1 RECOVERED email queued
        (new CheckMonitorUrl($monitor->id))->handle($checker, $intervalResolver, $recipientResolver);

        Mail::assertQueued(MonitorRecoveredMail::class, 1);
        Mail::assertQueued(MonitorRecoveredMail::class, function (MonitorRecoveredMail $mail) use ($owner) {
            return $mail->hasTo($owner->email);
        });
    }

    public function test_per_monitor_email_toggle_blocks_sending(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['email' => 'owner2@example.test']);

        $monitor = $this->makeIndividualMonitor($owner, [
            'email_alerts_enabled' => false,
            'last_status' => 'up',
            'consecutive_failures' => 0,
        ]);

        $checker = new class {
            public function check(string $url): CheckResult
            {
                return new CheckResult(false, 500, 120, 'HTTP_STATUS', '500', null, 'example.com', 'https://example.com', []);
            }
        };

        $intervalResolver = new class extends MonitorIntervalResolver {
            public function resolveMinutes(\App\Models\Monitor $monitor): int { return 5; }
        };

        $recipientResolver = new MonitorAlertRecipientResolver();

        // Run twice to reach DOWN transition, but emails disabled
        (new CheckMonitorUrl($monitor->id))->handle($checker, $intervalResolver, $recipientResolver);
        (new CheckMonitorUrl($monitor->id))->handle($checker, $intervalResolver, $recipientResolver);

        Mail::assertNothingQueued();
    }

    public function test_team_notifies_owner_admin_and_members_with_receive_alerts(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['email' => 'team-owner@example.test']);
        $admin = User::factory()->create(['email' => 'team-admin@example.test']);
        $member = User::factory()->create(['email' => 'team-member@example.test']);

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'name' => 'Alert Team',
            'billing_plan' => 'team',
        ]);

        // Attach users with Jetstream membership roles
        $team->users()->attach($admin->id, ['role' => 'admin']);
        $team->users()->attach($member->id, ['role' => 'member']);

        $monitor = $this->makeTeamMonitor($team, $owner, [
            'last_status' => 'up',
            'consecutive_failures' => 1, // next failure triggers DOWN transition
        ]);

        // Member explicitly allowed to receive alerts
        MonitorMemberPermission::query()->create([
            'monitor_id' => $monitor->id,
            'user_id' => $member->id,
            'view_logs' => false,
            'receive_alerts' => true,
            'pause_resume' => false,
            'edit_settings' => false,
        ]);

        $checker = new class {
            public function check(string $url): CheckResult
            {
                return new CheckResult(false, 500, 120, 'HTTP_STATUS', '500', null, 'example.com', 'https://example.com', []);
            }
        };

        $intervalResolver = new class extends MonitorIntervalResolver {
            public function resolveMinutes(\App\Models\Monitor $monitor): int { return 5; }
        };

        $recipientResolver = new MonitorAlertRecipientResolver();

        // This should cause DOWN transition and queue emails to all 3 recipients
        (new CheckMonitorUrl($monitor->id))->handle($checker, $intervalResolver, $recipientResolver);

        Mail::assertQueued(MonitorDownMail::class, function (MonitorDownMail $mail) use ($owner) {
            return $mail->hasTo($owner->email);
        });

        Mail::assertQueued(MonitorDownMail::class, function (MonitorDownMail $mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });

        Mail::assertQueued(MonitorDownMail::class, function (MonitorDownMail $mail) use ($member) {
            return $mail->hasTo($member->email);
        });
    }
}
