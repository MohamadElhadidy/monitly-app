<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaddleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PDF; // Assuming you'll use a PDF library like barryvdh/laravel-dompdf

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $billingService,
        private readonly PaddleService $paddleService,
    ) {}

    /**
     * Main billing dashboard
     */
    public function index(Request $request)
    {
        return view('livewire.pages.billing.index');
    }

    /**
     * Subscription management dashboard
     * Shows current plan, usage, add-ons, and next billing date
     */
    public function subscription(Request $request)
    {
        $user = $request->user();
        $billing = $this->billingService->current($user);
        $usage = $this->billingService->getUsage($user);
        $upcomingInvoice = $this->billingService->getUpcomingInvoice($user);
        
        return view('livewire.pages.billing.subscription', [
            'billing' => $billing,
            'usage' => $usage,
            'upcomingInvoice' => $upcomingInvoice,
            'plan' => config("billing.plans.{$billing['plan']}"),
        ]);
    }

    /**
     * Invoice portal - list all invoices
     */
    public function invoices(Request $request)
    {
        $user = $request->user();
        $invoices = $this->billingService->getInvoices($user);
        
        return view('livewire.pages.billing.invoices', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Download specific invoice as PDF
     */
    public function downloadInvoice(Request $request, $id)
    {
        $user = $request->user();
        $invoice = $this->billingService->getInvoice($user, $id);
        
        if (!$invoice) {
            abort(404, 'Invoice not found');
        }
        
        // Verify ownership
        if ($invoice->user_id !== $user->id) {
            abort(403, 'Unauthorized access');
        }
        
        $pdf = PDF::loadView('pdf.invoice', ['invoice' => $invoice]);
        return $pdf->download("invoice-{$invoice->number}.pdf");
    }

    /**
     * Plan comparison and selection page
     */
    public function plans(Request $request)
    {
        $user = $request->user();
        $currentPlan = $user->billing_plan ?? 'free';
        $plans = config('billing.plans');
        $addons = config('billing.addons');
        
        return view('livewire.pages.billing.plans', [
            'plans' => $plans,
            'addons' => $addons,
            'currentPlan' => $currentPlan,
        ]);
    }

    /**
     * Payment method management
     */
    public function paymentMethods(Request $request)
    {
        $user = $request->user();
        $paymentMethods = $this->paddleService->getPaymentMethods($user);
        
        return view('livewire.pages.billing.payment-methods', [
            'paymentMethods' => $paymentMethods,
        ]);
    }

    /**
     * Update payment method
     */
    public function updatePaymentMethod(Request $request)
    {
        try {
            $validated = $request->validate([
                'payment_method_id' => 'required|string',
            ]);
            
            $user = $request->user();
            $result = $this->billingService->updatePaymentMethod(
                $user,
                $validated['payment_method_id']
            );
            
            if ($result) {
                return back()->with('success', 'Payment method updated successfully.');
            }
            
            return back()->with('error', 'Failed to update payment method.');
        } catch (\Exception $e) {
            Log::error('Payment method update error', ['error' => $e->getMessage()]);
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Billing settings page
     */
    public function settings(Request $request)
    {
        $user = $request->user();
        
        return view('livewire.pages.billing.settings', [
            'user' => $user,
        ]);
    }

    /**
     * Update billing settings
     */
    public function updateSettings(Request $request)
    {
        try {
            $validated = $request->validate([
                'billing_email' => 'nullable|email',
                'company_name' => 'nullable|string|max:255',
                'tax_id' => 'nullable|string|max:50',
                'billing_address' => 'nullable|string|max:500',
            ]);
            
            $user = $request->user();
            $user->update($validated);
            
            return back()->with('success', 'Billing settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('Settings update error', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to update settings.');
        }
    }

    /**
     * Usage dashboard - show current usage vs limits
     */
    public function usage(Request $request)
    {
        $user = $request->user();
        $usage = $this->billingService->getUsage($user);
        $plan = config("billing.plans.{$user->billing_plan}");
        
        return view('livewire.pages.billing.usage', [
            'usage' => $usage,
            'plan' => $plan,
        ]);
    }

    /**
     * Initiate plan change (upgrade/downgrade)
     */
    public function changePlan(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan' => 'required|in:free,pro,team',
                'addons' => 'nullable|array',
            ]);
            
            $user = $request->user();
            $newPlan = $validated['plan'];
            $currentPlan = $user->billing_plan ?? 'free';
            
            // Don't allow changing to the same plan
            if ($newPlan === $currentPlan) {
                return back()->with('info', 'You are already on this plan.');
            }
            
            // Handle downgrade to free
            if ($newPlan === 'free') {
                return $this->cancel($request);
            }
            
            // Calculate proration if upgrading
            if ($user->billing_status === 'active') {
                $proration = $this->billingService->calculateProration($user, $newPlan);
                
                // Show proration preview and confirmation
                return view('livewire.pages.billing.confirm-plan-change', [
                    'currentPlan' => $currentPlan,
                    'newPlan' => $newPlan,
                    'proration' => $proration,
                ]);
            }
            
            // If no active subscription, create new one
            return $this->checkout($request);
            
        } catch (\Exception $e) {
            Log::error('Plan change error', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to change plan: ' . $e->getMessage());
        }
    }

    /**
     * Existing checkout method (from original file)
     */
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
            
            $addons = $validated['addons'] ?? [];
            if (empty($addons) && !empty($validated['addon'])) {
                $addons = [$validated['addon']];
            }
            $addons = array_filter($addons);

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

    /**
     * Existing checkout page (from original file)
     */
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

    /**
     * Existing success page (from original file)
     */
    public function success(Request $request)
    {
        return view('livewire.pages.billing.success');
    }

    /**
     * Existing cancel subscription (from original file)
     */
    public function cancel(Request $request)
    {
        try {
            $user = $request->user();
            
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

    /**
     * Reactivate cancelled subscription
     */
    public function reactivate(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->billing_status !== 'cancelled') {
                return back()->with('error', 'No cancelled subscription to reactivate.');
            }
            
            $result = $this->paddleService->reactivateSubscription($user);
            
            if ($result) {
                Log::info('Subscription reactivated', ['user_id' => $user->id]);
                return back()->with('success', 'Your subscription has been reactivated.');
            }
            
            return back()->with('error', 'Failed to reactivate subscription.');
        } catch (\Exception $e) {
            Log::error('Reactivate error', ['error' => $e->getMessage()]);
            return back()->with('error', 'An error occurred while reactivating.');
        }
    }

    /**
     * Apply promo/discount code
     */
    public function applyPromoCode(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:50',
            ]);
            
            $user = $request->user();
            $result = $this->paddleService->applyPromoCode($user, $validated['code']);
            
            if ($result['success']) {
                return back()->with('success', "Promo code applied! {$result['discount']}");
            }
            
            return back()->with('error', $result['message'] ?? 'Invalid promo code.');
        } catch (\Exception $e) {
            Log::error('Promo code error', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to apply promo code.');
        }
    }

    /**
     * Request refund
     */
    public function requestRefund(Request $request)
    {
        try {
            $validated = $request->validate([
                'invoice_id' => 'required|string',
                'reason' => 'required|string|max:500',
            ]);
            
            $user = $request->user();
            
            // Log refund request for admin review
            Log::info('Refund requested', [
                'user_id' => $user->id,
                'invoice_id' => $validated['invoice_id'],
                'reason' => $validated['reason'],
            ]);
            
            // Send email to admin
            // Mail::to(config('mail.admin'))->send(new RefundRequestMail($user, $validated));
            
            return back()->with('success', 'Refund request submitted. Our team will review and contact you within 24 hours.');
        } catch (\Exception $e) {
            Log::error('Refund request error', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to submit refund request.');
        }
    }
}