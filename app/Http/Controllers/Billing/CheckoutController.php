<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Simple checkout controller - delegates to Paddle.js frontend
 */
class CheckoutController extends Controller
{
    /**
     * Show checkout page with selected plan.
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'price_id' => 'required|string',
        ]);

        $priceId = $request->input('price_id');
        
        // Just show a page with Paddle.js that will open checkout
        return view('livewire.pages.billing.checkout', [
            'priceId' => $priceId,
        ]);
    }

    /**
     * Handle successful checkout.
     */
    public function success(Request $request)
    {
        return redirect()->route('billing.index')
            ->with('success', 'Subscription activated successfully! It may take a few moments to appear.');
    }

    /**
     * Handle cancelled checkout.
     */
    public function cancel(Request $request)
    {
        return redirect()->route('billing.index')
            ->with('message', 'Checkout was cancelled.');
    }
}