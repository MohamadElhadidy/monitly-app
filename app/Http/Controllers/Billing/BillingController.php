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
                'plan' => 'required|in:free,pro,team',
                'addons' => 'nullable|array',
                'addons.*' => 'string',
                // Backward compatibility
                'addon' => 'nullable|string',
            ]);

            $user = $request->user();
            $plan = $validated['plan'];
            
            // Support both single addon (backward compat) and multiple addons
            $addons = $validated['addons'] ?? [];
            if (empty($addons) && !empty($validated['addon'])) {
                $addons = [$validated['addon']];
            }
            $addons = array_filter($addons); // Remove empty values

            $checkout = $this->paddleService->createCheckoutSession(
                billable: $user,
                plan: $plan,
                addons: $addons,
            );

            if (!$checkout || ($checkout['id'] ?? null) === 'no_items') {
                return back()->with('error', $checkout['message'] ?? 'Failed to create checkout. Please ensure Paddle price IDs are configured.');
            }

            Log::info('Checkout initiated', ['user_id' => $user->id, 'plan' => $plan, 'addons' => $addons]);

            return redirect()->route('billing.checkout.page', [
                'plan' => $plan,
                'addons' => $addons,
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
        $addons = $request->get('addons', []);
        
        // Backward compatibility
        if (empty($addons) && $request->has('addon')) {
            $addons = [$request->get('addon')];
        }
        $addons = array_filter($addons);

        if (!$checkout) {
            // If no checkout session, create one
            $user = $request->user();
            $checkout = $this->paddleService->createCheckoutSession(
                billable: $user,
                plan: $plan,
                addons: $addons,
            );

            if (!$checkout || ($checkout['id'] ?? null) === 'no_items') {
                return redirect()->route('billing.index')
                    ->with('error', $checkout['message'] ?? 'Failed to create checkout. Please ensure Paddle price IDs are configured.');
            }
        }

        return view('livewire.pages.billing.checkout', [
            'checkout' => $checkout,
            'plan' => $plan,
            'addons' => $addons,
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
            
            // Check if user has an active subscription
            if (!in_array($user->billing_status ?? 'free', ['active', 'grace'])) {
                return back()->with('error', 'No active subscription to cancel.');
            }
            
            $result = $this->paddleService->cancelSubscription($user);
            
            if ($result) {
                Log::info('Subscription cancelled', ['user_id' => $user->id]);
                return back()->with('success', 'Your subscription has been cancelled. You will retain access until the end of your billing period.');
            } else {
                return back()->with('error', 'Failed to cancel subscription. Please try again or contact support.');
            }
        } catch (\Exception $e) {
            Log::error('Cancel error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', 'An error occurred while cancelling. Please contact support if the issue persists.');
        }
    }
}