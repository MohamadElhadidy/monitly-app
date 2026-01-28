<?php

namespace App\Support;

class BillingPlanResolver
{
    public static function planFromPriceId(string $priceId): ?string
    {
        foreach (config('billing.plans') as $key => $plan) {
            foreach (($plan['price_ids'] ?? []) as $id) {
                if ($id === $priceId) {
                    return $key;
                }
            }
        }

        return null;
    }
}