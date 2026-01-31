<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingWebhookEvent;
use App\Services\Billing\BillingOwnerResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingSyncController extends Controller
{
    /**
     * Return current billing sync status as JSON for polling.
     * This endpoint must be lightweight - no external API calls.
     */
    public function syncStatus(Request $request, BillingOwnerResolver $resolver): JsonResponse
    {
        $user = $request->user();
        $context = $resolver->resolve($user);
        $billable = $context['billable'];

        $lastWebhook = BillingWebhookEvent::query()
            ->where(function ($q) use ($billable, $context) {
                if ($context['type'] === 'team') {
                    $q->whereJsonContains('payload->data->custom_data->owner_type', 'team')
                      ->whereJsonContains('payload->data->custom_data->owner_id', $billable->id);
                } else {
                    $q->whereJsonContains('payload->data->custom_data->owner_type', 'user')
                      ->whereJsonContains('payload->data->custom_data->owner_id', $billable->id);
                }
            })
            ->whereNotNull('processed_at')
            ->orderByDesc('processed_at')
            ->first();

        $subscription = method_exists($billable, 'subscription') 
            ? $billable->subscription('default') 
            : null;

        $nextBillingDate = null;
        if ($subscription && $subscription->ends_at) {
            $nextBillingDate = $subscription->ends_at->toIso8601String();
        } elseif ($billable->next_bill_at) {
            $nextBillingDate = $billable->next_bill_at->toIso8601String();
        }

        return response()->json([
            'current_plan' => strtolower($billable->billing_plan ?? 'free'),
            'billing_status' => strtolower($billable->billing_status ?? 'free'),
            'next_billing_date' => $nextBillingDate,
            'checkout_in_progress' => (bool) ($billable->checkout_in_progress_until && $billable->checkout_in_progress_until->isFuture()),
            'last_webhook_processed_at' => $lastWebhook?->processed_at?->toIso8601String(),
            'is_synced' => in_array(strtolower($billable->billing_status ?? 'free'), ['active', 'free', 'canceled']),
        ]);
    }

    /**
     * Clear checkout_in_progress flag safely.
     * Only the billing owner can clear this.
     */
    public function clearPending(Request $request, BillingOwnerResolver $resolver): JsonResponse
    {
        $user = $request->user();
        $context = $resolver->resolve($user);
        $billable = $context['billable'];
        $team = $context['team'];

        if (! $resolver->canManage($user, $team)) {
            return response()->json([
                'success' => false,
                'error' => 'Only the team owner can manage billing.',
            ], 403);
        }

        $billable->checkout_in_progress_until = null;
        $billable->save();

        return response()->json([
            'success' => true,
            'message' => 'Pending checkout cleared.',
        ]);
    }
}
