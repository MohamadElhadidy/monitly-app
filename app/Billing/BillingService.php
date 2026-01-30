<?php

namespace App\Billing;

use Illuminate\Database\Eloquent\Model;

class BillingService
{
    /**
     * @param  Model  $billable  User or Team using Cashier Paddle
     */
    public function current(Model $billable): array
    {
        // Safety check (production-grade)
        if (! method_exists($billable, 'subscription')) {
            return [
                'plan' => 'free',
                'addons' => [],
                'subscribed' => false,
            ];
        }

        $sub = $billable->subscription('default');

        if (! $sub || ! $sub->active()) {
            return [
                'plan' => 'free',
                'subscribed' => false,
            ];
        }

        $items = $sub->items()->pluck('price_id')->all();

        return [
            'plan' => match (true) {
                in_array(Plans::BUSINESS, $items, true) => 'business',
                in_array(Plans::TEAM, $items, true) => 'team',
                in_array(Plans::PRO, $items, true)  => 'pro',
                default => 'free',
            },
            'subscribed' => true,
        ];
    }
}
