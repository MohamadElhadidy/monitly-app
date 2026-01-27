<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')]
class extends Component {
    // Checkout logic
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <h2 class="text-2xl font-bold text-gray-900">Upgrade Your Plan</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Pro Plan -->
            <x-ui.card>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Pro Plan</h3>
                <div class="mb-4">
                    <span class="text-4xl font-bold text-gray-900">$9</span>
                    <span class="text-gray-600">/month</span>
                </div>
                
                <ul class="space-y-3 mb-6">
                    <li class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>5 monitors</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>10-minute checks</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Email alerts</span>
                    </li>
                </ul>

                <x-ui.button class="w-full paddle-checkout" data-price-id="pro_price_id" variant="primary">
                    Subscribe Now
                </x-ui.button>
            </x-ui.card>

            <!-- Team Plan -->
            <x-ui.card class="border-2 border-emerald-600">
                <x-ui.badge variant="success" class="mb-2">Most Popular</x-ui.badge>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Team Plan</h3>
                <div class="mb-4">
                    <span class="text-4xl font-bold text-gray-900">$29</span>
                    <span class="text-gray-600">/month</span>
                </div>
                
                <ul class="space-y-3 mb-6">
                    <li class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>20 monitors</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>5 team members</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Slack & Webhook alerts</span>
                    </li>
                </ul>

                <x-ui.button class="w-full paddle-checkout" data-price-id="team_price_id" variant="primary">
                    Subscribe Now
                </x-ui.button>
            </x-ui.card>
        </div>
    </div>

    @push('scripts')
    <script>
        document.querySelectorAll('.paddle-checkout').forEach(button => {
            button.addEventListener('click', function() {
                Paddle.Checkout.open({
                    items: [{ priceId: this.dataset.priceId, quantity: 1 }],
                    customer: { email: '{{ auth()->user()->email }}' },
                    successUrl: '{{ route('billing.success') }}',
                });
            });
        });
    </script>
    @endpush
</div>