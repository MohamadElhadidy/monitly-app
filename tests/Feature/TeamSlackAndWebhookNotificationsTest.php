<?php

namespace Tests\Feature;

use App\Jobs\CheckMonitorUrl;
use App\Jobs\Notifications\SendSlackAlertJob;
use App\Jobs\Notifications\SendWebhookAlertJob;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\Monitoring\CheckResult;
use App\Services\Monitoring\MonitorIntervalResolver;
use App\Services\Notifications\MonitorAlertRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamSlackAndWebhookNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.stores.redis' => ['driver' => 'array']]);
    }

    private function makeTeamWithUsers(): array
    {
        $owner = User::factory()->create(['email' => 'owner@t.test']);
        $admin = User::factory()->create(['email' => 'admin@t.test']);
        $member = User::factory()->create(['email' => 'member@t.test']);

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'name' => 'T1',
            'billing_plan' => 'team',
        ]);

        $team->users()->attach($admin->id, ['role' => 'admin']);
        $team->users()->attach($member->id, ['role' => 'member']);

        return [$team, $owner, $admin, $member];
    }

    public function test_free_or_pro_individual_never_dispatches_slack_or_webhooks(): void
    {
        Mail::fake();
        Bus::fake([SendSlackAlertJob::class, SendWebhookAlertJob::class]);

        $owner = User::factory()->create(['email' => 'solo@test']);
        $owner->billing_plan = 'pro';
        $owner->save();

        $monitor = Monitor::query()->create([
            'team_id' => null,
            'user_id' => $owner->id,
            'name' => 'Solo',
            'url' => 'https://example.com',
            'paused' => false,
            'email_alerts_enabled' => true,
            'slack_alerts_enabled' => true,
            'webhook_alerts_enabled' => true,
            'last_status' => 'up',
            'consecutive_failures' => 1,
            'next_check_at' => now()->subMinute(),
        ]);

        $checker = new class {
            public function check(string $url): CheckResult
            {
                return new CheckResult(false, 500, 100, 'HTTP_STATUS', '500', null, 'example.com', $url, []);
            }
        };

        $intervalResolver = new class extends MonitorIntervalResolver {
            public function resolveMinutes(\App\Models\Monitor $monitor): int { return 5; }
        };

        $recipientResolver = new MonitorAlertRecipientResolver();

        (new CheckMonitorUrl($monitor->id))->handle($checker, $intervalResolver, $recipientResolver);

        Bus::assertNotDispatched(SendSlackAlertJob::class);
        Bus::assertNotDispatched(SendWebhookAlertJob::class);
    }

    public function test_team_down_transition_dispatches_slack_and_webhooks_when_enabled(): void
    {
        Mail::fake();
        Bus::fake([SendSlackAlertJob::class, SendWebhookAlertJob::class]);

        [$team, $owner] = $this->makeTeamWithUsers();

        NotificationChannel::query()->create([
            'team_id' => $team->id,
            'email_enabled' => true,
            'slack_enabled' => true,
            'webhooks_enabled' => true,
            'slack_webhook_url' => 'https://hooks.slack.com/services/XXX/YYY/ZZZ',
        ]);

        $ep1 = WebhookEndpoint::query()->create([
            'team_id' => $team->id,
            'url' => 'https://example.com/webhook-1',
            'secret' => 'secret1',
            'enabled' => true,
            'last_error' => null,
            'retry_meta' => null,
        ]);

        $ep2 = WebhookEndpoint::query()->create([
            'team_id' => $team->id,
            'url' => 'https://example.com/webhook-2',
            'secret' => 'secret2',
            'enabled' => true,
            'last_error' => null,
            'retry_meta' => null,
        ]);

        $monitor = Monitor::query()->create([
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'name' => 'TeamMon',
            'url' => 'https://example.com',
            'paused' => false,
            'email_alerts_enabled' => true,
            'slack_alerts_enabled' => true,
            'webhook_alerts_enabled' => true,
            'last_status' => 'up',
            'consecutive_failures' => 1, // next fail => DOWN transition
            'next_check_at' => now()->subMinute(),
        ]);

        $checker = new class {
            public function check(string $url): CheckResult
            {
                return new CheckResult(false, 500, 100, 'HTTP_STATUS', '500', null, 'example.com', $url, []);
            }
        };

        $intervalResolver = new class extends MonitorIntervalResolver {
            public function resolveMinutes(\App\Models\Monitor $monitor): int { return 5; }
        };

        $recipientResolver = new MonitorAlertRecipientResolver();

        (new CheckMonitorUrl($monitor->id))->handle($checker, $intervalResolver, $recipientResolver);

        Bus::assertDispatched(SendSlackAlertJob::class, function (SendSlackAlertJob $job) use ($team, $monitor) {
            return $job->teamId === $team->id && $job->monitorId === $monitor->id && $job->event === 'monitor.down';
        });

        Bus::assertDispatched(SendWebhookAlertJob::class, 2);

        Bus::assertDispatched(SendWebhookAlertJob::class, function (SendWebhookAlertJob $job) use ($ep1) {
            return $job->endpointId === $ep1->id && $job->event === 'monitor.down';
        });

        Bus::assertDispatched(SendWebhookAlertJob::class, function (SendWebhookAlertJob $job) use ($ep2) {
            return $job->endpointId === $ep2->id && $job->event === 'monitor.down';
        });
    }

    public function test_slack_job_records_last_error_on_failure_and_retries(): void
    {
        Http::fake([
            '*' => Http::response('nope', 500),
        ]);

        [$team, $owner] = $this->makeTeamWithUsers();

        $channel = NotificationChannel::query()->create([
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
            'webhook_alerts_enabled' => true,
            'last_status' => 'down',
            'consecutive_failures' => 2,
            'next_check_at' => now()->subMinute(),
        ]);

        $incident = Incident::query()->create([
            'monitor_id' => $monitor->id,
            'started_at' => now()->subMinutes(3),
            'recovered_at' => null,
            'downtime_seconds' => null,
            'cause_summary' => 'HTTP 500',
            'created_by' => 'system',
            'sla_counted' => true,
        ]);

        $job = new SendSlackAlertJob($team->id, $monitor->id, $incident->id, 'monitor.down');

        try {
            $job->handle(app(\App\Services\Notifications\AlertPayloadBuilder::class));
            $this->fail('Expected slack job to throw to trigger retries.');
        } catch (\Throwable) {
            // expected
        }

        $channel->refresh();
        $this->assertNotNull($channel->slack_last_error);
        $this->assertIsArray($channel->slack_retry_meta);
        $this->assertArrayHasKey('attempt', $channel->slack_retry_meta);
    }

    public function test_webhook_job_sends_signature_headers_and_clears_last_error_on_success(): void
    {
        Http::fake(function ($request) {
            $this->assertTrue($request->hasHeader('X-Monitly-Event'));
            $this->assertTrue($request->hasHeader('X-Monitly-Timestamp'));
            $this->assertTrue($request->hasHeader('X-Monitly-Signature'));
            return Http::response('ok', 200);
        });

        [$team, $owner] = $this->makeTeamWithUsers();

        $ep = WebhookEndpoint::query()->create([
            'team_id' => $team->id,
            'url' => 'https://example.com/receiver',
            'secret' => 'supersecret',
            'enabled' => true,
            'last_error' => 'old error',
            'retry_meta' => ['attempt' => 99],
        ]);

        $monitor = Monitor::query()->create([
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'name' => 'TeamMon',
            'url' => 'https://example.com',
            'paused' => false,
            'email_alerts_enabled' => true,
            'slack_alerts_enabled' => true,
            'webhook_alerts_enabled' => true,
            'last_status' => 'up',
            'consecutive_failures' => 0,
            'next_check_at' => now()->subMinute(),
        ]);

        $incident = Incident::query()->create([
            'monitor_id' => $monitor->id,
            'started_at' => now()->subMinutes(2),
            'recovered_at' => now()->subMinute(),
            'downtime_seconds' => 60,
            'cause_summary' => 'HTTP 500',
            'created_by' => 'system',
            'sla_counted' => true,
        ]);

        $job = new SendWebhookAlertJob($ep->id, $monitor->id, $incident->id, 'monitor.recovered');
        $job->handle(app(\App\Services\Notifications\AlertPayloadBuilder::class), app(\App\Services\Security\SsrfGuard::class));

        $ep->refresh();
        $this->assertNull($ep->last_error);
        $this->assertIsArray($ep->retry_meta);
        $this->assertArrayHasKey('last_success_at', $ep->retry_meta);
    }
}
