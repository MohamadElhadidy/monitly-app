<?php

namespace App\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Support\BillingPlanResolver;
use Carbon\Carbon;

class PaddleWebhookController
{
    public function handle(Request $request)
    {
        Log::channel('paddle')->info('Webhook received', [
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
        ]);

        try {
            $payload = $request->all();

            $event = $payload['event_type'] ?? null;
            $data  = $payload['data'] ?? [];

            Log::channel('paddle')->info('Parsed event', [
                'event' => $event,
            ]);

            // Ignore non-subscription events (you can expand later if you want transaction-based sync)
            if (!is_string($event) || !str_starts_with($event, 'subscription.')) {
                Log::channel('paddle')->info('Event ignored (not subscription)');
                return response()->json(['ignored' => true]);
            }

            $customerId = $data['customer_id'] ?? null;

            if (!$customerId) {
                Log::channel('paddle')->error('Missing customer_id', ['data' => $data]);
                return response()->json(['error' => 'Missing customer_id'], 400);
            }

            Log::channel('paddle')->info('Resolving user', ['customer_id' => $customerId]);

            $user = User::where('paddle_customer_id', $customerId)->first();

            if (!$user && isset($data['customer']['id'])) {
                $user = User::where('paddle_customer_id', $data['customer']['id'])->first();
            }

            if (!$user) {
                Log::channel('paddle')->error('User not found', ['customer_id' => $customerId]);
                return response()->json(['error' => 'User not found'], 404);
            }

            Log::channel('paddle')->info('User resolved', [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);

            match ($event) {
                'subscription.created',
                'subscription.updated',
                'subscription.activated' => $this->syncFromPaddle($user, $data),

                'subscription.canceled' => $this->cancel($user, $data),

                default => Log::channel('paddle')->warning('Unhandled subscription event', [
                    'event' => $event,
                ]),
            };

            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {
            Log::channel('paddle')->critical('Webhook crashed', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Webhook error'], 500);
        }
    }

    private function syncFromPaddle(User $user, array $data): void
    {
        Log::channel('paddle')->info('Sync start', [
            'user_id' => $user->id,
            'before'  => $user->only([
                'billing_plan',
                'billing_status',
                'addon_extra_monitor_packs',
                'addon_interval_override_minutes',
                'paddle_subscription_id',
                'next_bill_at',
                'last_bill_at',
                'grace_ends_at',
            ]),
        ]);

        $items = $data['items'] ?? [];

        // Subscription id
        $subId = $data['id'] ?? $data['subscription_id'] ?? null;
        if ($subId) {
            $user->paddle_subscription_id = $subId;
        }

        $user->billing_status = 'active';

        // RESET — Paddle is source of truth
        $user->billing_plan = 'free';
        $user->addon_extra_monitor_packs = 0;
        $user->addon_interval_override_minutes = null;

        foreach ($items as $item) {
            // ✅ Paddle item shape: item.price.id
            $priceId = $item['price']['id'] ?? $item['price_id'] ?? null;
            $qty     = (int)($item['quantity'] ?? 1);

            Log::channel('paddle')->info('Processing item', [
                'price_id' => $priceId,
                'qty'      => $qty,
            ]);

            if (!$priceId) continue;

            $plan = BillingPlanResolver::planFromPriceId($priceId);
            if ($plan) {
                Log::channel('paddle')->info('Plan detected', ['plan' => $plan]);
                $user->billing_plan = $plan;
                continue;
            }

            if (in_array($priceId, config('billing.addons.extra_monitor_pack.price_ids', []), true)) {
                Log::channel('paddle')->info('Addon: extra monitor packs', ['added' => $qty]);
                $user->addon_extra_monitor_packs += max(0, $qty);
                continue;
            }

            if (in_array($priceId, config('billing.addons.faster_checks_5min.price_ids', []), true)) {
                Log::channel('paddle')->info('Addon: faster checks (5min)');
                $user->addon_interval_override_minutes = 5;
                continue;
            }

            Log::channel('paddle')->warning('Unknown price_id', ['price_id' => $priceId]);
        }

        // ✅ Dates (convert from Paddle ISO -> Carbon -> MySQL datetime)
        // Subscription payload has: next_billed_at, first_billed_at, started_at, current_billing_period, etc.
        $nextBill = $data['next_billed_at'] ?? null;
        $firstBilled = $data['first_billed_at'] ?? null;
        $startedAt = $data['started_at'] ?? null;
        $graceEnds = $data['grace_period_ends_at'] ?? null;

        // Fallbacks from items if needed
        if (!$nextBill && !empty($items[0]['next_billed_at'])) {
            $nextBill = $items[0]['next_billed_at'];
        }
        if (!$firstBilled && !empty($items[0]['previously_billed_at'])) {
            $firstBilled = $items[0]['previously_billed_at'];
        }

        if ($nextBill) {
            $user->next_bill_at = $this->parsePaddleDate($nextBill);
        }

        // last_bill_at: set from first_billed_at, otherwise started_at, otherwise previously_billed_at
        $lastBillRaw = $firstBilled ?: ($startedAt ?: null);
        if ($lastBillRaw) {
            $user->last_bill_at = $this->parsePaddleDate($lastBillRaw);
        }

        if ($graceEnds) {
            $user->grace_ends_at = $this->parsePaddleDate($graceEnds);
        }

        $user->has_payment_method = 1;
        $user->first_paid_at = $user->first_paid_at ?? now();

        $user->save();

        Log::channel('paddle')->info('Sync completed', [
            'user_id' => $user->id,
            'after'   => $user->only([
                'billing_plan',
                'billing_status',
                'addon_extra_monitor_packs',
                'addon_interval_override_minutes',
                'paddle_subscription_id',
                'next_bill_at',
                'last_bill_at',
                'grace_ends_at',
            ]),
        ]);
    }

    private function cancel(User $user, array $data): void
    {
        Log::channel('paddle')->info('Subscription canceled', [
            'user_id' => $user->id,
        ]);

        $user->billing_status = 'free';
        $user->billing_plan = 'free';

        $user->paddle_subscription_id = null;
        $user->addon_extra_monitor_packs = 0;
        $user->addon_interval_override_minutes = null;

        $user->save();

        Log::channel('paddle')->info('User downgraded to free', [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Paddle sends ISO8601 strings like: 2026-01-28T18:30:36.997471Z
     * Convert to Carbon so Eloquent writes a valid MySQL DATETIME.
     */
    private function parsePaddleDate(?string $value): ?Carbon
    {
        if (!$value || !is_string($value)) return null;

        try {
            // parse as UTC
            return Carbon::parse($value)->utc();
        } catch (\Throwable $e) {
            Log::channel('paddle')->warning('Failed to parse Paddle date', [
                'value' => $value,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}