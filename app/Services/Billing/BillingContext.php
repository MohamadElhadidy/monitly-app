<?php

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

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

        if ($this->matchesAny($priceIds, (array) config('billing.paddle_price_ids_team', []))) {
            return 'team';
        }

        if ($this->matchesAny($priceIds, (array) config('billing.paddle_price_ids_pro', []))) {
            return 'pro';
        }

        return 'free';
    }

    public function addons(Model $billable): array
    {
        $sub = $this->subscription($billable);

        $fallback = [
            'monitor_packs' => (int) ($billable->addon_extra_monitor_packs ?? 0),
            'seat_packs' => (int) ($billable->addon_extra_seat_packs ?? 0),
            'fast_interval' => (int) ($billable->addon_interval_override_minutes ?? 0) > 0,
        ];

        if (! $sub || ! method_exists($sub, 'active') || ! $sub->active()) {
            return $fallback;
        }

        $items = $this->subscriptionItems($sub);

        $monitorPriceIds = (array) config('billing.paddle_addon_price_ids_monitor_pack', []);
        $seatPriceIds = (array) config('billing.paddle_addon_price_ids_team_member_pack', []);
        $fastPriceIds = (array) config('billing.paddle_addon_price_ids_interval_1m', []);

        $monitorPacks = 0;
        $seatPacks = 0;
        $fast = false;

        foreach ($items as $it) {
            $price = $it['price_id'];
            $qty = (int) $it['quantity'];

            if (in_array($price, $monitorPriceIds, true)) {
                $monitorPacks += $qty;
            }

            if (in_array($price, $seatPriceIds, true)) {
                $seatPacks += $qty;
            }

            if (in_array($price, $fastPriceIds, true) && $qty > 0) {
                $fast = true;
            }
        }

        return [
            'monitor_packs' => $monitorPacks,
            'seat_packs' => $seatPacks,
            'fast_interval' => $fast,
        ];
    }

    public function buildPricesPayload(string $planKey, array $addons): array
    {
        $planKey = in_array($planKey, ['free', 'pro', 'team'], true) ? $planKey : 'free';

        if ($planKey === 'free') {
            return [];
        }

        $basePrice = $this->defaultPriceIdForPlan($planKey);

        $prices = [$basePrice];

        $monitorPackQty = max(0, (int) Arr::get($addons, 'monitor_packs', 0));
        $seatPackQty = max(0, (int) Arr::get($addons, 'seat_packs', 0));
        $fast = (bool) Arr::get($addons, 'fast_interval', false);

        if ($monitorPackQty > 0) {
            $prices[$this->defaultAddonPriceId('monitor_pack')] = $monitorPackQty;
        }

        if ($seatPackQty > 0) {
            $prices[$this->defaultAddonPriceId('team_member_pack')] = $seatPackQty;
        }

        if ($fast) {
            $prices[$this->defaultAddonPriceId('interval_1m')] = 1;
        }

        return $prices;
    }

    public function subscription(Model $billable)
    {
        if (! method_exists($billable, 'subscription')) {
            return null;
        }

        return $billable->subscription(self::SUBSCRIPTION);
    }

    protected function defaultPriceIdForPlan(string $planKey): string
    {
        $ids = match ($planKey) {
            'team' => (array) config('billing.paddle_price_ids_team', []),
            'pro' => (array) config('billing.paddle_price_ids_pro', []),
            default => [],
        };

        return (string) ($ids[0] ?? '');
    }

    protected function defaultAddonPriceId(string $addonKey): string
    {
        $ids = match ($addonKey) {
            'monitor_pack' => (array) config('billing.paddle_addon_price_ids_monitor_pack', []),
            'team_member_pack' => (array) config('billing.paddle_addon_price_ids_team_member_pack', []),
            'interval_1m' => (array) config('billing.paddle_addon_price_ids_interval_1m', []),
            default => [],
        };

        return (string) ($ids[0] ?? '');
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