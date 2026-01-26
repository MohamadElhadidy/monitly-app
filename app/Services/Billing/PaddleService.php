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
                Log::warning('No price IDs configured for plan', ['plan' => $plan]);
                // Return a development checkout URL that shows a message
                return [
                    'url' => '#',
                    'id' => 'dev_checkout_' . uniqid(),
                    'message' => 'Paddle price IDs not configured. Please set PADDLE_PRICE_IDS_PRO and PADDLE_PRICE_IDS_TEAM in your .env file.',
                ];
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

            // Use Laravel Cashier Paddle if available
            if (method_exists($billable, 'checkout')) {
                try {
                    $checkout = $billable->checkout($items, [
                        'return_url' => route('billing.success'),
                        'custom_data' => [
                            'user_id' => $billable->id,
                            'plan' => $plan,
                            'addon' => $addon,
                        ],
                    ]);

                    return [
                        'url' => $checkout->url ?? '#',
                        'id' => $checkout->id ?? 'checkout_' . uniqid(),
                    ];
                } catch (\Exception $e) {
                    Log::error('Cashier checkout error', ['error' => $e->getMessage()]);
                }
            }

            // Fallback: Direct Paddle API call
            $apiKey = config('services.paddle.api_key') ?? env('PADDLE_API_KEY');
            if ($apiKey) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])->post('https://api.paddle.com/transactions', [
                    'items' => $items,
                    'customer_email' => $billable->email,
                    'return_url' => route('billing.success'),
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'url' => $data['checkout']['url'] ?? '#',
                        'id' => $data['id'] ?? 'checkout_' . uniqid(),
                    ];
                }
            }

            // Development fallback
            return [
                'url' => '#',
                'id' => 'dev_checkout_' . uniqid(),
                'message' => 'Paddle API not configured. Set PADDLE_API_KEY in your .env file for production.',
            ];
        } catch (\Exception $e) {
            Log::error('Paddle checkout error', ['error' => $e->getMessage()]);
            return [
                'url' => '#',
                'id' => 'error_checkout',
                'message' => 'Error creating checkout: ' . $e->getMessage(),
            ];
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