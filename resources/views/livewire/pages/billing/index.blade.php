<?php

use Livewire\Volt\Component;
use App\Services\Billing\BillingService;

use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')] 
class extends Component {
    public array $currentBilling = [];
    public array $plans = [];
    
    public function mount(BillingService $billingService)
    {
        $user = auth()->user();
        $this->currentBilling = $billingService->current($user);
        $this->plans = config('billing.plans', []);
    }
}; ?>

    <div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">

            <!-- Header -->
            <div class="mb-12">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Billing & Plans</h1>
                <p class="text-lg text-gray-600">Choose the perfect plan for your monitoring needs</p>

                <!-- Status Alert -->
                @if ($currentBilling['status'] === 'active')
                    <div class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4">
                        <p class="text-green-800 font-semibold">
                            ✓ Your {{ ucfirst($currentBilling['plan']) }} plan is active
                            @if ($currentBilling['next_bill_at'])
                                • Next billing: {{ $currentBilling['next_bill_at']->format('M d, Y') }}
                            @endif
                        </p>
                    </div>
                @elseif ($currentBilling['status'] === 'grace')
                    <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <p class="text-yellow-800 font-semibold">
                            ⚠ Your subscription is in grace period
                            @if ($currentBilling['grace_ends_at'])
                                • Ends: {{ $currentBilling['grace_ends_at']->format('M d, Y') }}
                            @endif
                        </p>
                    </div>
                @endif
            </div>

            <!-- Plans Grid -->
            <div class="grid md:grid-cols-3 gap-8 mb-16">
                @foreach (['free', 'pro', 'team'] as $planKey)
                    @php
                        $plan = $plans[$planKey] ?? [];
                        $isCurrentPlan = $currentBilling['plan'] === $planKey;
                    @endphp

                    <div class="relative group h-full">
                        <!-- Glow effect for popular plans -->
                        @if ($planKey === 'team')
                            <div class="absolute -inset-1 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl blur opacity-25 group-hover:opacity-50 transition duration-1000"></div>
                        @endif

                        <div class="relative bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 h-full flex flex-col overflow-hidden">
                            <!-- Badge -->
                            @if ($planKey === 'team')
                                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2 text-center">
                                    <span class="text-white text-xs font-bold tracking-widest">BEST VALUE</span>
                                </div>
                            @elseif ($planKey === 'pro')
                                <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-4 py-2 text-center">
                                    <span class="text-white text-xs font-bold tracking-widest">POPULAR</span>
                                </div>
                            @else
                                <div class="bg-gray-100 px-4 py-2 text-center">
                                    <span class="text-gray-700 text-xs font-bold tracking-widest">FREE</span>
                                </div>
                            @endif

                            <div class="p-8 flex flex-col flex-grow">
                                <!-- Plan Name -->
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan['name'] ?? 'Plan' }}</h3>
                                <p class="text-gray-600 text-sm mb-6">{{ $plan['description'] ?? '' }}</p>

                                <!-- Price -->
                                <div class="mb-6">
                                    @if (($plan['price'] ?? 0) == 0)
                                        <span class="text-4xl font-bold text-gray-900">Free</span>
                                    @else
                                        <div class="flex items-baseline">
                                            <span class="text-4xl font-bold text-gray-900">${{ $plan['price'] ?? 0 }}</span>
                                            <span class="text-gray-600 ml-2">/month</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Features List -->
                                <div class="space-y-3 mb-8 flex-grow">
                                    <!-- Monitors -->
                                    <div class="flex items-center">
                                        <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-gray-700">
                                            <strong>{{ $plan['monitors'] ?? 1 }}</strong>
                                            {{ ($plan['monitors'] ?? 1) === 1 ? 'Monitor' : 'Monitors' }}
                                        </span>
                                    </div>

                                    <!-- Users -->
                                    <div class="flex items-center">
                                        <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10.5 1.5H5.75A2.75 2.75 0 003 4.25v11.5A2.75 2.75 0 005.75 18.5h8.5A2.75 2.75 0 0017 15.75V4.25A2.75 2.75 0 0014.25 1.5h-3.75m0 3.5h2.5m-2.5 3h2.5m-7 0h2.5m-2.5 3h2.5m-2.5 3h6.5"/>
                                        </svg>
                                        <span class="text-gray-700">
                                            <strong>{{ $plan['users'] ?? 1 }}</strong>
                                            {{ ($plan['users'] ?? 1) === 1 ? 'User' : 'Users' }}
                                        </span>
                                    </div>

                                    <!-- Check Interval -->
                                    <div class="flex items-center">
                                        <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-gray-700">{{ $plan['check_interval'] ?? '15-minute' }} checks</span>
                                    </div>

                                    <!-- Email Alerts -->
                                    <div class="flex items-center">
                                        <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M2.5 3A1.5 1.5 0 001 4.5v.793c.026.009.051.02.076.032l10 5 10-5a.504.504 0 00.076-.032v-.793A1.5 1.5 0 0017.5 3h-15z"/>
                                        </svg>
                                        <span class="text-gray-700">Email Alerts</span>
                                    </div>

                                    <!-- Slack -->
                                    @if (($plan['features']['slack_integration'] ?? false))
                                        <div class="flex items-center">
                                            <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-gray-700">Slack Integration</span>
                                        </div>
                                    @else
                                        <div class="flex items-center">
                                            <svg class="h-5 w-5 text-gray-300 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-gray-400">Slack Integration</span>
                                        </div>
                                    @endif

                                    <!-- Webhooks -->
                                    @if (($plan['features']['webhooks'] ?? false))
                                        <div class="flex items-center">
                                            <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-gray-700">Webhooks</span>
                                        </div>
                                    @else
                                        <div class="flex items-center">
                                            <svg class="h-5 w-5 text-gray-300 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-gray-400">Webhooks</span>
                                        </div>
                                    @endif

                                    <!-- Team Invitations -->
                                    @if (($plan['features']['team_invitations'] ?? false))
                                        <div class="flex items-center">
                                            <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-gray-700">Team Invitations</span>
                                        </div>
                                    @else
                                        <div class="flex items-center">
                                            <svg class="h-5 w-5 text-gray-300 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-gray-400">Team Invitations</span>
                                        </div>
                                    @endif

                                    <!-- Add-ons -->
                                    @if (($plan['features']['add_ons'] ?? false))
                                        <div class="flex items-center">
                                            <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-gray-700">Add-ons Available</span>
                                        </div>
                                    @else
                                        <div class="flex items-center">
                                            <svg class="h-5 w-5 text-gray-300 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-gray-400">Add-ons</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- CTA Button -->
                                <div class="pt-6 border-t border-gray-100">
                                    @if ($isCurrentPlan)
                                        <button disabled class="w-full py-3 px-4 rounded-lg font-semibold text-gray-700 bg-gray-100 cursor-default">
                                            ✓ Current Plan
                                        </button>
                                    @else
                                        @if ($planKey === 'free')
                                            <a href="{{ route('billing.cancel') }}"
                                               class="block w-full py-3 px-4 rounded-lg font-semibold text-white bg-gray-600 hover:bg-gray-700 transition text-center">
                                                Downgrade to Free
                                            </a>
                                        @else
                                            <form method="POST" action="{{ route('billing.checkout') }}" class="w-full">
                                                @csrf
                                                <input type="hidden" name="plan" value="{{ $planKey }}">
                                                <input type="hidden" name="scope" value="user">
                                                <button type="submit" class="w-full py-3 px-4 rounded-lg font-semibold text-white {{ $planKey === 'team' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700' : 'bg-purple-600 hover:bg-purple-700' }} transition">
                                                    {{ $planKey === 'free' ? 'Use Free' : 'Start Trial' }}
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- FAQ Section -->
            <div class="max-w-3xl mx-auto">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Frequently Asked Questions</h2>

                <div class="space-y-4">
                    <details class="group bg-white rounded-lg shadow-md p-6 cursor-pointer">
                        <summary class="flex justify-between items-center font-semibold text-gray-900">
                            Can I cancel my subscription anytime?
                            <span class="transform group-open:rotate-180">➔</span>
                        </summary>
                        <p class="text-gray-600 mt-4">Yes! You can cancel your subscription at any time from your billing dashboard. No hidden fees or commitment required.</p>
                    </details>

                    <details class="group bg-white rounded-lg shadow-md p-6 cursor-pointer">
                        <summary class="flex justify-between items-center font-semibold text-gray-900">
                            What happens if I downgrade?
                            <span class="transform group-open:rotate-180">➔</span>
                        </summary>
                        <p class="text-gray-600 mt-4">You'll receive a prorated refund for the remainder of your billing cycle. Any excess usage beyond your new plan limits will be disabled.</p>
                    </details>

                    <details class="group bg-white rounded-lg shadow-md p-6 cursor-pointer">
                        <summary class="flex justify-between items-center font-semibold text-gray-900">
                            Do you offer annual billing?
                            <span class="transform group-open:rotate-180">➔</span>
                        </summary>
                        <p class="text-gray-600 mt-4">Yes! Contact our sales team for custom annual plans with discounts.</p>
                    </details>

                    <details class="group bg-white rounded-lg shadow-md p-6 cursor-pointer">
                        <summary class="flex justify-between items-center font-semibold text-gray-900">
                            Is there a free trial?
                            <span class="transform group-open:rotate-180">➔</span>
                        </summary>
                        <p class="text-gray-600 mt-4">Yes! Pro and Team plans come with a 14-day free trial. No credit card required to start.</p>
                    </details>
                </div>
            </div>
        </div>
    </div>
