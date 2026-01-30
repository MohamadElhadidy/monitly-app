<?php

namespace App\Services\Billing;

use App\Models\BillingWebhookEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        $plan = $this->resolvePlanFromItems($items);

        Log::info('🎯 Resolved plan', [
            'event_id' => $event->id,
            'plan' => $plan,
        ]);

        DB::transaction(function () use ($owner, $ownerType, $type, $status, $customerId, $subscriptionId, $nextBillAt, $occurredAt, $plan, $data, $event) {
            $billingStatus = $this->determineBillingStatus($type, $status, $plan, $data);

            Log::info('📊 Billing status determined', [
                'event_id' => $event->id,
                'billing_status' => $billingStatus,
                'plan' => $plan,
            ]);

            if ($ownerType === 'user') {
                $this->processUserSubscription(
                    $owner,
                    $plan,
                    $customerId,
                    $subscriptionId,
                    $billingStatus,
                    $nextBillAt,
                    $occurredAt,
                    $type,
                    $event->id
                );
            } else {
                $this->processTeamSubscription(
                    $owner,
                    $plan,
                    $customerId,
                    $subscriptionId,
                    $billingStatus,
                    $nextBillAt,
                    $occurredAt,
                    $type,
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
     * Resolve plan from subscription items.
     */
    private function resolvePlanFromItems(array $items): string
    {
        $plans = config('billing.plans', []);
        $planPriceIds = [];

        foreach ($plans as $key => $plan) {
            $ids = $plan['price_ids'] ?? [];
            $flat = [];

            foreach ($ids as $id) {
                if (is_array($id)) {
                    $flat = array_merge($flat, $id);
                } else {
                    $flat[] = $id;
                }
            }

            $planPriceIds[$key] = array_filter($flat);
        }

        foreach ($items as $item) {
            $priceId = Arr::get($item, 'price.id') ?? Arr::get($item, 'price_id') ?? null;

            if (! is_string($priceId) || $priceId === '') {
                continue;
            }

            foreach ($planPriceIds as $key => $ids) {
                if (in_array($priceId, $ids, true)) {
                    Log::info('✅ Matched plan', ['price_id' => $priceId, 'plan' => $key]);
                    return (string) $key;
                }
            }
        }

        return PlanLimits::PLAN_FREE;
    }

    /**
     * Determine billing status from event type and subscription status
     */
    private function determineBillingStatus(string $type, string $status, string $plan, array $data): string
    {
        if (Str::contains($type, ['payment_failed', 'transaction.payment_failed'])) {
            return 'past_due';
        }

        if (in_array($status, ['past_due', 'paused', 'unpaid'], true)) {
            return 'past_due';
        }

        if (in_array($status, ['canceled', 'cancelled'], true)) {
            return 'canceled';
        }

        $scheduledAction = Arr::get($data, 'scheduled_change.action');
        if ($scheduledAction === 'cancel') {
            return 'canceling';
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
        string $customerId,
        string $subscriptionId,
        string $billingStatus,
        $nextBillAt,
        $occurredAt,
        string $type,
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
            && in_array($currentTeam->billing_status ?? '', ['active', 'past_due', 'canceling'], true);

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
        $normalizedPlan = in_array($plan, [PlanLimits::PLAN_PRO], true) ? $plan : PlanLimits::PLAN_FREE;

        if ($billingStatus === 'canceled' || $normalizedPlan === PlanLimits::PLAN_FREE) {
            $owner->paddle_subscription_id = null;
        } else {
            $owner->paddle_subscription_id = $subscriptionId ?: $owner->paddle_subscription_id;
        }

        $owner->billing_plan = $billingStatus === 'canceled' ? PlanLimits::PLAN_FREE : $normalizedPlan;
        $owner->billing_status = $owner->billing_plan === PlanLimits::PLAN_FREE
            ? ($billingStatus === 'canceled' ? 'canceled' : 'free')
            : $billingStatus;
        $owner->next_bill_at = $nextBillAt;
        $owner->grace_ends_at = null;

        if (Str::contains($type, ['payment_succeeded', 'transaction.paid', 'invoice.paid', 'transaction.completed'])) {
            if (!$owner->first_paid_at) {
                $owner->first_paid_at = $occurredAt;
            }
            $owner->billing_status = $owner->billing_plan === PlanLimits::PLAN_FREE ? 'free' : 'active';
        }
        $owner->checkout_in_progress_until = null;

        $owner->save();

        Log::info('✅ USER updated', [
            'event_id' => $eventId,
            'user_id' => $owner->id,
            'billing_plan' => $owner->billing_plan,
            'billing_status' => $owner->billing_status,
        ]);

        app(PlanEnforcer::class)->enforceMonitorCapForUser($owner);
    }

    /**
     * 🔥 FIX #1: Process team subscription with conflict detection
     */
    private function processTeamSubscription(
        Team $owner,
        string $plan,
        string $customerId,
        string $subscriptionId,
        string $billingStatus,
        $nextBillAt,
        $occurredAt,
        string $type,
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
            && in_array($teamOwner->billing_status ?? '', ['active', 'past_due', 'canceling'], true)
            && $teamOwner->billing_plan !== PlanLimits::PLAN_FREE;

        if (in_array($plan, [PlanLimits::PLAN_TEAM, PlanLimits::PLAN_BUSINESS], true) && $hasActiveUserSub) {
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
        $normalizedPlan = in_array($plan, [PlanLimits::PLAN_TEAM, PlanLimits::PLAN_BUSINESS], true)
            ? $plan
            : PlanLimits::PLAN_FREE;

        if ($billingStatus === 'canceled' || $normalizedPlan === PlanLimits::PLAN_FREE) {
            $owner->paddle_subscription_id = null;
        } else {
            $owner->paddle_subscription_id = $subscriptionId ?: $owner->paddle_subscription_id;
        }

        $owner->billing_plan = $billingStatus === 'canceled' ? PlanLimits::PLAN_FREE : $normalizedPlan;
        $owner->billing_status = $owner->billing_plan === PlanLimits::PLAN_FREE
            ? ($billingStatus === 'canceled' ? 'canceled' : 'free')
            : $billingStatus;
        $owner->next_bill_at = $nextBillAt;
        $owner->grace_ends_at = null;

        if (Str::contains($type, ['payment_succeeded', 'transaction.paid', 'invoice.paid', 'transaction.completed'])) {
            if (!$owner->first_paid_at) {
                $owner->first_paid_at = $occurredAt;
            }
            $owner->billing_status = $owner->billing_plan === PlanLimits::PLAN_FREE ? 'free' : 'active';
        }
        $owner->checkout_in_progress_until = null;

        $owner->save();

        Log::info('✅ TEAM updated', [
            'event_id' => $eventId,
            'team_id' => $owner->id,
            'billing_plan' => $owner->billing_plan,
            'billing_status' => $owner->billing_status,
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
