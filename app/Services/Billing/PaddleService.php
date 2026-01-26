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
        array $addons = [],
    ): ?array {
        try {
            $priceIds = config("billing.plans.{$plan}.price_ids", []);
            
            // Debug: Log what we're getting
            $envPriceId = env("PADDLE_PRICE_IDS_" . strtoupper($plan));
            if (empty($priceIds) && !empty($envPriceId)) {
                Log::info('Price ID found in env but not in config. Clear config cache with: php artisan config:clear', [
                    'plan' => $plan,
                    'env_value' => $envPriceId,
                    'config_value' => $priceIds
                ]);
            }

            if (empty($priceIds) && $plan !== 'free') {
                Log::warning('No price IDs configured for plan', [
                    'plan' => $plan,
                    'env_check' => env("PADDLE_PRICE_IDS_" . strtoupper($plan)),
                    'config_value' => $priceIds
                ]);
                // Return a development checkout URL that shows a message
                $envValue = env("PADDLE_PRICE_IDS_" . strtoupper($plan));
                $message = empty($envValue) 
                    ? "Paddle price ID not found in .env for {$plan} plan. Please set PADDLE_PRICE_IDS_" . strtoupper($plan) . " in your .env file."
                    : "Paddle price ID found in .env but config is cached. Run: php artisan config:clear";
                
                return [
                    'url' => '#',
                    'id' => 'dev_checkout_' . uniqid(),
                    'message' => $message,
                ];
            }

            // Build items array for Paddle
            $items = [];
            
            // Add plan price if not free
            if ($plan !== 'free' && !empty($priceIds)) {
                $items[] = [
                    'price_id' => $priceIds[0],
                    'quantity' => 1,
                ];
            }

            // Add addons
            foreach ($addons as $addonKey) {
                if (empty($addonKey)) continue;
                
                $addonPriceIds = config("billing.addons.{$addonKey}.price_ids", []);
                if (!empty($addonPriceIds)) {
                    $items[] = [
                        'price_id' => $addonPriceIds[0],
                        'quantity' => 1,
                    ];
                }
            }

            // If no items (free plan with no addons), return null
            if (empty($items)) {
                return [
                    'url' => '#',
                    'id' => 'no_items',
                    'message' => 'No items to checkout. Please select a plan or addon.',
                ];
            }

            // Use Laravel Cashier Paddle if available (only for single item)
            // For multiple items, use direct Paddle API which handles items array properly
            if (method_exists($billable, 'checkout') && count($items) === 1) {
                try {
                    // Laravel Cashier Paddle checkout signature: checkout(string $priceId, int $quantity, array $options = [])
                    $priceId = $items[0]['price_id'];
                    $quantity = $items[0]['quantity'] ?? 1;
                    
                    $checkout = $billable->checkout($priceId, $quantity, [
                        'return_url' => route('billing.success'),
                        'custom_data' => [
                            'user_id' => $billable->id,
                            'plan' => $plan,
                            'addons' => $addons,
                        ],
                    ]);

                    return [
                        'url' => $checkout->url ?? '#',
                        'id' => $checkout->id ?? 'checkout_' . uniqid(),
                    ];
                } catch (\Exception $e) {
                    Log::error('Cashier checkout error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    // Fall through to direct API call
                }
            }

            // Fallback: Direct Paddle API call
            $apiKey = config('services.paddle.api_key') ?? env('PADDLE_API_KEY');
            if ($apiKey) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])->post('https://api.paddle.com/transactions', [
                    'items' => $items,
                    'customer_email' => $billable->email ?? null,
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
            // If using Laravel Cashier Paddle, cancel the subscription properly
            if (method_exists($billable, 'subscription')) {
                $subscription = $billable->subscription('default');
                
                if ($subscription && method_exists($subscription, 'active') && $subscription->active()) {
                    // Cancel at period end (allows access until end of billing period)
                    if (method_exists($subscription, 'cancel')) {
                        $subscription->cancel();
                        Log::info('Subscription cancelled via Cashier', ['billable_id' => $billable->id]);
                    } elseif (method_exists($subscription, 'cancelNow')) {
                        // Immediate cancellation
                        $subscription->cancelNow();
                        Log::info('Subscription cancelled immediately via Cashier', ['billable_id' => $billable->id]);
                    }
                }
            }
            
            // Update local database
            $billable->update([
                'billing_status' => 'canceled',
                'billing_plan' => 'free',
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Cancel subscription error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            
            // Still update local database even if Paddle cancellation fails
            try {
                $billable->update([
                    'billing_status' => 'canceled',
                    'billing_plan' => 'free',
                ]);
            } catch (\Exception $dbError) {
                Log::error('Failed to update local billing status', ['error' => $dbError->getMessage()]);
            }
            
            return false;
        }
    }

    public function generatePortalUrl(Model $billable): ?string
    {
        return "https://customer.paddle.com/billing/customers/{$billable->paddle_customer_id}";
    }
}