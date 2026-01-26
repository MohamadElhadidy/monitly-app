<?php

use Livewire\Volt\Component;
use App\Services\Billing\BillingService;

use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')] 
class extends Component {
    public array $currentBilling = [];
    public array $plans = [];
    public array $addons = [];
    
    public function mount(BillingService $billingService)
    {
        $user = auth()->user();
        $this->currentBilling = $billingService->current($user);
        $this->plans = config('billing.plans', []);
        $this->addons = config('billing.addons', []);
    }

    public function getAvailableAddons(string $planKey): array
    {
        return array_filter($this->addons, function($addon) use ($planKey) {
            return in_array($planKey, $addon['allowed_plans'] ?? []);
        }, ARRAY_FILTER_USE_BOTH);
    }
}; ?>

<div class="space-y-6">
    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="max-w-7xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Billing</h1>
            <p class="mt-2 text-sm text-gray-600">Manage your subscription and billing information</p>
        </div>

        @php
            $user = auth()->user();
            $subscription = method_exists($user, 'subscription') ? $user->subscription('default') : null;
            $hasActiveSubscription = $subscription && method_exists($subscription, 'active') && $subscription->active();
        @endphp

        <!-- Current Subscription Card -->
        @if ($hasActiveSubscription || $currentBilling['status'] === 'active')
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 mb-8">
                <div class="px-6 py-5 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Current Subscription</h2>
                </div>
                <div class="px-6 py-5">
                    <dl class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Plan</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ ucfirst($currentBilling['plan']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $currentBilling['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ ucfirst($currentBilling['status']) }}
                                </span>
                            </dd>
                        </div>
                        @if ($currentBilling['next_bill_at'])
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Next Billing Date</dt>
                                <dd class="mt-1 text-lg font-semibold text-gray-900">{{ $currentBilling['next_bill_at']->format('M d, Y') }}</dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-6 flex flex-wrap gap-3">
                        @if ($subscription && $user->paddle_customer_id)
                            <a href="https://customer.paddle.com/billing/customers/{{ $user->paddle_customer_id }}" 
                               target="_blank" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="mr-2 -ml-1 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                View Invoices
                            </a>
                        @endif
                        
                        @if ($currentBilling['status'] === 'active' && $currentBilling['plan'] !== 'free')
                            <button 
                                onclick="document.getElementById('cancel-modal').classList.remove('hidden')"
                                class="inline-flex items-center px-4 py-2 border border-red-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                Cancel Subscription
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @elseif ($currentBilling['status'] === 'grace')
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-8">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-yellow-800">Payment Required</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>Your subscription is in grace period. Please update your payment method to continue service.</p>
                            @if ($currentBilling['grace_ends_at'])
                                <p class="mt-1 font-medium">Grace period ends: {{ $currentBilling['grace_ends_at']->format('M d, Y') }}</p>
                            @endif
                        </div>
                        <div class="mt-4">
                            <div class="-mx-2 -my-1.5 flex">
                                @if ($user->paddle_customer_id)
                                    <a href="https://customer.paddle.com/billing/customers/{{ $user->paddle_customer_id }}" 
                                       target="_blank"
                                       class="bg-yellow-50 px-2 py-1.5 rounded-md text-sm font-medium text-yellow-800 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-yellow-50 focus:ring-yellow-600">
                                        Update Payment Method
                                    </a>
                                @endif
                                <form method="POST" action="{{ route('billing.cancel') }}" class="ml-2">
                                    @csrf
                                    <button type="submit" class="bg-yellow-50 px-2 py-1.5 rounded-md text-sm font-medium text-yellow-800 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-yellow-50 focus:ring-yellow-600">
                                        Cancel Subscription
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Plans Section -->
        <div class="mb-8">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-900">Choose Your Plan</h2>
                <p class="mt-1 text-sm text-gray-600">Select the plan that best fits your monitoring needs</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                @foreach (['free', 'pro', 'team'] as $planKey)
                    @php
                        $plan = $plans[$planKey] ?? [];
                        $isCurrentPlan = $currentBilling['plan'] === $planKey;
                    @endphp

                    <div class="relative {{ $isCurrentPlan ? 'ring-2 ring-blue-500' : '' }} bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                        @if ($isCurrentPlan)
                            <div class="absolute top-0 right-0 bg-blue-500 text-white px-3 py-1 text-xs font-semibold">
                                Current Plan
                            </div>
                        @endif

                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ $plan['name'] ?? 'Plan' }}</h3>
                            
                            <div class="mt-4 flex items-baseline">
                                @if (($plan['price'] ?? 0) == 0)
                                    <span class="text-4xl font-bold text-gray-900">Free</span>
                                @else
                                    <span class="text-4xl font-bold text-gray-900">${{ $plan['price'] ?? 0 }}</span>
                                    <span class="ml-2 text-lg text-gray-600">/month</span>
                                @endif
                            </div>

                            <ul class="mt-6 space-y-3">
                                <li class="flex items-start">
                                    <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-sm text-gray-700"><strong>{{ $plan['monitors'] ?? 1 }}</strong> {{ ($plan['monitors'] ?? 1) === 1 ? 'Monitor' : 'Monitors' }}</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-sm text-gray-700"><strong>{{ $plan['users'] ?? 1 }}</strong> {{ ($plan['users'] ?? 1) === 1 ? 'User' : 'Users' }}</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-sm text-gray-700">{{ $plan['check_interval'] ?? '15-minute' }} check interval</span>
                                </li>
                                <li class="flex items-start">
                                    @if (($plan['features']['email_alerts'] ?? false))
                                        <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="h-5 w-5 text-gray-300 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm {{ ($plan['features']['email_alerts'] ?? false) ? 'text-gray-700' : 'text-gray-400' }}">Email alerts</span>
                                </li>
                                <li class="flex items-start">
                                    @if (($plan['features']['slack_integration'] ?? false))
                                        <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="h-5 w-5 text-gray-300 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm {{ ($plan['features']['slack_integration'] ?? false) ? 'text-gray-700' : 'text-gray-400' }}">Slack integration</span>
                                </li>
                                <li class="flex items-start">
                                    @if (($plan['features']['webhooks'] ?? false))
                                        <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="h-5 w-5 text-gray-300 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm {{ ($plan['features']['webhooks'] ?? false) ? 'text-gray-700' : 'text-gray-400' }}">Webhooks</span>
                                </li>
                                <li class="flex items-start">
                                    @if (($plan['features']['add_ons'] ?? false))
                                        <svg class="h-5 w-5 text-green-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="h-5 w-5 text-gray-300 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm {{ ($plan['features']['add_ons'] ?? false) ? 'text-gray-700' : 'text-gray-400' }}">Add-ons available</span>
                                </li>
                            </ul>

                            <div class="mt-6">
                                @if ($isCurrentPlan)
                                    <button disabled class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-400 bg-gray-50 cursor-not-allowed">
                                        Current Plan
                                    </button>
                                @else
                                    @if ($planKey === 'free' && $currentBilling['plan'] !== 'free')
                                        <form method="POST" action="{{ route('billing.cancel') }}">
                                            @csrf
                                            <button type="submit" class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                                Downgrade to Free
                                            </button>
                                        </form>
                                    @else
                                        @php
                                            $availableAddons = $this->getAvailableAddons($planKey);
                                        @endphp
                                        
                                        @if (count($availableAddons) > 0)
                                            <div class="mb-4 p-4 bg-gray-50 rounded-md border border-gray-200">
                                                <label class="block text-xs font-medium text-gray-700 mb-3">Optional Add-ons:</label>
                                                <div class="space-y-2">
                                                    @foreach ($availableAddons as $addonKey => $addon)
                                                        <label class="flex items-start p-2 rounded border border-gray-200 hover:bg-white hover:border-blue-300 cursor-pointer transition-colors">
                                                            <input 
                                                                type="checkbox" 
                                                                name="addons[]" 
                                                                value="{{ $addonKey }}"
                                                                form="checkout-form-{{ $planKey }}"
                                                                class="mt-0.5 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                                            />
                                                            <div class="ml-3 flex-1">
                                                                <div class="text-sm font-medium text-gray-900">{{ $addon['name'] }}</div>
                                                                @if (isset($addon['description']))
                                                                    <div class="text-xs text-gray-500 mt-0.5">{{ $addon['description'] }}</div>
                                                                @elseif (isset($addon['pack_size']))
                                                                    <div class="text-xs text-gray-500 mt-0.5">+{{ $addon['pack_size'] }} {{ str_contains($addon['name'], 'Monitor') ? 'monitors' : 'team members' }}</div>
                                                                @endif
                                                                <div class="text-xs font-semibold text-blue-600 mt-1">+${{ $addon['price'] }}/mo</div>
                                                            </div>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <form method="POST" action="{{ route('billing.checkout') }}" id="checkout-form-{{ $planKey }}">
                                            @csrf
                                            <input type="hidden" name="plan" value="{{ $planKey }}">
                                            <button type="submit" class="w-full px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white {{ $planKey === 'team' ? 'bg-blue-600 hover:bg-blue-700' : ($planKey === 'pro' ? 'bg-purple-600 hover:bg-purple-700' : 'bg-gray-600 hover:bg-gray-700') }} focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                {{ $planKey === 'free' ? 'Use Free Plan' : 'Subscribe' }}
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Add-ons Section -->
        @if ($currentBilling['status'] === 'active' && count($this->getAvailableAddons($currentBilling['plan'])) > 0)
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-5 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Add-ons</h2>
                    <p class="mt-1 text-sm text-gray-600">Enhance your plan with additional features</p>
                </div>
                <div class="px-6 py-5">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($this->getAvailableAddons($currentBilling['plan']) as $addonKey => $addon)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $addon['name'] }}</h3>
                                    <span class="text-sm font-semibold text-gray-900">${{ $addon['price'] }}/mo</span>
                                </div>
                                @if (isset($addon['description']))
                                    <p class="text-xs text-gray-600 mb-3">{{ $addon['description'] }}</p>
                                @elseif (isset($addon['pack_size']))
                                    <p class="text-xs text-gray-600 mb-3">+{{ $addon['pack_size'] }} {{ str_contains($addon['name'], 'Monitor') ? 'monitors' : 'team members' }} per pack</p>
                                @endif
                                <form method="POST" action="{{ route('billing.checkout') }}">
                                    @csrf
                                    <input type="hidden" name="plan" value="{{ $currentBilling['plan'] }}">
                                    <input type="hidden" name="addons[]" value="{{ $addonKey }}">
                                    <button type="submit" class="w-full px-3 py-2 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100">
                                        Add to Subscription
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Cancel Modal -->
    @if ($currentBilling['status'] === 'active' && $currentBilling['plan'] !== 'free')
        <div id="cancel-modal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-5 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Cancel Subscription</h3>
                </div>
                <div class="px-6 py-5">
                    <p class="text-sm text-gray-600 mb-4">
                        Are you sure you want to cancel your {{ ucfirst($currentBilling['plan']) }} subscription? 
                        You'll lose access to premium features immediately.
                    </p>
                    @if ($currentBilling['next_bill_at'])
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-4">
                            <p class="text-xs text-yellow-800">
                                <strong>Note:</strong> Your subscription will remain active until {{ $currentBilling['next_bill_at']->format('M d, Y') }}. 
                                You'll continue to have access until then.
                            </p>
                        </div>
                    @endif
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3 rounded-b-lg">
                    <button 
                        onclick="document.getElementById('cancel-modal').classList.add('hidden')"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Keep Subscription
                    </button>
                    <form method="POST" action="{{ route('billing.cancel') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                            Cancel Subscription
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
