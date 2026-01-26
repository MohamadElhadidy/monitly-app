<?php

use Livewire\Volt\Component;
use App\Services\Billing\BillingService;

new class extends Component {
    public array $currentBilling = [];
    public array $plans = [];
    
    public function mount(BillingService $billingService)
    {
        $user = auth()->user();
        $this->currentBilling = $billingService->current($user);
        $this->plans = config('billing.plans', []);
    }
}; ?>

<x-layouts.app>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-white py-12 px-4">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Billing & Plans</h1>
            <p class="text-lg text-gray-600 mb-12">Choose your perfect plan</p>

            <!-- Status Alert -->
            @if ($currentBilling['status'] === 'active')
                <div class="mb-8 bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-green-800 font-semibold">✓ Your {{ ucfirst($currentBilling['plan']) }} plan is active</p>
                </div>
            @endif

            <!-- Plans Grid -->
            <div class="grid md:grid-cols-3 gap-8">
                @foreach (['free', 'pro', 'team'] as $planKey)
                    @php
                        $plan = $plans[$planKey] ?? [];
                        $isCurrentPlan = $currentBilling['plan'] === $planKey;
                    @endphp

                    <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition p-8 flex flex-col">
                        <!-- Plan Name & Price -->
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan['name'] }}</h3>
                        <div class="mb-6">
                            @if ($plan['price'] == 0)
                                <span class="text-4xl font-bold">Free</span>
                            @else
                                <span class="text-4xl font-bold">${{ $plan['price'] }}</span><span class="text-gray-600">/month</span>
                            @endif
                        </div>

                        <!-- Features -->
                        <ul class="space-y-3 mb-8 flex-grow">
                            <li class="flex items-center text-gray-700">
                                <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                </svg>
                                <strong>{{ $plan['monitors'] }}</strong> Monitors
                            </li>
                            <li class="flex items-center text-gray-700">
                                <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                </svg>
                                {{ $plan['check_interval'] }} checks
                            </li>
                            <li class="flex items-center text-gray-700">
                                <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                </svg>
                                Email Alerts
                            </li>
                            @if ($plan['features']['slack_integration'] ?? false)
                                <li class="flex items-center text-gray-700">
                                    <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                    </svg>
                                    Slack Integration
                                </li>
                            @endif
                            @if ($plan['features']['webhooks'] ?? false)
                                <li class="flex items-center text-gray-700">
                                    <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                    </svg>
                                    Webhooks
                                </li>
                            @endif
                        </ul>

                        <!-- Button -->
                        <button class="w-full py-3 px-4 rounded-lg font-semibold text-white {{ $isCurrentPlan ? 'bg-gray-400' : 'bg-blue-600 hover:bg-blue-700' }} transition">
                            {{ $isCurrentPlan ? '✓ Current Plan' : 'Choose Plan' }}
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>