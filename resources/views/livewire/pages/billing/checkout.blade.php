<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new
#[Layout('layouts.app')]
class extends Component {
    public $selectedPlan = 'pro';
    public $selectedAddons = [];

    public function mount()
    {
        $this->selectedPlan = request()->get('plan', 'pro');
        $addonParam = request()->get('addon');
        
        if ($addonParam) {
            $this->selectedAddons = is_array($addonParam) ? $addonParam : [$addonParam];
        } else {
            $addonsParam = request()->get('addons', []);
            $this->selectedAddons = is_array($addonsParam) ? $addonsParam : [];
        }
    }

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
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <h2 class="text-2xl font-bold text-gray-900">Checkout</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">

        <!-- Plan Summary -->
        @php 
            $plan = $this->plans()[$selectedPlan] ?? null;
            $totalPrice = $plan ? $plan['price'] : 0;
        @endphp

        @if($plan)
        <x-ui.card class="mb-8">
            <h3 class="text-xl font-bold text-gray-900 mb-6">Order Summary</h3>
            
            <!-- Plan -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-200 mb-4">
                <div>
                    <h4 class="font-semibold text-gray-900">{{ $plan['name'] }} Plan</h4>
                    <p class="text-sm text-gray-600">{{ $plan['description'] }}</p>
                </div>
                <div class="text-right">
                    <span class="text-2xl font-bold text-gray-900">${{ $plan['price'] }}</span>
                    <span class="text-sm text-gray-600">/month</span>
                </div>
            </div>

            <!-- Selected Add-ons -->
            @if(!empty($selectedAddons))
                @foreach($selectedAddons as $addonKey)
                    @php
                        $addon = $this->addons()[$addonKey] ?? null;
                        if ($addon) {
                            $totalPrice += $addon['price'];
                        }
                    @endphp
                    @if($addon)
                    <div class="flex items-center justify-between py-3 border-b border-gray-100">
                        <div>
                            <h4 class="font-medium text-gray-900">{{ $addon['name'] }}</h4>
                            <p class="text-sm text-gray-600">{{ $addon['description'] }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-lg font-semibold text-gray-900">${{ $addon['price'] }}</span>
                            <span class="text-sm text-gray-600">/month</span>
                        </div>
                    </div>
                    @endif
                @endforeach
            @endif

            <!-- Total -->
            <div class="flex items-center justify-between pt-4 mt-4 border-t-2 border-gray-200">
                <span class="text-lg font-bold text-gray-900">Total</span>
                <div class="text-right">
                    <span class="text-3xl font-bold text-gray-900">${{ $totalPrice }}</span>
                    <span class="text-sm text-gray-600">/month</span>
                </div>
            </div>
        </x-ui.card>

        <!-- Checkout Button -->
        <div class="text-center mb-8">
            <button 
                class="paddle-checkout inline-flex items-center px-8 py-4 border border-transparent text-lg font-medium rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition"
                data-plan="{{ $selectedPlan }}">
                <svg class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Proceed to Secure Checkout
            </button>
            <p class="mt-3 text-sm text-gray-500">
                Secured by Paddle • Cancel anytime
            </p>
        </div>

        <!-- Security & Trust -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
            <div class="flex flex-col items-center">
                <svg class="h-8 w-8 text-emerald-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span class="text-sm font-medium text-gray-900">Secure Payment</span>
            </div>
            <div class="flex flex-col items-center">
                <svg class="h-8 w-8 text-emerald-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium text-gray-900">30-Day Guarantee</span>
            </div>
            <div class="flex flex-col items-center">
                <svg class="h-8 w-8 text-emerald-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span class="text-sm font-medium text-gray-900">Instant Activation</span>
            </div>
        </div>
        @endif

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

                    const token = @json(config('billing.paddle_customer_token'));
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

                function getAddonPriceIds(addonKeys) {
                    const addonMap = @json(array_map(function($addon) { 
                        return $addon['price_ids'][0] ?? null; 
                    }, config('billing.addons')));
                    
                    const priceIds = [];
                    addonKeys.forEach(key => {
                        if (addonMap[key]) {
                            priceIds.push(addonMap[key]);
                        }
                    });
                    return priceIds;
                }

                function openCheckout(plan) {
                    initPaddleOnce();
                    if (!paddleReady) return;

                    const priceId = getPriceIdForPlan(plan);
                    if (!priceId) {
                        alert('Price ID not configured for this plan.\n\nCheck billing config + .env for PADDLE_PRICE_IDS_*');
                        return;
                    }

                    // Build items array (plan + addons)
                    const items = [{ priceId, quantity: 1 }];
                    
                    const selectedAddons = @json($selectedAddons);
                    const addonPriceIds = getAddonPriceIds(selectedAddons);
                    addonPriceIds.forEach(addonPriceId => {
                        items.push({ priceId: addonPriceId, quantity: 1 });
                    });

                    @php
                        $user = auth()->user();
                        $currentTeam = $user->currentTeam;
                    @endphp

                    // Determine owner based on plan
                    const ownerType = plan === 'team' ? 'team' : 'user';
                    const ownerId = plan === 'team' ? @json($currentTeam?->id ?? $user->id) : @json($user->id);

                    console.log('Opening Paddle checkout:', {
                        plan,
                        ownerType,
                        ownerId,
                        items
                    });

                    Paddle.Checkout.open({
                        items: items,
                        customer: { email: @json($user->email) },
                        customData: {
                            owner_type: ownerType,
                            owner_id: ownerId,
                            plan: plan,
                            addons: selectedAddons,
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