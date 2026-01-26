<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaddleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $billingService,
        private readonly PaddleService $paddleService,
    ) {}

    public function index(Request $request)
    {
        return view('livewire.pages.billing.index');
    }

    public function checkout(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan' => 'required|in:pro,team',
                'addon' => 'nullable|string',
            ]);

            $user = $request->user();
            $plan = $validated['plan'];
            $addon = $validated['addon'] ?? null;

            $checkout = $this->paddleService->createCheckoutSession(
                billable: $user,
                plan: $plan,
                addon: $addon,
            );

            if (!$checkout) {
                return back()->with('error', 'Failed to create checkout. Please ensure Paddle price IDs are configured.');
            }

            Log::info('Checkout initiated', ['user_id' => $user->id, 'plan' => $plan]);

            return redirect()->route('billing.checkout.page', [
                'plan' => $plan,
                'addon' => $addon,
            ])->with('checkout', $checkout);
        } catch (\Exception $e) {
            Log::error('Checkout error', ['error' => $e->getMessage()]);
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function checkoutPage(Request $request)
    {
        $checkout = $request->session()->get('checkout');
        $plan = $request->get('plan', 'pro');
        $addon = $request->get('addon');

        if (!$checkout) {
            // If no checkout session, create one
            $user = $request->user();
            $checkout = $this->paddleService->createCheckoutSession(
                billable: $user,
                plan: $plan,
                addon: $addon,
            );

            if (!$checkout) {
                return redirect()->route('billing.index')
                    ->with('error', 'Failed to create checkout. Please ensure Paddle price IDs are configured.');
            }
        }

        return view('livewire.pages.billing.checkout', [
            'checkout' => $checkout,
            'plan' => $plan,
            'addon' => $addon,
        ]);
    }

    public function success(Request $request)
    {
        return view('livewire.pages.billing.success');
    }

    public function cancel(Request $request)
    {
        try {
            $user = $request->user();
            $this->paddleService->cancelSubscription($user);
            Log::info('Subscription cancelled', ['user_id' => $user->id]);
            return back()->with('success', 'Subscription cancelled');
        } catch (\Exception $e) {
            Log::error('Cancel error', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to cancel');
        }
    }
}