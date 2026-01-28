<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Main billing controller for subscription management
 * Compatible with Laravel Cashier Paddle v2.6
 */
class BillingController extends Controller
{
    /**
     * Display billing dashboard.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        return view('livewire.pages.billing.index', [
            'user' => $user,
            'subscription' => $user->subscription(),
            'subscriptions' => $user->subscriptions,
            'transactions' => $user->transactions()->latest()->paginate(10),
        ]);
    }

    /**
     * Redirect to Paddle customer portal.
     * 
     * Creates a portal session and redirects user to Paddle's hosted portal
     * where they can manage their subscription, payment methods, and view invoices.
     */
    public function portal(Request $request)
    {
        $user = $request->user();

        // Ensure we have a Cashier customer record (customers table + Paddle customer)
        if (!$user->customer) {
            $user->createAsCustomer();
        }

        $customerId = $user->customer->paddle_id;

        // Optional: include the active subscription id so Paddle returns deep links
        $subscription = $user->subscription('default') ?? $user->subscription();
        $subscriptionId = $subscription?->paddle_id;

        $apiKey = config('services.paddle.api_key') ?? env('PADDLE_API_KEY');
        $baseUrl = rtrim(config('services.paddle.base_url', 'https://api.paddle.com/'), '/') . '/';

        if (!$apiKey) {
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

            if (!$response->successful()) {
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

            if (!$url) {
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


    /**
     * Cancel subscription (with grace period).
     */
    public function cancel(Request $request)
    {
        $subscription = $request->user()->subscription();

        if ($subscription && $subscription->active()) {
            $subscription->cancel();

            return redirect()->route('billing.index')
                ->with('success', 'Subscription cancelled. You can continue using the service until the end of your billing period.');
        }

        return redirect()->route('billing.index')
            ->with('error', 'No active subscription to cancel.');
    }

    /**
     * Cancel subscription immediately.
     */
    public function cancelNow(Request $request)
    {
        $subscription = $request->user()->subscription();

        if ($subscription && $subscription->active()) {
            $subscription->cancelNow();

            return redirect()->route('billing.index')
                ->with('success', 'Subscription cancelled immediately.');
        }

        return redirect()->route('billing.index')
            ->with('error', 'No active subscription to cancel.');
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resume(Request $request)
    {
        $subscription = $request->user()->subscription();

        if ($subscription && $subscription->onGracePeriod()) {
            $subscription->resume();

            return redirect()->route('billing.index')
                ->with('success', 'Subscription resumed successfully!');
        }

        return redirect()->route('billing.index')
            ->with('error', 'Unable to resume subscription.');
    }

    /**
     * Swap to a different plan/price.
     */
    public function swap(Request $request)
    {
        $request->validate([
            'price_id' => 'required|string',
        ]);

        $subscription = $request->user()->subscription();

        if ($subscription && $subscription->active()) {
            $subscription->swap($request->input('price_id'));

            return redirect()->route('billing.index')
                ->with('success', 'Plan updated successfully!');
        }

        return redirect()->route('billing.index')
            ->with('error', 'No active subscription to update.');
    }

    /**
     * Update subscription quantity.
     */
    public function updateQuantity(Request $request)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $subscription = $request->user()->subscription();

        if ($subscription && $subscription->active()) {
            $subscription->updateQuantity($request->input('quantity'));

            return redirect()->route('billing.index')
                ->with('success', 'Quantity updated successfully!');
        }

        return redirect()->route('billing.index')
            ->with('error', 'No active subscription to update.');
    }

    /**
     * Pause subscription.
     */
    public function pause(Request $request)
    {
        $subscription = $request->user()->subscription();

        if ($subscription && $subscription->active() && !$subscription->paused()) {
            $subscription->pause();

            return redirect()->route('billing.index')
                ->with('success', 'Subscription paused.');
        }

        return redirect()->route('billing.index')
            ->with('error', 'Unable to pause subscription.');
    }

    /**
     * Unpause subscription.
     */
    public function unpause(Request $request)
    {
        $subscription = $request->user()->subscription();

        if ($subscription && $subscription->paused()) {
            $subscription->unpause();

            return redirect()->route('billing.index')
                ->with('success', 'Subscription resumed.');
        }

        return redirect()->route('billing.index')
            ->with('error', 'Subscription is not paused.');
    }

    /**
     * Download invoice/receipt.
     */
    public function downloadInvoice(Request $request, $transactionId)
    {
        $transaction = $request->user()
            ->transactions()
            ->where('id', $transactionId)
            ->firstOrFail();

        return redirect($transaction->receipt_url);
    }
}