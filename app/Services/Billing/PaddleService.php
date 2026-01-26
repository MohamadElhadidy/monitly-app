<?php

namespace App\Services\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaddleService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.paddle.client_token') ?? env('PADDLE_CUSTOMER_TOKEN');
    }

    public function createCheckoutSession(
        Model $billable,
        string $plan,
        ?string $addon = null,
    ): ?array {
        try {
            $priceIds = config("billing.plans.{$plan}.price_ids", []);

            if (empty($priceIds)) {
                return null;
            }

            $items = [
                [
                    'price_id' => $priceIds[0],
                    'quantity' => 1,
                ]
            ];

            if ($addon) {
                $addonPriceIds = config("billing.addons.{$addon}.price_ids", []);
                if (!empty($addonPriceIds)) {
                    $items[] = [
                        'price_id' => $addonPriceIds[0],
                        'quantity' => 1,
                    ];
                }
            }

            $payload = [
                'items' => $items,
                'customer' => [
                    'email' => $billable->email,
                ],
                'custom_data' => [
                    'user_id' => $billable->id,
                ],
                'return_url' => route('billing.success'),
            ];

            // In production, call Paddle API
            // For now, return mock response
            return [
                'url' => 'https://checkout.paddle.com/mock',
                'id' => 'checkout_' . uniqid(),
            ];
        } catch (\Exception $e) {
            Log::error('Paddle checkout error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function cancelSubscription(Model $billable): bool
    {
        try {
            $billable->update([
                'billing_status' => 'canceled',
                'billing_plan' => 'free',
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Cancel subscription error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function generatePortalUrl(Model $billable): ?string
    {
        return "https://customer.paddle.com/billing/customers/{$billable->paddle_customer_id}";
    }
}