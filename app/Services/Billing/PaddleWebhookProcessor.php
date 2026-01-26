<?php

namespace App\Services\Billing;

use App\Models\BillingWebhookEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaddleWebhookProcessor
{
    public function __construct(
        private readonly PlanEnforcer $enforcer
    ) {}

    public function process(BillingWebhookEvent $event): void
    {
        if ($event->processed_at) {
            return;
        }

        $payload = (array) $event->payload;

        $type = (string) ($payload['event_type'] ?? $payload['type'] ?? $event->event_type ?? '');
        $data = (array) ($payload['data'] ?? []);

        // Find owner from custom_data: { owner_type: "user"|"team", owner_id: 123 }
        $custom = (array) Arr::get($data, 'custom_data', []);
        $ownerType = strtolower((string) Arr::get($custom, 'owner_type', ''));
        $ownerId = (int) Arr::get($custom, 'owner_id', 0);

        // Fallback: try match by customer email → user
        $customerEmail = (string) (Arr::get($data, 'customer.email') ?? Arr::get($data, 'customer_email') ?? '');

        $owner = null;

        if ($ownerType === 'team' && $ownerId > 0) {
            $owner = Team::query()->find($ownerId);
        } elseif ($ownerType === 'user' && $ownerId > 0) {
            $owner = User::query()->find($ownerId);
        } elseif ($customerEmail !== '') {
            $owner = User::query()->where('email', $customerEmail)->first();
            $ownerType = $owner ? 'user' : '';
        }

        if (! $owner) {
            $event->processing_error = 'Owner not found (missing custom_data.owner_* and no email match).';
            $event->processed_at = now();
            $event->save();
            return;
        }

        // Parse subscription fields (best-effort across Paddle event shapes)
        $customerId = (string) (Arr::get($data, 'customer_id') ?? Arr::get($data, 'customer.id') ?? Arr::get($data, 'customer.id') ?? '');
        $subscriptionId = (string) (Arr::get($data, 'id') ?? Arr::get($data, 'subscription_id') ?? '');
        $status = strtolower((string) (Arr::get($data, 'status') ?? ''));

        $nextBillAt = Arr::get($data, 'next_billed_at') ?? Arr::get($data, 'next_bill_date') ?? Arr::get($data, 'billing_period.ends_at');
        $nextBillAt = $nextBillAt ? now()->parse($nextBillAt) : null;

        $occurredAt = $payload['occurred_at'] ?? $payload['event_time'] ?? null;
        $occurredAt = $occurredAt ? now()->parse($occurredAt) : now();

        // Determine plan + add-ons from items/lines (Paddle Billing v2 typically has data.items)
        $items = Arr::get($data, 'items', []);
        if (! is_array($items)) $items = [];

        $plan = $this->resolvePlanFromItems($items, $ownerType);

        $addons = $this->resolveAddonsFromItems($items, $ownerType);

        DB::transaction(function () use ($owner, $ownerType, $type, $status, $customerId, $subscriptionId, $nextBillAt, $occurredAt, $plan, $addons) {
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

            // Apply to owner
            if ($ownerType === 'user') {
                /** @var User $owner */
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

                // Apply add-ons (Pro only)
                $owner->addon_extra_monitor_packs = $owner->billing_plan === PlanLimits::PLAN_PRO ? $addons['extra_monitor_packs'] : 0;
                $owner->addon_interval_override_minutes = $owner->billing_plan === PlanLimits::PLAN_PRO ? $addons['interval_override_minutes'] : null;

                $owner->save();

                app(PlanEnforcer::class)->enforceMonitorCapForUser($owner);
            } else {
                /** @var Team $owner */
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
                $owner->addon_interval_override_minutes = $owner->billing_plan === PlanLimits::PLAN_TEAM ? $addons['interval_override_minutes'] : null;

                $owner->save();

                app(PlanEnforcer::class)->enforceSeatCapForTeam($owner);
                app(PlanEnforcer::class)->enforceMonitorCapForTeam($owner);
            }
        });

        $event->processed_at = now();
        $event->processing_error = null;
        $event->save();
    }

    private function resolvePlanFromItems(array $items, string $ownerType): string
    {
        $proIds = (array) config('billing.plans.pro.price_ids', []);
        $teamIds = (array) config('billing.plans.team.price_ids', []);

        $seen = [];

        foreach ($items as $it) {
            $priceId = Arr::get($it, 'price.id') ?? Arr::get($it, 'price_id') ?? null;
            if (is_string($priceId) && $priceId !== '') {
                $seen[] = $priceId;
            }
        }

        // Team subscriptions must map to Team plan
        if ($ownerType === 'team') {
            foreach ($seen as $pid) {
                if (in_array($pid, $teamIds, true)) return PlanLimits::PLAN_TEAM;
            }
            return PlanLimits::PLAN_FREE;
        }

        // User subscriptions map to Pro (or Free)
        foreach ($seen as $pid) {
            if (in_array($pid, $proIds, true)) return PlanLimits::PLAN_PRO;
        }

        return PlanLimits::PLAN_FREE;
    }

    private function resolveAddonsFromItems(array $items, string $ownerType): array
    {
        $addonMon = (array) config('billing.addons.extra_monitor_pack.price_ids', []);
        $addonSeat = (array) config('billing.addons.extra_seat_pack.price_ids', []);
        $addon2 = (array) config('billing.addons.interval_override_2.price_ids', []);
        $addon1 = (array) config('billing.addons.interval_override_1.price_ids', []);

        $extraMonitorPacks = 0;
        $extraSeatPacks = 0;

        $intervalOverride = null; // 2 or 1

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

            if (in_array($priceId, $addon2, true)) {
                $intervalOverride = 2;
            }
            if (in_array($priceId, $addon1, true)) {
                $intervalOverride = 1;
            }
        }

        return [
            'extra_monitor_packs' => $extraMonitorPacks,
            'extra_seat_packs' => $extraSeatPacks,
            'interval_override_minutes' => $intervalOverride,
        ];
    }
}