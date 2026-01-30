<?php

namespace Tests\Feature\Billing;

use App\Models\BillingWebhookEvent;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\PaddleWebhookProcessor;
use App\Services\Billing\PlanEnforcer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingFlowTest extends TestCase
{
    use RefreshDatabase;

    private function setPlanPriceIds(): void
    {
        config()->set('billing.plans.pro.price_ids', [
            'monthly' => 'price_pro_monthly',
            'yearly' => 'price_pro_yearly',
        ]);
        config()->set('billing.plans.team.price_ids', [
            'monthly' => 'price_team_monthly',
            'yearly' => 'price_team_yearly',
        ]);
        config()->set('billing.plans.business.price_ids', [
            'monthly' => 'price_business_monthly',
            'yearly' => 'price_business_yearly',
        ]);
    }

    private function makeEventPayload(User $user, string $eventType, string $status, string $priceId, array $overrides = []): array
    {
        return array_replace_recursive([
            'event_id' => Str::uuid()->toString(),
            'event_type' => $eventType,
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'id' => 'sub_' . Str::random(6),
                'status' => $status,
                'customer_id' => 'cus_' . Str::random(6),
                'custom_data' => [
                    'owner_type' => 'user',
                    'owner_id' => $user->id,
                ],
                'items' => [
                    [
                        'price' => ['id' => $priceId],
                        'quantity' => 1,
                    ],
                ],
            ],
        ], $overrides);
    }

    public function test_upgrade_sets_active_plan(): void
    {
        $this->setPlanPriceIds();

        $user = User::factory()->create();

        $payload = $this->makeEventPayload($user, 'subscription.created', 'active', 'price_pro_monthly');

        $event = BillingWebhookEvent::create([
            'provider' => 'paddle',
            'event_id' => $payload['event_id'],
            'event_type' => $payload['event_type'],
            'payload' => $payload,
            'signature_valid' => true,
        ]);

        app(PaddleWebhookProcessor::class)->process($event);

        $user->refresh();

        $this->assertSame('pro', $user->billing_plan);
        $this->assertSame('active', $user->billing_status);
    }

    public function test_downgrade_marks_canceled(): void
    {
        $this->setPlanPriceIds();

        $user = User::factory()->create([
            'billing_plan' => 'pro',
            'billing_status' => 'active',
        ]);

        $payload = $this->makeEventPayload($user, 'subscription.canceled', 'canceled', 'price_pro_monthly');

        $event = BillingWebhookEvent::create([
            'provider' => 'paddle',
            'event_id' => $payload['event_id'],
            'event_type' => $payload['event_type'],
            'payload' => $payload,
            'signature_valid' => true,
        ]);

        app(PaddleWebhookProcessor::class)->process($event);

        $user->refresh();

        $this->assertSame('free', $user->billing_plan);
        $this->assertSame('canceled', $user->billing_status);
    }

    public function test_canceling_status_sets_canceling(): void
    {
        $this->setPlanPriceIds();

        $user = User::factory()->create([
            'billing_plan' => 'pro',
            'billing_status' => 'active',
        ]);

        $payload = $this->makeEventPayload($user, 'subscription.updated', 'active', 'price_pro_monthly', [
            'data' => [
                'scheduled_change' => [
                    'action' => 'cancel',
                ],
            ],
        ]);

        $event = BillingWebhookEvent::create([
            'provider' => 'paddle',
            'event_id' => $payload['event_id'],
            'event_type' => $payload['event_type'],
            'payload' => $payload,
            'signature_valid' => true,
        ]);

        app(PaddleWebhookProcessor::class)->process($event);

        $user->refresh();

        $this->assertSame('pro', $user->billing_plan);
        $this->assertSame('canceling', $user->billing_status);
    }

    public function test_webhook_replay_is_idempotent(): void
    {
        Queue::fake();

        config()->set('billing.paddle_webhook_secret', 'secret');

        $payload = [
            'event_id' => 'evt_replay',
            'event_type' => 'subscription.updated',
            'data' => [
                'id' => 'sub_123',
                'status' => 'active',
            ],
        ];

        $raw = json_encode($payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp . ':' . $raw, 'secret');
        $header = "ts={$timestamp};h1={$signature}";

        $this->postJson('/webhooks/paddle', $payload, ['Paddle-Signature' => $header])
            ->assertOk();

        $this->postJson('/webhooks/paddle', $payload, ['Paddle-Signature' => $header])
            ->assertOk();

        $this->assertDatabaseCount('billing_webhook_events', 1);

        Queue::assertPushed(\App\Jobs\Billing\ProcessPaddleWebhookJob::class, 1);
    }

    public function test_over_limit_blocking_locks_extra_items(): void
    {
        $user = User::factory()->create([
            'billing_plan' => 'pro',
        ]);

        Monitor::factory()->count(17)->create([
            'user_id' => $user->id,
            'team_id' => null,
        ]);

        app(PlanEnforcer::class)->enforceMonitorCapForUser($user);

        $monitors = Monitor::query()
            ->where('user_id', $user->id)
            ->whereNull('team_id')
            ->orderBy('created_at')
            ->get();

        $this->assertCount(17, $monitors);
        $this->assertFalse($monitors->slice(0, 15)->contains(fn ($monitor) => $monitor->locked_by_plan));
        $this->assertTrue($monitors->slice(15)->every(fn ($monitor) => $monitor->locked_by_plan));
        $this->assertTrue($monitors->slice(15)->every(fn ($monitor) => $monitor->locked_reason === 'This item is blocked due to your plan limits.'));

        $teamOwner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $teamOwner->id,
            'personal_team' => false,
            'billing_plan' => 'team',
        ]);

        $members = User::factory()->count(6)->create();
        foreach ($members as $index => $member) {
            $team->users()->attach($member->id, [
                'role' => 'member',
                'created_at' => now()->subMinutes(10 - $index),
                'updated_at' => now()->subMinutes(10 - $index),
            ]);
        }

        app(PlanEnforcer::class)->enforceSeatCapForTeam($team);

        $blockedCount = \DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('blocked_by_plan', true)
            ->count();

        $this->assertSame(2, $blockedCount);
    }
}
