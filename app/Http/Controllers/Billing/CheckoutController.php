<?php

namespace App\Http\Controllers\Billing;

use Illuminate\Http\Request;

class CheckoutController
{
    public function show(Request $request)
    {
        $plan = $request->string('plan', 'pro')->toString();
        $interval = $request->string('interval', 'monthly')->toString();

        $plan = in_array($plan, ['free', 'pro', 'team'], true) ? $plan : 'pro';
        $interval = in_array($interval, ['monthly', 'yearly'], true) ? $interval : 'monthly';

        $plans = config('billing.plans', []);
        $addons = config('billing.addons', []);

        $selectedAddons = $request->input('addons', []);
        if (!is_array($selectedAddons)) $selectedAddons = [];
        
        if (isset($selectedAddons['faster_checks_5min'])) {
            $selectedAddons['faster_checks_5min'] = (int)($selectedAddons['faster_checks_5min'] ?? 0) > 0 ? 1 : 0;
            
            
            if ($selectedAddons['faster_checks_5min'] === 0) {
            unset($selectedAddons['faster_checks_5min']);
            }
            }

        // --- helpers ---
        $pickPriceId = function (array $priceIds, string $interval): ?string {
            $ids = array_values(array_filter($priceIds));
            if (count($ids) >= 2) return $interval === 'yearly' ? $ids[1] : $ids[0];
            return $ids[0] ?? null;
        };

        // Build Cashier payload (multiple prices + quantities)
        $pricePayload = [];
        $lineItems = [];

        // base plan (pro/team only)
        if ($plan !== 'free') {
            $baseIds = $plans[$plan]['price_ids'] ?? [];
            $baseId = is_array($baseIds) ? $pickPriceId($baseIds, $interval) : null;

            if ($baseId) {
                $pricePayload[] = $baseId;
                $lineItems[] = ['label' => ($plans[$plan]['name'] ?? strtoupper($plan)).' plan', 'qty' => 1];
            }
        }

        // addons (allowed based on allowed_plans)
        foreach ($addons as $addonKey => $cfg) {
            $qty = (int) ($selectedAddons[$addonKey] ?? 0);
            if ($qty <= 0) continue;

            $allowedPlans = $cfg['allowed_plans'] ?? ['free','pro','team'];
            if (is_array($allowedPlans) && !in_array($plan, $allowedPlans, true)) {
                continue;
            }

            $addonIds = $cfg['price_ids'] ?? [];
            $addonId = is_array($addonIds) ? $pickPriceId($addonIds, $interval) : null;
            if (!$addonId) continue;

            // qty for addon price
            $pricePayload[$addonId] = $qty;

            $lineItems[] = [
                'label' => $cfg['name'] ?? $addonKey,
                'qty' => $qty,
            ];
        }

        // nothing to checkout
        if (empty($pricePayload)) {
            return redirect()->route('billing.index')
                ->with('billing_notice', 'Nothing to checkout. Choose Pro/Team or add at least one add-on.');
        }


        $user = $request->user();

        if (!$user->paddle_customer_id) {
               $customer = $user->createAsCustomer();
               $user->paddle_customer_id =$customer->paddle_id;
                $user->save();
        }

        // ✅ Create checkout session (server-side, not Livewire)
        $checkout = $user
            ->subscribe($pricePayload, 'default')
            ->customData([
                'selected_plan' => $plan,
                'selected_interval' => $interval,
                'selected_addons' => $selectedAddons,
            ])
            ->returnTo(route('billing.success'));

        return view('livewire.pages.billing.checkout', [
            'checkout' => $checkout,   // safe here (Blade, not Livewire)
            'lineItems' => $lineItems,
            'plan' => $plan,
            'interval' => $interval,
        ]);
    }
}