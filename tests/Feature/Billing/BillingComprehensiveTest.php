<?php

namespace Tests\Feature\Billing;

use App\Jobs\Monitoring\DispatchDueChecksJob;
use App\Jobs\Monitoring\RunMonitorCheckJob;
use App\Models\BillingWebhookEvent;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\BillingOwnerResolver;
use App\Services\Billing\PaddleWebhookProcessor;
use App\Services\Billing\PlanEnforcer;
use App\Services\Billing\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function makeWebhookPayload(
        string $ownerType,
        int $ownerId,
        string $eventType,
        string $status,
        string $priceId,
        array $overrides = []
    ): array {
        return array_replace_recursive([
            'event_id' => Str::uuid()->toString(),
            'event_type' => $eventType,
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'id' => 'sub_' . Str::random(8),
                'status' => $status,
                'customer_id' => 'cus_' . Str::random(8),
                'custom_data' => [
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                ],
                'items' => [
                    [
                        'price' => ['id' => $priceId],
                        'quantity' => 1,
                    ],
                ],
                'billing_period' => [
                    'ends_at' => now()->addMonth()->toIso8601String(),
                ],
            ],
        ], $overrides);
    }

    private function processWebhook(array $payload): BillingWebhookEvent
    {
        $event = BillingWebhookEvent::create([
            'provider' => 'paddle',
            'event_id' => $payload['event_id'],
            'event_type' => $payload['event_type'],
            'payload' => $payload,
            'signature_valid' => true,
        ]);

        app(PaddleWebhookProcessor::class)->process($event);

        return $event;
    }

    /**
     * TEST 1: Normal user can subscribe to Pro (monthly)
     */
    public function test_normal_user_can_subscribe_to_pro_monthly(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => PlanLimits::BILLING_STATUS_FREE,
        ]);

        $payload = $this->makeWebhookPayload(
            'user',
            $user->id,
            'subscription.created',
            'active',
            'price_pro_monthly'
        );

        $this->processWebhook($payload);

        $user->refresh();

        $this->assertSame(PlanLimits::PLAN_PRO, $user->billing_plan);
        $this->assertSame(PlanLimits::BILLING_STATUS_ACTIVE, $user->billing_status);
        $this->assertNotNull($user->paddle_subscription_id);
        $this->assertNotNull($user->paddle_customer_id);
    }

    /**
     * TEST 2: Team owner can subscribe to Team plan; member cannot access billing
     */
    public function test_team_owner_can_subscribe_member_cannot(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => PlanLimits::BILLING_STATUS_FREE,
        ]);

        $team->users()->attach($member->id, ['role' => 'member']);
        $owner->current_team_id = $team->id;
        $owner->save();
        $member->current_team_id = $team->id;
        $member->save();

        $resolver = app(BillingOwnerResolver::class);

        $this->assertTrue($resolver->canManage($owner, $team));
        $this->assertFalse($resolver->canManage($member, $team));

        $payload = $this->makeWebhookPayload(
            'team',
            $team->id,
            'subscription.created',
            'active',
            'price_team_monthly'
        );

        $this->processWebhook($payload);

        $team->refresh();

        $this->assertSame(PlanLimits::PLAN_TEAM, $team->billing_plan);
        $this->assertSame(PlanLimits::BILLING_STATUS_ACTIVE, $team->billing_status);
    }

    /**
     * TEST 3: Downgrade scheduled end-of-period does not result in double charge
     */
    public function test_downgrade_scheduled_end_of_period_no_double_charge(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_PRO,
            'billing_status' => PlanLimits::BILLING_STATUS_ACTIVE,
            'paddle_subscription_id' => 'sub_existing',
            'paddle_customer_id' => 'cus_existing',
            'next_bill_at' => now()->addDays(15),
        ]);

        $payload = $this->makeWebhookPayload(
            'user',
            $user->id,
            'subscription.updated',
            'active',
            'price_pro_monthly',
            [
                'data' => [
                    'id' => 'sub_existing',
                    'scheduled_change' => [
                        'action' => 'cancel',
                        'effective_at' => now()->addDays(15)->toIso8601String(),
                    ],
                ],
            ]
        );

        $this->processWebhook($payload);

        $user->refresh();

        $this->assertSame(PlanLimits::PLAN_PRO, $user->billing_plan);
        $this->assertSame(PlanLimits::BILLING_STATUS_CANCELING, $user->billing_status);
        $this->assertNotNull($user->paddle_subscription_id);
    }

    /**
     * TEST 4: Cancel to Free results in canceling->canceled->free flow
     */
    public function test_cancel_to_free_results_in_proper_status_flow(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_PRO,
            'billing_status' => PlanLimits::BILLING_STATUS_CANCELING,
            'paddle_subscription_id' => 'sub_cancel_test',
            'paddle_customer_id' => 'cus_cancel_test',
            'next_bill_at' => now()->subDay(),
        ]);

        $payload = $this->makeWebhookPayload(
            'user',
            $user->id,
            'subscription.canceled',
            'canceled',
            'price_pro_monthly',
            [
                'data' => [
                    'id' => 'sub_cancel_test',
                ],
            ]
        );

        $this->processWebhook($payload);

        $user->refresh();

        $this->assertSame(PlanLimits::PLAN_FREE, $user->billing_plan);
        $this->assertSame(PlanLimits::BILLING_STATUS_CANCELED, $user->billing_status);
        $this->assertNull($user->paddle_subscription_id);
    }

    /**
     * TEST 5: Webhook replay is idempotent (no duplicate state changes)
     */
    public function test_webhook_replay_is_idempotent(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => PlanLimits::BILLING_STATUS_FREE,
        ]);

        $eventId = Str::uuid()->toString();

        $payload = [
            'event_id' => $eventId,
            'event_type' => 'subscription.created',
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'id' => 'sub_idempotent_test',
                'status' => 'active',
                'customer_id' => 'cus_idempotent',
                'custom_data' => [
                    'owner_type' => 'user',
                    'owner_id' => $user->id,
                ],
                'items' => [
                    ['price' => ['id' => 'price_pro_monthly'], 'quantity' => 1],
                ],
            ],
        ];

        $event1 = BillingWebhookEvent::create([
            'provider' => 'paddle',
            'event_id' => $eventId,
            'event_type' => $payload['event_type'],
            'payload' => $payload,
            'signature_valid' => true,
        ]);

        app(PaddleWebhookProcessor::class)->process($event1);

        $user->refresh();
        $this->assertSame(PlanLimits::PLAN_PRO, $user->billing_plan);
        $firstProcessedAt = $event1->fresh()->processed_at;

        $event2 = BillingWebhookEvent::where('event_id', $eventId)->first();
        app(PaddleWebhookProcessor::class)->process($event2);

        $event2->refresh();
        $this->assertEquals($firstProcessedAt, $event2->processed_at);

        $this->assertDatabaseCount('billing_webhook_events', 1);

        $user->refresh();
        $this->assertSame(PlanLimits::PLAN_PRO, $user->billing_plan);
    }

    /**
     * TEST 6: Monitor creation is blocked when at plan limit
     */
    public function test_monitor_creation_blocked_at_plan_limit(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => PlanLimits::BILLING_STATUS_FREE,
        ]);

        $limit = PlanLimits::baseMonitorLimit(PlanLimits::PLAN_FREE);
        $this->assertSame(3, $limit);

        for ($i = 1; $i <= $limit; $i++) {
            Monitor::factory()->create([
                'user_id' => $user->id,
                'team_id' => null,
                'url' => "https://example{$i}.com",
                'name' => "Monitor {$i}",
            ]);
        }

        $this->assertCount($limit, Monitor::where('user_id', $user->id)->whereNull('team_id')->get());

        $enforcer = app(PlanEnforcer::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $newMonitor = new Monitor([
            'user_id' => $user->id,
            'team_id' => null,
            'url' => 'https://blocked.com',
            'name' => 'Should Be Blocked',
        ]);

        $enforcer->assertCanCreateMonitor($newMonitor);
    }

    /**
     * TEST 7: Business plan checks route to checks_priority queue
     */
    public function test_business_checks_route_to_priority_queue(): void
    {
        Queue::fake();

        $freeUser = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_FREE,
            'billing_status' => PlanLimits::BILLING_STATUS_FREE,
        ]);

        $freeMonitor = Monitor::factory()->create([
            'user_id' => $freeUser->id,
            'team_id' => null,
            'paused' => false,
            'next_check_at' => now()->subMinute(),
        ]);

        $businessOwner = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_BUSINESS,
            'billing_status' => PlanLimits::BILLING_STATUS_ACTIVE,
        ]);

        $businessTeam = Team::factory()->create([
            'user_id' => $businessOwner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_BUSINESS,
            'billing_status' => PlanLimits::BILLING_STATUS_ACTIVE,
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
     * Additional test: Grace period expiration downgrades user
     */
    public function test_grace_period_expiration_downgrades_user(): void
    {
        $user = User::factory()->create([
            'billing_plan' => PlanLimits::PLAN_PRO,
            'billing_status' => PlanLimits::BILLING_STATUS_PAST_DUE,
            'grace_ends_at' => now()->subDay(),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Monitor::factory()->create([
                'user_id' => $user->id,
                'team_id' => null,
                'url' => "https://example{$i}.com",
            ]);
        }

        app(PlanEnforcer::class)->enforceGraceDowngrades();

        $user->refresh();

        $this->assertSame(PlanLimits::PLAN_FREE, $user->billing_plan);
        $this->assertSame(PlanLimits::BILLING_STATUS_FREE, $user->billing_status);
        $this->assertNull($user->grace_ends_at);

        $lockedCount = Monitor::where('user_id', $user->id)
            ->whereNull('team_id')
            ->where('locked_by_plan', true)
            ->count();

        $this->assertSame(2, $lockedCount);
    }

    /**
     * Additional test: Team plan enforcement blocks excess members
     */
    public function test_team_seat_limit_blocks_excess_members(): void
    {
        $owner = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'billing_plan' => PlanLimits::PLAN_TEAM,
            'billing_status' => PlanLimits::BILLING_STATUS_ACTIVE,
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
