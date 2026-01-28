<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new 
#[Layout('layouts.app')]
class extends Component {
    
    public function with(): array
    {
        $user = auth()->user();
        
        return [
            'subscription' => $user->subscription(),
            'subscriptions' => $user->subscriptions,
            'transactions' => $user->transactions()->latest()->take(10)->get(),
            'isSubscribed' => $user->subscribed(),
            'onGracePeriod' => $user->subscription()?->onGracePeriod() ?? false,
        ];
    }
}

?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 ">Billing</h1>
        </div>

        @if($isSubscribed)
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Current Subscription</h2>
                <p>Status: {{ $subscription->paddle_status }}</p>
                <a href="{{ route('billing.portal') }}" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded">
                    Manage Subscription
                </a>
            </div>
        @else
            <div class="bg-white  shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Choose a Plan</h2>
                <form action="{{ route('billing.checkout') }}" method="POST">
                    @csrf
                    <input type="hidden" name="price_id" value="pri_01j18c54myrfew21q81xb5b9ka">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Subscribe</button>
                </form>
            </div>
        @endif
    </div>
</div>