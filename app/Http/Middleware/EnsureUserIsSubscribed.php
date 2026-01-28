<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure user has an active subscription
 * Following Laravel Cashier Paddle docs
 */
class EnsureUserIsSubscribed
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $product  Optional Paddle product ID to check
     * @param  string|null  $price  Optional Paddle price ID to check
     */
    public function handle(Request $request, Closure $next, ?string $product = null, ?string $price = null): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            return redirect()->route('login');
        }

        // Check specific product subscription
        if ($product && !$user->subscribedToProduct($product)) {
            return redirect()->route('billing.index')
                ->with('error', 'This feature requires an active subscription to the specified plan.');
        }

        // Check specific price subscription
        if ($price && !$user->subscribedToPrice($price)) {
            return redirect()->route('billing.index')
                ->with('error', 'This feature requires an active subscription to the specified plan.');
        }

        // Check generic subscription
        if (!$product && !$price && !$user->subscribed()) {
            return redirect()->route('billing.index')
                ->with('error', 'This feature requires an active subscription.');
        }

        // Check if subscription is past due
        if ($user->subscription() && $user->subscription()->pastDue()) {
            return redirect()->route('billing.index')
                ->with('error', 'Your subscription payment is past due. Please update your payment method.');
        }

        return $next($request);
    }
}