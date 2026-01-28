<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaddleService;
use Illuminate\Http\Request;
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
                'addon' => 'nullable|string',
            ]);

            $user = $request->user();
            $plan = $validated['plan'];
            
            // 🔥 FIX: Use team as billable for team plans
            $billable = $plan === 'team' ? $user->currentTeam : $user;
            
            // Validate that team exists for team plans
            if ($plan === 'team' && !$billable) {
                return back()->with('error', 'You must be part of a team to subscribe to the Team plan.');
            }
            
            $addons = $validated['addons'] ?? [];
            if (empty($addons) && !empty($validated['addon'])) {
                $addons = [$validated['addon']];
            }
            $addons = array_filter($addons);

            $checkout = $this->paddleService->createCheckoutSession(
                billable: $billable,
                plan: $plan,
                addons: $addons,
            );

            if (!$checkout || ($checkout['id'] ?? null) === 'no_items') {
                return back()->with('error', $checkout['message'] ?? 'Failed to create checkout.');
            }

            Log::info('Checkout initiated', [
                'user_id' => $user->id,
                'billable_type' => get_class($billable),
                'billable_id' => $billable->id,
                'plan' => $plan,
                'addons' => $addons,
            ]);

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
        
        if (empty($addons) && $request->has('addon')) {
            $addons = [$request->get('addon')];
        }
        $addons = array_filter($addons);

        if (!$checkout) {
            $user = $request->user();
            
            // 🔥 FIX: Use team as billable for team plans
            $billable = $plan === 'team' ? $user->currentTeam : $user;
            
            // Validate that team exists for team plans
            if ($plan === 'team' && !$billable) {
                return redirect()->route('billing.index')
                    ->with('error', 'You must be part of a team to subscribe to the Team plan.');
            }
            
            $checkout = $this->paddleService->createCheckoutSession(
                billable: $billable,
                plan: $plan,
                addons: $addons,
            );

            if (!$checkout || ($checkout['id'] ?? null) === 'no_items') {
                return redirect()->route('billing.index')
                    ->with('error', $checkout['message'] ?? 'Failed to create checkout.');
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

    /**
     * Redirect to Paddle Customer Portal
     */
    public function manageSubscription(Request $request)
    {
        try {
            $user = $request->user();
            
            // Determine if managing user or team subscription
            $billable = $user;
            
            // If user has a team with a subscription, manage that instead
            if ($user->currentTeam && $user->currentTeam->paddle_customer_id) {
                $billable = $user->currentTeam;
            }
            
            if (!$billable->paddle_customer_id) {
                return back()->with('error', 'No subscription found. Please subscribe first.');
            }

            $portalUrl = $this->paddleService->generatePortalUrl($billable);
            
            if (!$portalUrl) {
                return back()->with('error', 'Unable to access portal. Contact support.');
            }

            Log::info('Customer portal accessed', [
                'user_id' => $user->id,
                'billable_type' => get_class($billable),
                'billable_id' => $billable->id,
            ]);

            return redirect()->away($portalUrl);
            
        } catch (\Exception $e) {
            Log::error('Customer portal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Error accessing portal. Contact support.');
        }
    }

    /**
     * Cancel - redirects to portal
     */
    public function cancel(Request $request)
    {
        return $this->manageSubscription($request);
    }
}