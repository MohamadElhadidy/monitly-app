<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new
#[Layout('layouts.app')]
class extends Component {
    public $selectedPlan = 'pro';
    public $selectedAddons = [];
    public $billingInterval = 'monthly';

    #[Computed]
    public function plans()
    {
        return [
            'free' => config('billing.plans.free'),
            'pro'  => config('billing.plans.pro'),
            'team' => config('billing.plans.team'),
        ];
    }

    #[Computed]
    public function addons()
    {
        return config('billing.addons', []);
    }

    #[Computed]
    public function currentUserPlan()
    {
        return auth()->user()?->billing_plan ?? 'free';
    }

    public function selectPlan($plan)
    {
        $this->selectedPlan = $plan;
    }

    public function toggleAddon($addonKey)
    {
        if (in_array($addonKey, $this->selectedAddons, true)) {
            $this->selectedAddons = array_values(array_diff($this->selectedAddons, [$addonKey]));
        } else {
            $this->selectedAddons[] = $addonKey;
        }
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <h2 class="text-2xl font-bold text-gray-900">Choose Your Plan</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Current Plan Notice --}}
        @if($this->currentUserPlan !== 'free')
            <div class="mb-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm text-blue-900">
                        You're currently on the <strong>{{ ucfirst($this->currentUserPlan) }}</strong> plan.
                        Upgrading will prorate your current subscription.
                    </p>
                </div>
            </div>
        @endif

        {{-- Pricing Plans --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">

            {{-- Free Plan --}}
            <x-ui.card class="relative {{ $this->currentUserPlan === 'free' ? 'ring-2 ring-gray-300' : '' }}">
                @if($this->currentUserPlan === 'free')
                    <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                        <x-ui.badge variant="default">Current Plan</x-ui.badge>
                    </div>
                @endif

                <div class="text-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Free</h3>
                    <div class="mb-4">
                        <span class="text-4xl font-bold text-gray-900">$0</span>
                        <span class="text-gray-600">/month</span>
                    </div>
                    <p class="text-sm text-gray-600">Perfect for trying out Monitly</p>
                </div>

                <ul class="space-y-3 mb-6">
                    <li class="flex items-center gap-2 text-sm"><span>1 monitor</span></li>
                    <li class="flex items-center gap-2 text-sm"><span>15-minute checks</span></li>
                    <li class="flex items-center gap-2 text-sm"><span>Email alerts</span></li>
                    <li class="flex items-center gap-2 text-sm"><span>7 days history</span></li>
                </ul>

                <x-ui.button class="w-full" variant="secondary" disabled>
                    Current Plan
                </x-ui.button>
            </x-ui.card>

            {{-- Pro Plan --}}
            <x-ui.card class="relative {{ $selectedPlan === 'pro' ? 'ring-2 ring-emerald-500' : '' }}">
                @if($this->currentUserPlan === 'pro')
                    <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                        <x-ui.badge variant="success">Current Plan</x-ui.badge>
                    </div>
                @else
                    <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                        <x-ui.badge variant="default">Popular</x-ui.badge>
                    </div>
                @endif

                <div class="text-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Pro</h3>
                    <div class="mb-4">
                        <span class="text-4xl font-bold text-gray-900">$9</span>
                        <span class="text-gray-600">/month</span>
                    </div>
                    <p class="text-sm text-gray-600">For serious monitoring</p>
                </div>

                <ul class="space-y-3 mb-6">
                    <li class="flex items-center gap-2 text-sm"><span><strong>5 monitors</strong></span></li>
                    <li class="flex items-center gap-2 text-sm"><span><strong>10-minute checks</strong></span></li>
                    <li class="flex items-center gap-2 text-sm"><span>Email alerts</span></li>
                    <li class="flex items-center gap-2 text-sm"><span><strong>Unlimited history</strong></span></li>
                    <li class="flex items-center gap-2 text-sm"><span>Add-ons available</span></li>
                </ul>

                @if($this->currentUserPlan === 'pro')
                    <x-ui.button class="w-full" variant="secondary" disabled>Current Plan</x-ui.button>
                @else
                    <x-ui.button
                        wire:click="selectPlan('pro')"
                        class="w-full paddle-checkout"
                        data-plan="pro"
                        variant="primary">
                        {{ $this->currentUserPlan === 'free' ? 'Upgrade to Pro' : 'Switch to Pro' }}
                    </x-ui.button>
                @endif
            </x-ui.card>

            {{-- Team Plan --}}
            <x-ui.card class="relative {{ $selectedPlan === 'team' ? 'ring-2 ring-emerald-500 shadow-xl' : '' }} border-2 border-emerald-600">
                @if($this->currentUserPlan === 'team')
                    <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                        <x-ui.badge variant="success">Current Plan</x-ui.badge>
                    </div>
                @else
                    <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                        <x-ui.badge variant="success">Best Value</x-ui.badge>
                    </div>
                @endif

                <div class="text-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Team</h3>
                    <div class="mb-4">
                        <span class="text-4xl font-bold text-gray-900">$29</span>
                        <span class="text-gray-600">/month</span>
                    </div>
                    <p class="text-sm text-gray-600">For teams & businesses</p>
                </div>

                <ul class="space-y-3 mb-6">
                    <li class="flex items-center gap-2 text-sm"><span><strong>20 monitors</strong></span></li>
                    <li class="flex items-center gap-2 text-sm"><span><strong>10-minute checks</strong></span></li>
                    <li class="flex items-center gap-2 text-sm"><span><strong>5 team members</strong></span></li>
                    <li class="flex items-center gap-2 text-sm"><span><strong>Slack & Webhooks</strong></span></li>
                    <li class="flex items-center gap-2 text-sm"><span>Unlimited history</span></li>
                    <li class="flex items-center gap-2 text-sm"><span>Priority support</span></li>
                </ul>

                @if($this->currentUserPlan === 'team')
                    <x-ui.button class="w-full" variant="secondary" disabled>Current Plan</x-ui.button>
                @else
                    <x-ui.button
                        wire:click="selectPlan('team')"
                        class="w-full paddle-checkout"
                        data-plan="team"
                        variant="primary">
                        Upgrade to Team
                    </x-ui.button>
                @endif
            </x-ui.card>
        </div>

        {{-- Add-ons --}}
        <div class="mt-12">
            <div class="text-center mb-8">
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Power Up With Add-ons</h3>
                <p class="text-gray-600">Extend your plan's capabilities with optional add-ons</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($this->addons as $key => $addon)
                    @php
                        $isAllowed = true;
                        if (isset($addon['allowed_plans'])) {
                            // Usually you want allowed based on selected plan, not current plan.
                            $isAllowed = in_array($selectedPlan, $addon['allowed_plans'], true);
                        }
                    @endphp

                    @if($isAllowed)
                        <x-ui.card
                            class="hover:shadow-lg transition cursor-pointer {{ in_array($key, $selectedAddons, true) ? 'ring-2 ring-emerald-500' : '' }}"
                            wire:click="toggleAddon('{{ $key }}')">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-1">{{ $addon['name'] }}</h4>
                                    <p class="text-sm text-gray-600 mb-3">{{ $addon['description'] }}</p>
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-2xl font-bold text-gray-900">${{ $addon['price'] }}</span>
                                        <span class="text-sm text-gray-600">/month</span>
                                    </div>
                                </div>

                                <input type="checkbox"
                                       class="h-5 w-5 text-emerald-600 rounded focus:ring-emerald-500"
                                       wire:model="selectedAddons"
                                       value="{{ $key }}"
                                       id="addon-{{ $key }}">
                            </div>

                            @if(isset($addon['pack_size']))
                                <div class="pt-3 border-t border-gray-200">
                                    <p class="text-xs text-gray-500">Adds {{ $addon['pack_size'] }} units to your plan</p>
                                </div>
                            @endif
                        </x-ui.card>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- FAQ --}}
        <div class="mt-16 max-w-3xl mx-auto">
            <h3 class="text-2xl font-bold text-gray-900 mb-6 text-center">Frequently Asked Questions</h3>
            <div class="space-y-4">
                <x-ui.card>
                    <h4 class="font-semibold text-gray-900 mb-2">Can I change plans later?</h4>
                    <p class="text-sm text-gray-600">Yes! You can upgrade or downgrade anytime. Changes are prorated automatically.</p>
                </x-ui.card>
                <x-ui.card>
                    <h4 class="font-semibold text-gray-900 mb-2">What payment methods do you accept?</h4>
                    <p class="text-sm text-gray-600">We accept all major credit cards, PayPal, and various local payment methods through Paddle.</p>
                </x-ui.card>
                <x-ui.card>
                    <h4 class="font-semibold text-gray-900 mb-2">Is there a refund policy?</h4>
                    <p class="text-sm text-gray-600">Yes! We offer a 30-day money-back guarantee on all paid plans.</p>
                </x-ui.card>
            </div>
        </div>

    </div>

    @push('scripts')
        <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
        <script>
            (function () {
                let paddleReady = false;

                function initPaddleOnce() {
                    if (paddleReady) return;
                    if (typeof Paddle === 'undefined') {
                        console.error('Paddle.js not loaded');
                        return;
                    }

                    const token = @json(config('billing.paddle_customer_token')); // MUST be a client-side token
                    if (!token) {
                        console.error('Paddle client-side token is missing (billing.paddle_customer_token).');
                        return;
                    }

                    @if(config('billing.sandbox'))
                        Paddle.Environment.set('sandbox');
                    @endif

                    Paddle.Initialize({
                        token: token,
                        eventCallback: (e) => console.log('Paddle event:', e),
                    });

                    paddleReady = true;
                    console.log('✅ Paddle initialized');
                }

                function getPriceIdForPlan(plan) {
                    const map = {
                        pro: @json(data_get(config('billing.plans.pro'), 'price_ids.0')),
                        team: @json(data_get(config('billing.plans.team'), 'price_ids.0')),
                    };
                    return map[plan] || null;
                }

                function openCheckout(plan) {
                    initPaddleOnce();
                    if (!paddleReady) return;

                    const priceId = getPriceIdForPlan(plan);
                    if (!priceId) {
                        alert('Price ID not configured for this plan.\n\nCheck billing config + .env for PADDLE_PRICE_IDS_*');
                        return;
                    }

                    Paddle.Checkout.open({
                        items: [{ priceId, quantity: 1 }],
                        customer: { email: @json(auth()->user()->email) },
                        customData: {
                            owner_type: 'user',
                            owner_id: @json(auth()->id()),
                            plan: plan,
                            addons: @json($selectedAddons),
                        },
                        settings: {
                            successUrl: @json(route('billing.success')),
                            theme: 'light',
                        },
                    });
                }

                function bindPaddleButtons() {
                    document.querySelectorAll('.paddle-checkout').forEach((btn) => {
                        if (btn.dataset.paddleBound === '1') return;
                        btn.dataset.paddleBound = '1';

                        btn.addEventListener('click', (e) => {
                            e.preventDefault();
                            const plan = btn.dataset.plan;
                            openCheckout(plan);
                        });
                    });
                }

                document.addEventListener('DOMContentLoaded', () => {
                    bindPaddleButtons();
                    initPaddleOnce();
                });

                // Livewire can replace DOM; rebind after updates
                document.addEventListener('livewire:navigated', bindPaddleButtons);
                document.addEventListener('livewire:load', () => {
                    if (window.Livewire?.hook) {
                        window.Livewire.hook('message.processed', () => bindPaddleButtons());
                    }
                });
            })();
        </script>
    @endpush
</div>