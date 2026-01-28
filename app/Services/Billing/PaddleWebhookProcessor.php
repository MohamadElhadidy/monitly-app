<?php

namespace App\Services\Billing;

use App\Models\BillingWebhookEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DuplicateSubscriptionsDetected;
use App\Notifications\SubscriptionAutoCanceled;

class PaddleWebhookProcessor
{
    public function __construct(
        private readonly PlanEnforcer $enforcer,
        private readonly PaddleService $paddleService
    ) {}

    public function process(BillingWebhookEvent $event): void
    {
        Log::info('🔵 WEBHOOK START', [
            'event_id' => $event->id,
            'event_type' => $event->event_type,
            'payload_size' => strlen(json_encode($event->payload)),
        ]);

        if ($event->processed_at) {
            Log::info('⏭️  Already processed, skipping', ['event_id' => $event->id]);
            return;
        }

        $payload = (array) $event->payload;
        $type = (string) ($payload['event_type'] ?? $payload['type'] ?? $event->event_type ?? '');
        $data = (array) ($payload['data'] ?? []);

        // 🔥 FIX #2: Better owner resolution with fallbacks
        $owner = $this->findOwner($data, $event->id);
        
        if (!$owner) {
            $errorMsg = 'Owner not found. Custom data: ' . json_encode(Arr::get($data, 'custom_data'));
            Log::error('❌ ' . $errorMsg, [
                'event_id' => $event->id,
                'customer_email' => Arr::get($data, 'customer.email'),
            ]);
            
            $event->processing_error = $errorMsg;
            $event->processed_at = now();
            $event->save();
            
            // 🔥 SEND ALERT TO ADMIN
            $this->alertAdminAboutFailedWebhook($event, $errorMsg);
            return;
        }

        $ownerType = $owner instanceof Team ? 'team' : 'user';
        
        Log::info('✅ Owner identified', [
            'event_id' => $event->id,
            'owner_type' => $ownerType,
            'owner_id' => $owner->id,
        ]);

        // Parse subscription data
        $customerId = (string) (Arr::get($data, 'customer_id') ?? Arr::get($data, 'customer.id') ?? '');
        $subscriptionId = (string) (Arr::get($data, 'id') ?? Arr::get($data, 'subscription_id') ?? '');
        $status = strtolower((string) (Arr::get($data, 'status') ?? ''));

        $nextBillAt = Arr::get($data, 'next_billed_at') ?? Arr::get($data, 'next_bill_date') ?? Arr::get($data, 'billing_period.ends_at');
        $nextBillAt = $nextBillAt ? now()->parse($nextBillAt) : null;

        $occurredAt = $payload['occurred_at'] ?? $payload['event_time'] ?? null;
        $occurredAt = $occurredAt ? now()->parse($occurredAt) : now();

        // 🔥 FIX #3 & #4: BETTER PLAN AND ADDON RESOLUTION
        $items = Arr::get($data, 'items', []);
        if (!is_array($items)) $items = [];

        Log::info('📋 Processing items', [
            'event_id' => $event->id,
            'item_count' => count($items),
            'items_detail' => json_encode($items),
        ]);

        [$plan, $addons] = $this->resolvePlanAndAddons($items, $ownerType);

        Log::info('🎯 Resolved plan and addons', [
            'event_id' => $event->id,
            'plan' => $plan,
            'addons' => $addons,
        ]);

        DB::transaction(function () use ($owner, $ownerType, $type, $status, $customerId, $subscriptionId, $nextBillAt, $occurredAt, $plan, $addons, $event) {
            
            $graceDays = (int) config('billing.grace_days', 7);
            $billingStatus = $this->determineBillingStatus($type, $status, $plan);

            Log::info('📊 Billing status determined', [
                'event_id' => $event->id,
                'billing_status' => $billingStatus,
                'plan' => $plan,
            ]);

            if ($ownerType === 'user') {
                $this->processUserSubscription(
                    $owner,
                    $plan,
                    $addons,
                    $customerId,
                    $subscriptionId,
                    $billingStatus,
                    $nextBillAt,
                    $occurredAt,
                    $type,
                    $graceDays,
                    $event->id
                );
            } else {
                $this->processTeamSubscription(
                    $owner,
                    $plan,
                    $addons,
                    $customerId,
                    $subscriptionId,
                    $billingStatus,
                    $nextBillAt,
                    $occurredAt,
                    $type,
                    $graceDays,
                    $event->id
                );
            }
        });

        $event->processed_at = now();
        $event->processing_error = null;
        $event->save();

        Log::info('✅ WEBHOOK COMPLETE', ['event_id' => $event->id]);
    }

