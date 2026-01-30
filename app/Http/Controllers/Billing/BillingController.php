<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingOwnerResolver;
use App\Services\Billing\PaddleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function portal(Request $request, BillingOwnerResolver $resolver)
    {
        $user = $request->user();
        $context = $resolver->resolve($user);
        $billable = $context['billable'];

        if (! $resolver->canManage($user, $context['team'])) {
            return redirect()->route('billing.index')
                ->with('error', 'Only the team owner can manage billing.');
        }

        if (! $billable->customer) {
            $billable->createAsCustomer();
        }

        $customerId = $billable->customer->paddle_id;

        $subscription = $billable->subscription('default') ?? $billable->subscription();
        $subscriptionId = $subscription?->paddle_id;

        $apiKey = config('services.paddle.api_key') ?? env('PADDLE_API_KEY');
        $baseUrl = rtrim(config('services.paddle.base_url', 'https://api.paddle.com/'), '/') . '/';

        if (! $apiKey) {
            return redirect()->route('billing.index')->with('error', 'Paddle API key is not configured (PADDLE_API_KEY).');
        }

        $payload = [];
        if ($subscriptionId) {
            $payload['subscription_ids'] = [$subscriptionId];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . "customers/{$customerId}/portal-sessions", $payload);

            if (! $response->successful()) {
                Log::error('Paddle portal session failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                    'customer_id' => $customerId,
                    'subscription_id' => $subscriptionId,
                ]);

                return redirect()->route('billing.index')
                    ->with('error', 'Could not open billing portal. Please try again or contact support.');
            }

            $data = $response->json('data');
            $url = data_get($data, 'urls.general.overview');

            if (! $url) {
                Log::error('Paddle portal session response missing url', ['response' => $response->json()]);
                return redirect()->route('billing.index')
                    ->with('error', 'Could not open billing portal (missing URL).');
            }

            return redirect()->away($url);
        } catch (\Throwable $e) {
            Log::error('Paddle portal session exception', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId,
            ]);

            return redirect()->route('billing.index')
                ->with('error', 'Could not open billing portal. Please try again.');
        }
    }

    public function cancel(Request $request, BillingOwnerResolver $resolver, PaddleService $paddleService)
    {
        $user = $request->user();
        $context = $resolver->resolve($user);
        $billable = $context['billable'];

        if (! $resolver->canManage($user, $context['team'])) {
            return redirect()->route('billing.index')
                ->with('error', 'Only the team owner can manage billing.');
        }

        if ($billable->paddle_subscription_id) {
            $paddleService->cancelSubscription($billable->paddle_subscription_id, false);
            $billable->billing_status = 'canceling';
            $billable->save();

            return redirect()->route('billing.index')
                ->with('success', 'Your plan will downgrade at the end of the current billing period.');
        }

        return redirect()->route('billing.index')
            ->with('error', 'No active subscription to cancel.');
    }

    public function downloadInvoice(Request $request, $transactionId, BillingOwnerResolver $resolver)
    {
        $user = $request->user();
        $context = $resolver->resolve($user);
        $billable = $context['billable'];

        if (! $resolver->canManage($user, $context['team'])) {
            return redirect()->route('billing.index')
                ->with('error', 'Only the team owner can download invoices.');
        }

        $transaction = $billable
            ->transactions()
            ->where('id', $transactionId)
            ->firstOrFail();

        return redirect($transaction->receipt_url ?? $transaction->invoice_url ?? '#');
    }
}
