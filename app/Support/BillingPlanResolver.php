<?php

namespace App\Support;

class BillingPlanResolver
{
    public static function planFromPriceId(string $priceId): ?string
    {
        foreach (config('billing.plans') as $key => $plan) {
            $ids = $plan['price_ids'] ?? [];
            $flatIds = [];

            foreach ($ids as $id) {
                if (is_array($id)) {
                    $flatIds = array_merge($flatIds, $id);
                } else {
                    $flatIds[] = $id;
                }
            }

            foreach (array_filter($flatIds) as $id) {
                if ((string) $id === $priceId) {
                    return $key;
                }
            }
        }

        return null;
    }
}