    /**
     * 🔥 FIX #2: Improved owner finding with multiple fallbacks
     */
    private function findOwner(array $data, string $eventId): User|Team|null
    {
        $custom = (array) Arr::get($data, 'custom_data', []);
        $ownerType = strtolower((string) Arr::get($custom, 'owner_type', ''));
        $ownerId = (int) Arr::get($custom, 'owner_id', 0);

        // Primary: Use custom_data
        if ($ownerType === 'team' && $ownerId > 0) {
            $owner = Team::find($ownerId);
            if ($owner) {
                Log::info('🏢 Found team via custom_data', [
                    'event_id' => $eventId,
                    'team_id' => $owner->id,
                ]);
                return $owner;
            }
        }

        if ($ownerType === 'user' && $ownerId > 0) {
            $owner = User::find($ownerId);
            if ($owner) {
                Log::info('👤 Found user via custom_data', [
                    'event_id' => $eventId,
                    'user_id' => $owner->id,
                ]);
                return $owner;
            }
        }

        // Fallback 1: Match by paddle_subscription_id
        if ($subscriptionId = (string) (Arr::get($data, 'id') ?? Arr::get($data, 'subscription_id') ?? '')) {
            // Try users first
            $owner = User::where('paddle_subscription_id', $subscriptionId)->first();
            if ($owner) {
                Log::info('👤 Found user via paddle_subscription_id', [
                    'event_id' => $eventId,
                    'user_id' => $owner->id,
                    'subscription_id' => $subscriptionId,
                ]);
                return $owner;
            }

            // Try teams
            $owner = Team::where('paddle_subscription_id', $subscriptionId)->first();
            if ($owner) {
                Log::info('🏢 Found team via paddle_subscription_id', [
                    'event_id' => $eventId,
                    'team_id' => $owner->id,
                    'subscription_id' => $subscriptionId,
                ]);
                return $owner;
            }
        }

        // Fallback 2: Match by paddle_customer_id
        if ($customerId = (string) (Arr::get($data, 'customer_id') ?? Arr::get($data, 'customer.id') ?? '')) {
            // Try users
            $owner = User::where('paddle_customer_id', $customerId)->first();
            if ($owner) {
                Log::info('👤 Found user via paddle_customer_id', [
                    'event_id' => $eventId,
                    'user_id' => $owner->id,
                    'customer_id' => $customerId,
                ]);
                return $owner;
            }

            // Try teams
            $owner = Team::where('paddle_customer_id', $customerId)->first();
            if ($owner) {
                Log::info('🏢 Found team via paddle_customer_id', [
                    'event_id' => $eventId,
                    'team_id' => $owner->id,
                    'customer_id' => $customerId,
                ]);
                return $owner;
            }
        }

        // Fallback 3: Match by email
        if ($email = (string) (Arr::get($data, 'customer.email') ?? Arr::get($data, 'customer_email') ?? '')) {
            $owner = User::where('email', $email)->first();
            if ($owner) {
                Log::info('👤 Found user via email', [
                    'event_id' => $eventId,
                    'user_id' => $owner->id,
                    'email' => $email,
                ]);
                return $owner;
            }
        }

        return null;
    }

