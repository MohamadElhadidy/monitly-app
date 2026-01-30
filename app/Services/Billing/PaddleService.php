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
        $this->apiKey = config('services.paddle.api_key') ?? env('PADDLE_API_KEY');
    }

    public function createCheckoutSession(
        Model $billable,
        string $plan,
        string $interval = 'monthly',
    ): ?array {
        try {
            $priceIds = config("billing.plans.{$plan}.price_ids", []);
            
            if (empty($priceIds) && $plan !== 'free') {
                Log::warning('No price IDs configured for plan', ['plan' => $plan]);
                return [
                    'url' => '#',
                    'id' => 'dev_checkout_' . uniqid(),
                    'message' => "Paddle price ID not configured for {$plan} plan.",
                ];
            }

            // Build items array
            $items = [];

            if ($plan !== 'free' && !empty($priceIds)) {
                $priceId = is_array($priceIds) ? ($priceIds[$interval] ?? null) : null;
                if ($priceId) {
                    $items[] = [
                        'price_id' => $priceId,
                        'quantity' => 1,
                    ];
                }
            }

            if (empty($items)) {
                return [
                    'url' => '#',
                    'id' => 'no_items',
                    'message' => 'No items to checkout.',
                ];
            }

            // Determine owner_type based on billable model
            $ownerType = $billable instanceof \App\Models\Team ? 'team' : 'user';
            
            // Use Laravel Cashier if available (only for single item)
            if (method_exists($billable, 'checkout') && count($items) === 1) {
                try {
                    $priceId = $items[0]['price_id'];
                    $quantity = $items[0]['quantity'] ?? 1;
                    
                    // ✅ FIXED: Use fluent API with customData() method
                    $checkout = $billable
                        ->checkout($priceId, $quantity)
                        ->returnTo(route('billing.success'))
                        ->customData([
                            'owner_type' => $ownerType,
                            'owner_id' => $billable->id,
                            'user_id' => $billable->id,
                            'plan' => $plan,
                            'interval' => $interval,
                        ]);

                    Log::info('Cashier checkout created', [
                        'owner_type' => $ownerType,
                        'owner_id' => $billable->id,
                        'plan' => $plan,
                    ]);

                    return [
                        'url' => $checkout->url() ?? '#',
                        'id' => $checkout->id ?? 'checkout_' . uniqid(),
                    ];
                } catch (\Exception $e) {
                    Log::error('Cashier checkout error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Fall through to direct API call
                }
            }

            // Fallback: For subscriptions, use Cashier subscribe() (see CheckoutController).
            // We intentionally do NOT create transactions here because it would bypass Cashier's subscription tables/webhooks.

            return [
                'url' => '#',
                'id' => 'cashier_required',
                'message' => 'This checkout must be created via Laravel Cashier (subscribe()).',
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

  /**
 * 🔥 FIX #1: Cancel a subscription in Paddle
 */
public function cancelSubscription(string $subscriptionId, bool $immediately = false): bool
{
    try {
        $apiKey = config('services.paddle.api_key') ?? env('PADDLE_API_KEY');
        
        if (!$apiKey) {
            Log::error('❌ Paddle API key not configured');
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'Paddle-Version' => '1',
        ])->post("https://api.paddle.com/subscriptions/{$subscriptionId}/cancel", [
            'effective_from' => $immediately ? 'immediately' : 'next_billing_period',
        ]);

        if ($response->successful()) {
            Log::info('✅ Paddle subscription canceled', [
                'subscription_id' => $subscriptionId,
                'immediately' => $immediately,
            ]);
            return true;
        }

        Log::error('❌ Failed to cancel Paddle subscription', [
            'subscription_id' => $subscriptionId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        
        return false;
        
    } catch (\Exception $e) {
        Log::error('❌ Exception canceling Paddle subscription', [
            'subscription_id' => $subscriptionId,
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}

    public function generatePortalUrl(Model $billable): ?string
    {
        // Simple redirect to Paddle's customer portal
        if (empty($billable->paddle_customer_id)) {
            return null;
        }
        
        // If Cashier method exists, use it
        if (method_exists($billable, 'billingPortalUrl')) {
            return $billable->billingPortalUrl(route('billing.index'));
        }
        
        // Otherwise, return generic Paddle portal URL
        $environment = config('services.paddle.sandbox', false) ? 'sandbox-' : '';
        return "https://{$environment}customer.paddle.com/subscriptions";
    }
}
