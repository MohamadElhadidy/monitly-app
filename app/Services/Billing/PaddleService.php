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

            if (empty($items)) {
                return [
                    'url' => '#',
                    'id' => 'no_items',
                    'message' => 'No items to checkout.',
                ];
            }

            // CRITICAL FIX: Determine owner_type based on billable model
            $ownerType = $billable instanceof \App\Models\Team ? 'team' : 'user';
            
            // Use Laravel Cashier if available (only for single item)
            if (method_exists($billable, 'checkout') && count($items) === 1) {
                try {
                    $priceId = $items[0]['price_id'];
                    $quantity = $items[0]['quantity'] ?? 1;
                    
                    // CRITICAL FIX: Pass owner_type and owner_id in custom_data
                    $checkout = $billable->checkout($priceId, $quantity, [
                        'return_url' => route('billing.success'),
                        'custom_data' => [
                            'owner_type' => $ownerType,  // ← THIS WAS MISSING!
                            'owner_id' => $billable->id, // ← THIS WAS MISSING!
                            'user_id' => $billable->id,  // Keep for backward compatibility
                            'plan' => $plan,
                            'addons' => $addons,
                        ],
                    ]);

                    Log::info('Cashier checkout created', [
                        'owner_type' => $ownerType,
                        'owner_id' => $billable->id,
                        'plan' => $plan,
                    ]);

                    return [
                        'url' => $checkout->url ?? '#',
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

            // Fallback: Direct Paddle API call
            $apiKey = config('services.paddle.api_key') ?? env('PADDLE_API_KEY');
            
            if ($apiKey) {
                try {
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                    ])->post(config('services.paddle.base_url') . 'transactions', [
                        'items' => $items,
                        'customer_email' => $billable->email ?? null,
                        'return_url' => route('billing.success'),
                        'custom_data' => [
                            'owner_type' => $ownerType,  // ← CRITICAL!
                            'owner_id' => $billable->id, // ← CRITICAL!
                            'plan' => $plan,
                            'addons' => $addons,
                        ],
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        
                        Log::info('Direct API checkout created', [
                            'owner_type' => $ownerType,
                            'owner_id' => $billable->id,
                            'plan' => $plan,
                        ]);
                        
                        return [
                            'url' => $data['checkout']['url'] ?? '#',
                            'id' => $data['id'] ?? 'checkout_' . uniqid(),
                        ];
                    } else {
                        Log::error('Paddle API call failed', [
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Paddle API exception', ['error' => $e->getMessage()]);
                }
            }

            return [
                'url' => '#',
                'id' => 'dev_checkout_' . uniqid(),
                'message' => 'Paddle API not configured.',
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
            if (method_exists($billable, 'subscription')) {
                $subscription = $billable->subscription('default');
                
                if ($subscription && method_exists($subscription, 'active') && $subscription->active()) {
                    if (method_exists($subscription, 'cancel')) {
                        $subscription->cancel();
                        Log::info('Subscription cancelled via Cashier', ['billable_id' => $billable->id]);
                    }
                }
            }
            
            $billable->update([
                'billing_status' => 'canceled',
                'billing_plan' => 'free',
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Cancel subscription error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            try {
                $billable->update([
                    'billing_status' => 'canceled',
                    'billing_plan' => 'free',
                ]);
            } catch (\Exception $dbError) {
                Log::error('Failed to update local billing status', [
                    'error' => $dbError->getMessage()
                ]);
            }
            
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