    /**
     * 🔥 FIX #3 & #4: Properly resolve plan and addons from items
     */
    private function resolvePlanAndAddons(array $items, string $ownerType): array
    {
        $proIds = (array) config('billing.plans.pro.price_ids', []);
        $teamIds = (array) config('billing.plans.team.price_ids', []);
        
        $addonMonitorIds = (array) config('billing.addons.extra_monitor_pack.price_ids', []);
        $addonSeatIds = (array) config('billing.addons.extra_seat_pack.price_ids', []);
        $addonFasterIds = (array) config('billing.addons.faster_checks_5min.price_ids', []);

        $plan = PlanLimits::PLAN_FREE;
        $monitorPacks = 0;
        $seatPacks = 0;
        $intervalOverride = null;

        foreach ($items as $item) {
            $priceId = Arr::get($item, 'price.id') ?? Arr::get($item, 'price_id') ?? null;
            $quantity = (int) (Arr::get($item, 'quantity') ?? 1);

            if (!is_string($priceId) || $priceId === '') continue;

            // Check if it's a plan
            if (in_array($priceId, $teamIds, true)) {
                $plan = PlanLimits::PLAN_TEAM;
                Log::info('✅ Matched TEAM plan', ['price_id' => $priceId]);
            } elseif (in_array($priceId, $proIds, true)) {
                $plan = PlanLimits::PLAN_PRO;
                Log::info('✅ Matched PRO plan', ['price_id' => $priceId]);
            }
            // Check if it's an addon
            elseif (in_array($priceId, $addonMonitorIds, true)) {
                $monitorPacks += $quantity; // 🔥 FIX #6: Track QUANTITY
                Log::info('✅ Matched MONITOR addon', ['price_id' => $priceId, 'quantity' => $quantity]);
            } elseif (in_array($priceId, $addonSeatIds, true)) {
                $seatPacks += $quantity; // 🔥 FIX #6: Track QUANTITY
                Log::info('✅ Matched SEAT addon', ['price_id' => $priceId, 'quantity' => $quantity]);
            } elseif (in_array($priceId, $addonFasterIds, true)) {
                $intervalOverride = 5;
                Log::info('✅ Matched FASTER CHECKS addon', ['price_id' => $priceId]);
            }
        }

        return [
            $plan,
            [
                'extra_monitor_packs' => $monitorPacks,
                'extra_seat_packs' => $seatPacks,
                'interval_override_minutes' => $intervalOverride,
            ]
        ];
    }

    /**
     * Determine billing status from event type and subscription status
     */
    private function determineBillingStatus(string $type, string $status, string $plan): string
    {
        if (Str::contains($type, ['payment_failed', 'transaction.payment_failed'])) {
            return 'grace';
        }

        if (in_array($status, ['past_due', 'paused'], true)) {
            return 'grace';
        }

        if (in_array($status, ['canceled', 'cancelled'], true)) {
            return 'canceled';
        }

        if ($plan === PlanLimits::PLAN_FREE) {
            return 'free';
        }

        return 'active';
    }

