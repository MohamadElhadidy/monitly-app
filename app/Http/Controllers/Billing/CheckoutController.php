<?php

namespace App\Http\Controllers\Billing;

use App\Services\Billing\BillingOwnerResolver;
use Illuminate\Http\Request;

class CheckoutController
{
    public function show(Request $request, BillingOwnerResolver $resolver)
    {
        $plan = $request->string('plan', 'pro')->toString();
        $interval = $request->string('interval', 'monthly')->toString();

        $plan = in_array($plan, ['free', 'pro', 'team', 'business'], true) ? $plan : 'pro';
        $interval = in_array($interval, ['monthly', 'yearly'], true) ? $interval : 'monthly';

        $plans = config('billing.plans', []);

        $user = $request->user();
        $context = $resolver->resolveForPlan($user, $plan);

        if (! $context) {
            return redirect()->route('billing.index')
                ->with('error', 'Team plans require a team.');
        }

        $billable = $context['billable'];
        $team = $context['team'];

        if (! $resolver->canManage($user, $team)) {
            return redirect()->route('billing.index')
                ->with('error', 'Only the team owner can manage billing.');
        }

        if ($plan === 'free') {
            return redirect()->route('billing.index')
                ->with('error', 'Free plan has no checkout.');
        }

        $priceId = $plans[$plan]['price_ids'][$interval] ?? null;
        if (! $priceId) {
            return redirect()->route('billing.index')
                ->with('error', 'Paddle price ID not configured for this plan.');
        }

        if ($billable->checkout_in_progress_until && $billable->checkout_in_progress_until->isFuture()) {
            return redirect()->route('billing.index')
                ->with('error', 'Checkout already in progress. Please wait a few minutes.');
        }

        $billable->checkout_in_progress_until = now()->addMinutes(10);
        $billable->save();

        $subscription = method_exists($billable, 'subscription') ? $billable->subscription('default') : null;
        $requiresCheckout = ! $subscription || ! $subscription->active();

        $checkout = null;

        if ($requiresCheckout) {
            if (! $billable->paddle_customer_id) {
                $customer = $billable->createAsCustomer();
                $billable->paddle_customer_id = $customer->paddle_id;
                $billable->save();
            }

            $checkout = $billable
                ->subscribe($priceId, 'default')
                ->customData([
                    'owner_type' => $context['type'],
                    'owner_id' => $billable->id,
                    'selected_plan' => $plan,
                    'selected_interval' => $interval,
                ])
                ->returnTo(route('billing.success'));
        }

        return view('livewire.pages.billing.checkout', [
            'checkout' => $checkout,
            'requiresCheckout' => $requiresCheckout,
            'plan' => $plan,
            'interval' => $interval,
            'planConfig' => $plans[$plan] ?? [],
        ]);
    }

    public function applyChange(Request $request, BillingOwnerResolver $resolver)
    {
        $plan = $request->string('plan', 'pro')->toString();
        $interval = $request->string('interval', 'monthly')->toString();

        $plan = in_array($plan, ['pro', 'team', 'business'], true) ? $plan : 'pro';
        $interval = in_array($interval, ['monthly', 'yearly'], true) ? $interval : 'monthly';

        $plans = config('billing.plans', []);
        $priceId = $plans[$plan]['price_ids'][$interval] ?? null;

        if (! $priceId) {
            return redirect()->route('billing.index')
                ->with('error', 'Paddle price ID not configured for this plan.');
        }

        $user = $request->user();
        $context = $resolver->resolveForPlan($user, $plan);

        if (! $context) {
            return redirect()->route('billing.index')
                ->with('error', 'Team plans require a team.');
        }

        $billable = $context['billable'];
        $team = $context['team'];

        if (! $resolver->canManage($user, $team)) {
            return redirect()->route('billing.index')
                ->with('error', 'Only the team owner can manage billing.');
        }

        $subscription = method_exists($billable, 'subscription') ? $billable->subscription('default') : null;
        if ($subscription && $subscription->active()) {
            $subscription->swap($priceId);
        } else {
            return redirect()->route('billing.checkout', ['plan' => $plan, 'interval' => $interval]);
        }

        $billable->checkout_in_progress_until = now()->addMinutes(10);
        $billable->save();

        return redirect()->route('billing.success');
    }
}
