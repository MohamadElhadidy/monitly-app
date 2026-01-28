<?php

namespace App\Services\Billing;

use App\Models\BillingWebhookEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaddleWebhookProcessor
{
    public function __construct(
        private readonly PlanEnforcer $enforcer
    ) {}

    public function process(BillingWebhookEvent $event): void
    {
        // 🔥 ADD EXTENSIVE DEBUG LOGGING
        Log::info('🔵 Starting webhook processing', [
            'event_id' => $event->id,
            'event_type' => $event->event_type,
        ]);

        if ($event->processed_at) {
            Log::info('⏭️  Webhook already processed, skipping', ['event_id' => $event->id]);
            return;
        }

        $payload = (array) $event->payload;

        $type = (string) ($payload['event_type'] ?? $payload['type'] ?? $event->event_type ?? '');
        $data = (array) ($payload['data'] ?? []);

        Log::info('📦 Webhook payload parsed', [
            'event_id' => $event->id,
            'type' => $type,
            'data_keys' => array_keys($data),
        ]);

        // Find owner from custom_data: { owner_type: "user"|"team", owner_id: 123 }
        $custom = (array) Arr::get($data, 'custom_data', []);
        $ownerType = strtolower((string) Arr::get($custom, 'owner_type', ''));
        $ownerId = (int) Arr::get($custom, 'owner_id', 0);

        Log::info('🔍 Looking for owner', [
            'event_id' => $event->id,
            'custom_data' => $custom,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ]);

        // Fallback: try match by customer email → user
        $customerEmail = (string) (Arr::get($data, 'customer.email') ?? Arr::get($data, 'customer_email') ?? '');

        $owner = null;

        if ($ownerType === 'team' && $ownerId > 0) {
            $owner = Team::query()->find($ownerId);
            Log::info('🏢 Found team owner', [
                'event_id' => $event->id,
                'team_id' => $owner?->id,
                'team_name' => $owner?->name,
            ]);
        } elseif ($ownerType === 'user' && $ownerId > 0) {
            $owner = User::query()->find($ownerId);
            Log::info('👤 Found user owner', [
                'event_id' => $event->id,
                'user_id' => $owner?->id,
                'user_email' => $owner?->email,
            ]);
        } elseif ($customerEmail !== '') {
            $owner = User::query()->where('email', $customerEmail)->first();
            $ownerType = $owner ? 'user' : '';
            Log::info('📧 Found owner by email fallback', [
                'event_id' => $event->id,
                'email' => $customerEmail,
                'found' => $owner ? 'yes' : 'no',
            ]);
        }

        if (! $owner) {
            $errorMsg = 'Owner not found (missing custom_data.owner_* and no email match).';
            Log::error('❌ ' . $errorMsg, [
                'event_id' => $event->id,
                'custom_data' => $custom,
                'customer_email' => $customerEmail,
                'full_payload' => $payload,
            ]);
            
            $event->processing_error = $errorMsg;
            $event->processed_at = now();
            $event->save();
            return;
        }

        Log::info('✅ Owner identified', [
            'event_id' => $event->id,
            'owner_type' => $ownerType,
            'owner_id' => $owner->id,
            'owner_class' => get_class($owner),
        ]);

        // Parse subscription fields (best-effort across Paddle event shapes)
        $customerId = (string) (Arr::get($data, 'customer_id') ?? Arr::get($data, 'customer.id') ?? Arr::get($data, 'customer.id') ?? '');
        $subscriptionId = (string) (Arr::get($data, 'id') ?? Arr::get($data, 'subscription_id') ?? '');
        $status = strtolower((string) (Arr::get($data, 'status') ?? ''));

        Log::info('💳 Subscription details', [
            'event_id' => $event->id,
            'customer_id' => $customerId,
            'subscription_id' => $subscriptionId,
            'status' => $status,
        ]);

        $nextBillAt = Arr::get($data, 'next_billed_at') ?? Arr::get($data, 'next_bill_date') ?? Arr::get($data, 'billing_period.ends_at');
        $nextBillAt = $nextBillAt ? now()->parse($nextBillAt) : null;

        $occurredAt = $payload['occurred_at'] ?? $payload['event_time'] ?? null;
        $occurredAt = $occurredAt ? now()->parse($occurredAt) : now();

        // Determine plan + add-ons from items/lines (Paddle Billing v2 typically has data.items)
        $items = Arr::get($data, 'items', []);
        if (! is_array($items)) $items = [];

        Log::info('📋 Processing items', [
            'event_id' => $event->id,
            'item_count' => count($items),
            'items' => $items,
        ]);

        $plan = $this->resolvePlanFromItems($items, $ownerType);
        $addons = $this->resolveAddonsFromItems($items, $ownerType);

        Log::info('🎯 Plan and addons resolved', [
            'event_id' => $event->id,
            'plan' => $plan,
            'addons' => $addons,
        ]);

        DB::transaction(function () use ($owner, $ownerType, $type, $status, $customerId, $subscriptionId, $nextBillAt, $occurredAt, $plan, $addons, $event) {
            Log::info('🔄 Starting database transaction', [
                'event_id' => $event->id,
                'owner_type' => $ownerType,
                'owner_id' => $owner->id,
            ]);

            // Normalize billing_status based on event + status
            $graceDays = (int) config('billing.grace_days', 7);

            $billingStatus = 'active';

            // Payment failed events → grace
            if (Str::contains($type, ['payment_failed', 'transaction.payment_failed', 'invoice.payment_failed'])) {
                $billingStatus = 'grace';
            } elseif (in_array($status, ['past_due', 'paused'], true)) {
                $billingStatus = 'grace';
            } elseif (in_array($status, ['canceled', 'cancelled'], true)) {
                $billingStatus = 'canceled';
            } elseif ($plan === PlanLimits::PLAN_FREE) {
                $billingStatus = 'free';
            }

            Log::info('📊 Billing status determined', [
                'event_id' => $event->id,
                'billing_status' => $billingStatus,
                'plan' => $plan,
            ]);

            // Apply to owner
            if ($ownerType === 'user') {
                /** @var User $owner */
                Log::info('👤 Updating user', [
                    'event_id' => $event->id,
                    'user_id' => $owner->id,
                    'old_plan' => $owner->billing_plan,
                    'new_plan' => $plan,
                ]);

                $owner->paddle_customer_id = $customerId ?: $owner->paddle_customer_id;
                $owner->paddle_subscription_id = $subscriptionId ?: $owner->paddle_subscription_id;

                // Users only: free/pro
                $owner->billing_plan = in_array($plan, [PlanLimits::PLAN_PRO], true) ? $plan : PlanLimits::PLAN_FREE;
                $owner->billing_status = $owner->billing_plan === PlanLimits::PLAN_FREE ? 'free' : $billingStatus;

                $owner->next_bill_at = $nextBillAt;

                // If grace, set grace_ends_at; else clear it
                if ($owner->billing_status === 'grace') {
                    $owner->grace_ends_at = now()->addDays($graceDays);
                } else {
                    $owner->grace_ends_at = null;
                }

                // Successful payment sets first_paid_at ONCE
                if (Str::contains($type, ['payment_succeeded', 'transaction.paid', 'invoice.paid', 'payment_succeeded', 'transaction.completed'])) {
                    if (! $owner->first_paid_at) {
                        $owner->first_paid_at = $occurredAt;
                    }
                    // Successful payment clears grace
                    $owner->billing_status = $owner->billing_plan === PlanLimits::PLAN_FREE ? 'free' : 'active';
                    $owner->grace_ends_at = null;
                }

                // Apply add-ons (Pro and Team)
                $owner->addon_extra_monitor_packs = in_array($owner->billing_plan, [PlanLimits::PLAN_PRO, PlanLimits::PLAN_TEAM], true) ? $addons['extra_monitor_packs'] : 0;
                // Faster checks (5min) available for Pro and Team; legacy overrides (2/1min) only for Pro
                $intervalOverride = $addons['interval_override_minutes'];
                if ($intervalOverride === 5 && in_array($owner->billing_plan, [PlanLimits::PLAN_PRO, PlanLimits::PLAN_TEAM], true)) {
                    $owner->addon_interval_override_minutes = 5;
                } elseif (in_array($intervalOverride, [2, 1], true) && $owner->billing_plan === PlanLimits::PLAN_PRO) {
                    $owner->addon_interval_override_minutes = $intervalOverride;
                } else {
                    $owner->addon_interval_override_minutes = null;
                }

                $owner->save();

                Log::info('✅ User updated successfully', [
                    'event_id' => $event->id,
                    'user_id' => $owner->id,
                    'billing_plan' => $owner->billing_plan,
                    'billing_status' => $owner->billing_status,
                ]);

                app(PlanEnforcer::class)->enforceMonitorCapForUser($owner);
            } else {
                /** @var Team $owner */
                Log::info('🏢 Updating team', [
                    'event_id' => $event->id,
                    'team_id' => $owner->id,
                    'team_name' => $owner->name,
                    'old_plan' => $owner->billing_plan,
                    'new_plan' => $plan,
                ]);

                $owner->paddle_customer_id = $customerId ?: $owner->paddle_customer_id;
                $owner->paddle_subscription_id = $subscriptionId ?: $owner->paddle_subscription_id;

                // Teams only: free/team
                $owner->billing_plan = $plan === PlanLimits::PLAN_TEAM ? PlanLimits::PLAN_TEAM : PlanLimits::PLAN_FREE;
                $owner->billing_status = $owner->billing_plan === PlanLimits::PLAN_FREE ? 'free' : $billingStatus;

                $owner->next_bill_at = $nextBillAt;

                if ($owner->billing_status === 'grace') {
                    $owner->grace_ends_at = now()->addDays($graceDays);
                } else {
                    $owner->grace_ends_at = null;
                }

                if (Str::contains($type, ['payment_succeeded', 'transaction.paid', 'invoice.paid', 'payment_succeeded', 'transaction.completed'])) {
                    if (! $owner->first_paid_at) {
                        $owner->first_paid_at = $occurredAt;
                    }
                    $owner->billing_status = $owner->billing_plan === PlanLimits::PLAN_FREE ? 'free' : 'active';
                    $owner->grace_ends_at = null;
                }

                // Apply add-ons (Team only)
                $owner->addon_extra_monitor_packs = $owner->billing_plan === PlanLimits::PLAN_TEAM ? $addons['extra_monitor_packs'] : 0;
                $owner->addon_extra_seat_packs = $owner->billing_plan === PlanLimits::PLAN_TEAM ? $addons['extra_seat_packs'] : 0;
                // Faster checks (5min) available for Team
                $intervalOverride = $addons['interval_override_minutes'];
                if ($intervalOverride === 5 && $owner->billing_plan === PlanLimits::PLAN_TEAM) {
                    $owner->addon_interval_override_minutes = 5;
                } elseif (in_array($intervalOverride, [2, 1], true) && $owner->billing_plan === PlanLimits::PLAN_TEAM) {
                    $owner->addon_interval_override_minutes = $intervalOverride;
                } else {
                    $owner->addon_interval_override_minutes = null;
                }

                $owner->save();

                Log::info('✅ Team updated successfully', [
                    'event_id' => $event->id,
                    'team_id' => $owner->id,
                    'billing_plan' => $owner->billing_plan,
                    'billing_status' => $owner->billing_status,
                    'paddle_subscription_id' => $owner->paddle_subscription_id,
                ]);

                app(PlanEnforcer::class)->enforceSeatCapForTeam($owner);
                app(PlanEnforcer::class)->enforceMonitorCapForTeam($owner);
            }
        });

        $event->processed_at = now();
        $event->processing_error = null;
        $event->save();

        Log::info('✅ Webhook processing completed successfully', [
            'event_id' => $event->id,
        ]);
    }

    private function resolvePlanFromItems(array $items, string $ownerType): string
    {
        $proIds = (array) config('billing.plans.pro.price_ids', []);
        $teamIds = (array) config('billing.plans.team.price_ids', []);

        Log::info('🔍 Resolving plan from items', [
            'owner_type' => $ownerType,
            'item_count' => count($items),
            'pro_price_ids' => $proIds,
            'team_price_ids' => $teamIds,
        ]);

        $seen = [];

        foreach ($items as $it) {
            $priceId = Arr::get($it, 'price.id') ?? Arr::get($it, 'price_id') ?? null;
            if (is_string($priceId) && $priceId !== '') {
                $seen[] = $priceId;
            }
        }

        Log::info('📋 Price IDs found in items', [
            'owner_type' => $ownerType,
            'price_ids' => $seen,
        ]);

        // Team subscriptions must map to Team plan
        if ($ownerType === 'team') {
            foreach ($seen as $pid) {
                if (in_array($pid, $teamIds, true)) {
                    Log::info('✅ Matched team plan', ['price_id' => $pid]);
                    return PlanLimits::PLAN_TEAM;
                }
            }
            Log::warning('⚠️  No team price ID match, defaulting to FREE', [
                'seen_price_ids' => $seen,
                'expected_team_ids' => $teamIds,
            ]);
            return PlanLimits::PLAN_FREE;
        }

        // User subscriptions map to Pro (or Free)
        foreach ($seen as $pid) {
            if (in_array($pid, $proIds, true)) {
                Log::info('✅ Matched pro plan', ['price_id' => $pid]);
                return PlanLimits::PLAN_PRO;
            }
        }

        Log::warning('⚠️  No plan match found, defaulting to FREE', [
            'seen_price_ids' => $seen,
        ]);

        return PlanLimits::PLAN_FREE;
    }

    private function resolveAddonsFromItems(array $items, string $ownerType): array
    {
        $addonMon = (array) config('billing.addons.extra_monitor_pack.price_ids', []);
        $addonSeat = (array) config('billing.addons.extra_seat_pack.price_ids', []);
        $addonFaster = (array) config('billing.addons.faster_checks_5min.price_ids', []);
        // Legacy addons (keep for backward compatibility)
        $addon2 = (array) config('billing.addons.interval_override_2.price_ids', []);
        $addon1 = (array) config('billing.addons.interval_override_1.price_ids', []);

        $extraMonitorPacks = 0;
        $extraSeatPacks = 0;

        $intervalOverride = null; // 5 (faster checks), 2, or 1

        foreach ($items as $it) {
            $priceId = Arr::get($it, 'price.id') ?? Arr::get($it, 'price_id') ?? null;
            $qty = (int) (Arr::get($it, 'quantity') ?? 1);
            $qty = max(0, $qty);

            if (! is_string($priceId) || $priceId === '') continue;

            if (in_array($priceId, $addonMon, true)) {
                $extraMonitorPacks += $qty;
            }

            if ($ownerType === 'team' && in_array($priceId, $addonSeat, true)) {
                $extraSeatPacks += $qty;
            }

            // Faster checks addon (10min → 5min)
            if (in_array($priceId, $addonFaster, true)) {
                $intervalOverride = 5;
            }
            // Legacy overrides
            elseif (in_array($priceId, $addon2, true)) {
                $intervalOverride = 2;
            }
            elseif (in_array($priceId, $addon1, true)) {
                $intervalOverride = 1;
            }
        }

        Log::info('🎁 Addons resolved', [
            'owner_type' => $ownerType,
            'extra_monitor_packs' => $extraMonitorPacks,
            'extra_seat_packs' => $extraSeatPacks,
            'interval_override_minutes' => $intervalOverride,
        ]);

        return [
            'extra_monitor_packs' => $extraMonitorPacks,
            'extra_seat_packs' => $extraSeatPacks,
            'interval_override_minutes' => $intervalOverride,
        ];
    }
}