    /**
     * 🔥 FIX #1: Process user subscription with conflict detection
     */
    private function processUserSubscription(
        User $owner,
        string $plan,
        array $addons,
        string $customerId,
        string $subscriptionId,
        string $billingStatus,
        $nextBillAt,
        $occurredAt,
        string $type,
        int $graceDays,
        string $eventId
    ): void {
        Log::info('👤 Processing USER subscription', [
            'event_id' => $eventId,
            'user_id' => $owner->id,
            'old_plan' => $owner->billing_plan,
            'new_plan' => $plan,
        ]);

        // 🔥 FIX #1: Check for conflicts with team subscription
        $currentTeam = $owner->currentTeam;
        $hasActiveTeamSub = $currentTeam 
            && $currentTeam->paddle_subscription_id 
            && in_array($currentTeam->billing_status ?? '', ['active', 'grace']);

        if ($plan === PlanLimits::PLAN_PRO && $hasActiveTeamSub) {
            Log::warning('⚠️ User subscribing to Pro while team subscription active', [
                'user_id' => $owner->id,
                'team_id' => $currentTeam->id,
            ]);
            
            // Check if same day
            $userSubCreated = $occurredAt;
            $teamSubCreated = $currentTeam->first_paid_at ?? $currentTeam->updated_at;
            $daysDiff = $teamSubCreated ? $userSubCreated->diffInDays($teamSubCreated) : 999;
            
            if ($daysDiff < 1) {
                // Same day - alert but allow
                Log::warning('⏰ Same-day subscriptions detected', [
                    'user_id' => $owner->id,
                    'days_diff' => $daysDiff,
                ]);
                
                // Send notification
                $owner->notify(new DuplicateSubscriptionsDetected($currentTeam));
            }
        }

        // Update user
        $owner->paddle_customer_id = $customerId ?: $owner->paddle_customer_id;
        $owner->paddle_subscription_id = $subscriptionId ?: $owner->paddle_subscription_id;
        $owner->billing_plan = in_array($plan, [PlanLimits::PLAN_PRO], true) ? $plan : PlanLimits::PLAN_FREE;
        $owner->billing_status = $owner->billing_plan === PlanLimits::PLAN_FREE ? 'free' : $billingStatus;
        $owner->next_bill_at = $nextBillAt;

        if ($owner->billing_status === 'grace') {
            $owner->grace_ends_at = now()->addDays($graceDays);
        } else {
            $owner->grace_ends_at = null;
        }

        if (Str::contains($type, ['payment_succeeded', 'transaction.paid', 'invoice.paid', 'transaction.completed'])) {
            if (!$owner->first_paid_at) {
                $owner->first_paid_at = $occurredAt;
            }
            $owner->billing_status = $owner->billing_plan === PlanLimits::PLAN_FREE ? 'free' : 'active';
            $owner->grace_ends_at = null;
        }

        // 🔥 FIX #6: Apply addons with proper quantity
        $owner->addon_extra_monitor_packs = $owner->billing_plan === PlanLimits::PLAN_PRO ? $addons['extra_monitor_packs'] : 0;
        $owner->addon_interval_override_minutes = $addons['interval_override_minutes'];

        $owner->save();

        Log::info('✅ USER updated', [
            'event_id' => $eventId,
            'user_id' => $owner->id,
            'billing_plan' => $owner->billing_plan,
            'billing_status' => $owner->billing_status,
            'monitor_packs' => $owner->addon_extra_monitor_packs,
        ]);

        app(PlanEnforcer::class)->enforceMonitorCapForUser($owner);
    }

