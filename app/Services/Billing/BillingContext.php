<?php

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BillingContext
{
    public const SUBSCRIPTION = 'default';

    public function billable(User $user, string $scope = 'personal'): Model
    {
        if ($scope === 'team' && $user->currentTeam) {
            return $user->currentTeam;
        }

        return $user;
    }

    public function planKey(Model $billable): string
    {
        $sub = $this->subscription($billable);

        if (! $sub || ! method_exists($sub, 'active') || ! $sub->active()) {
            return (string) ($billable->billing_plan ?: 'free');
        }

        $priceIds = $this->subscriptionPriceIds($sub);

        foreach (config('billing.plans') as $key => $plan) {
            $ids = $plan['price_ids'] ?? [];
            $flat = [];
            foreach ($ids as $id) {
                if (is_array($id)) {
                    $flat = array_merge($flat, $id);
                } else {
                    $flat[] = $id;
                }
            }

            if ($this->matchesAny($priceIds, array_filter($flat))) {
                return (string) $key;
            }
        }

        return 'free';
    }

    public function subscription(Model $billable)
    {
        if (! method_exists($billable, 'subscription')) {
            return null;
        }

        return $billable->subscription(self::SUBSCRIPTION);
    }

    protected function subscriptionPriceIds($subscription): array
    {
        return array_values(array_unique(array_map(
            fn ($it) => $it['price_id'],
            $this->subscriptionItems($subscription)
        )));
    }

    protected function subscriptionItems($subscription): array
    {
        $items = [];

        if (isset($subscription->items) && $subscription->items) {
            foreach ($subscription->items as $item) {
                $items[] = [
                    'price_id' => (string) ($item->price_id ?? $item->paddle_price ?? ''),
                    'quantity' => (int) ($item->quantity ?? 1),
                ];
            }
        } else {
            $p = (string) (data_get($subscription, 'price_id')
                ?? data_get($subscription, 'paddle_price')
                ?? data_get($subscription, 'paddle_price_id')
                ?? '');

            if ($p !== '') {
                $items[] = ['price_id' => $p, 'quantity' => 1];
            }
        }

        return $items;
    }

    protected function matchesAny(array $needlePriceIds, array $haystack): bool
    {
        foreach ($needlePriceIds as $id) {
            if (in_array($id, $haystack, true)) {
                return true;
            }
        }

        return false;
    }
}