    /**
     * 🔥 FIX #1: Process team subscription with conflict detection
     */
    private function processTeamSubscription(
        Team $owner,
        string $plan,
        array $addons,
        string $customerId,
        string $subscriptionId,
        string $billingStatus,
        $nextBillAt,
        $occurredAt,
        string $type,
        int $graceDays,
        string $eventId
    ): void {
        Log::info('🏢 Processing TEAM subscription', [
            'event_id' => $eventId,
            'team_id' => $owner->id,
            'old_plan' => $owner->billing_plan,
            'new_plan' => $plan,
        ]);

        // 🔥 FIX #1: Check for conflicts with user subscription
        $teamOwner = $owner->owner;
        $hasActiveUserSub = $teamOwner 
            && $teamOwner->paddle_subscription_id 
            && in_array($teamOwner->billing_status ?? '', ['active', 'grace'])
            && $teamOwner->billing_plan !== PlanLimits::PLAN_FREE;

        if ($plan === PlanLimits::PLAN_TEAM && $hasActiveUserSub) {
            Log::warning('⚠️ Team subscribing while owner has Pro subscription', [
                'team_id' => $owner->id,
                'owner_id' => $teamOwner->id,
                'owner_plan' => $teamOwner->billing_plan,
            ]);
            
            // Check timing
            $teamSubCreated = $occurredAt;
            $userSubCreated = $teamOwner->first_paid_at ?? $teamOwner->updated_at;
            $daysDiff = $userSubCreated ? $teamSubCreated->diffInDays($userSubCreated) : 999;
            
            Log::info('📅 Checking subscription timing', [
                'team_sub_created' => $teamSubCreated->toDateTimeString(),
                'user_sub_created' => $userSubCreated?->toDateTimeString(),
                'days_diff' => $daysDiff,
            ]);
            
            if ($daysDiff < 1) {
                // Same day - alert user
                Log::warning('⏰ Same-day subscriptions', [
                    'owner_id' => $teamOwner->id,
                    'message' => 'Both Pro and Team created same day',
                ]);
                
                $teamOwner->notify(new DuplicateSubscriptionsDetected($owner));
                
            } else {
                // Different days - auto-cancel Pro
                Log::info('🔄 Auto-canceling Pro subscription', [
                    'owner_id' => $teamOwner->id,
                    'reason' => 'Team upgrade',
                ]);
                
                try {
                    $this->paddleService->cancelSubscription($teamOwner->paddle_subscription_id, false);
                    
                    $teamOwner->billing_plan = PlanLimits::PLAN_FREE;
                    $teamOwner->billing_status = 'canceled';
                    $teamOwner->save();
                    
                    Log::info('✅ Canceled Pro subscription', ['owner_id' => $teamOwner->id]);
                    
                    $teamOwner->notify(new SubscriptionAutoCanceled($owner));
                    
                } catch (\Exception $e) {
                    Log::error('❌ Failed to cancel Pro subscription', [
                        'owner_id' => $teamOwner->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Update team
        $owner->paddle_customer_id = $customerId ?: $owner->paddle_customer_id;
        $owner->paddle_subscription_id = $subscriptionId ?: $owner->paddle_subscription_id;
        $owner->billing_plan = $plan === PlanLimits::PLAN_TEAM ? PlanLimits::PLAN_TEAM : PlanLimits::PLAN_FREE;
        $owner->billing_status = $owner->billing_plan === PlanLimits::PLAN_FREE ? 'free' : $billingStatus;
        $owner->next_bill_at = $nextBillAt;

        if ($owner->billing_status === 'grace') {
            $owner->grace_ends_at = now()->addDays($graceDays);
        } else {
            $owner->grace_ends_at = null;
        }

        if (Str::contains($type, ['payment_succeeded', 'transaction.paid', 'invoice.paid', 'transaction.completed'])) {
            if (!$owner->first_paid_at) {
                $owner->first_paid_at = $occurredAt;
            }
            $owner->billing_status = $owner->billing_plan === PlanLimits::PLAN_FREE ? 'free' : 'active';
            $owner->grace_ends_at = null;
        }

        // 🔥 FIX #6: Apply addons with proper quantity
        $owner->addon_extra_monitor_packs = $owner->billing_plan === PlanLimits::PLAN_TEAM ? $addons['extra_monitor_packs'] : 0;
        $owner->addon_extra_seat_packs = $owner->billing_plan === PlanLimits::PLAN_TEAM ? $addons['extra_seat_packs'] : 0;
        $owner->addon_interval_override_minutes = $addons['interval_override_minutes'];

        $owner->save();

        Log::info('✅ TEAM updated', [
            'event_id' => $eventId,
            'team_id' => $owner->id,
            'billing_plan' => $owner->billing_plan,
            'billing_status' => $owner->billing_status,
            'monitor_packs' => $owner->addon_extra_monitor_packs,
            'seat_packs' => $owner->addon_extra_seat_packs,
        ]);

        app(PlanEnforcer::class)->enforceSeatCapForTeam($owner);
        app(PlanEnforcer::class)->enforceMonitorCapForTeam($owner);
    }

    /**
     * Send alert to admin about failed webhook
     */
    private function alertAdminAboutFailedWebhook(BillingWebhookEvent $event, string $error): void
    {
        // TODO: Implement admin notification
        Log::critical('🚨 FAILED WEBHOOK - ADMIN ALERT NEEDED', [
            'event_id' => $event->id,
            'error' => $error,
            'payload' => $event->payload,
        ]);
    }
}