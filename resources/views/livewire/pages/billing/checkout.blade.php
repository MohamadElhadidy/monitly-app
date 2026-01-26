<?php

use Livewire\Volt\Component;

use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')] 
class extends Component {
    public $plan = 'pro';
    public $addons = [];
    public $plans;
    public $addonsConfig;
    public $checkout;

    public function mount()
    {
        // Get data from request (passed from controller)
        $request = request();
        $this->plan = $request->get('plan', 'pro');
        $addonsFromRequest = $request->get('addons', []);
        
        // Handle backward compatibility with single addon
        if (empty($addonsFromRequest) && $request->has('addon')) {
            $addonsFromRequest = [$request->get('addon')];
        }
        
        $this->addons = is_array($addonsFromRequest) ? array_filter($addonsFromRequest) : [];
        $this->plans = config('billing.plans', []);
        $this->addonsConfig = config('billing.addons', []);
        $this->checkout = session('checkout', ['url' => '#', 'id' => '']);
    }
}; ?>

    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-white py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto">
            <!-- Back Button -->
            <a href="{{ route('billing.index') }}"
               class="inline-flex items-center text-blue-600 hover:text-blue-700 mb-8">
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Plans
            </a>

            @if (session('error'))
                <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 shadow-sm p-6">
                    <div class="text-sm font-semibold text-rose-800">Error</div>
                    <div class="mt-1 text-sm text-rose-700">{{ session('error') }}</div>
                </div>
            @endif

            @if (($checkout['message'] ?? null))
                <div class="mb-6 rounded-xl border border-yellow-200 bg-yellow-50 shadow-sm p-6">
                    <div class="text-sm font-semibold text-yellow-800">Configuration Required</div>
                    <div class="mt-1 text-sm text-yellow-700">{{ $checkout['message'] }}</div>
                </div>
            @endif

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-12 text-white">
                    <h1 class="text-3xl font-bold mb-2">Complete Payment</h1>
                    <p class="text-blue-100">Secure checkout powered by Paddle</p>
                </div>

                <!-- Body -->
                <div class="p-8">
                    <!-- Plan Summary -->
                    <div class="mb-8 pb-8 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6">Order Summary</h2>

                        @php
                            $planData = $plans[$plan] ?? [];
                            $selectedAddons = [];
                            $totalAddonPrice = 0;
                            
                            foreach ($addons as $addonKey) {
                                if (!empty($addonKey) && isset($addonsConfig[$addonKey])) {
                                    $selectedAddons[] = $addonsConfig[$addonKey];
                                    $totalAddonPrice += $addonsConfig[$addonKey]['price'] ?? 0;
                                }
                            }
                        @endphp

                        <!-- Plan Item -->
                        @if ($plan !== 'free')
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $planData['name'] ?? 'Plan' }}</p>
                                    <p class="text-sm text-gray-600">{{ ucfirst($planData['billing_cycle'] ?? 'monthly') }} billing</p>
                                </div>
                                <p class="text-lg font-bold text-gray-900">${{ $planData['price'] ?? 0 }}/mo</p>
                            </div>
                        @else
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $planData['name'] ?? 'Free Plan' }}</p>
                                    <p class="text-sm text-gray-600">Base plan</p>
                                </div>
                                <p class="text-lg font-bold text-gray-900">Free</p>
                            </div>
                        @endif

                        <!-- Addon Items -->
                        @foreach ($selectedAddons as $addonData)
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $addonData['name'] ?? 'Add-on' }}</p>
                                    <p class="text-sm text-gray-600">
                                        @if (isset($addonData['description']))
                                            {{ $addonData['description'] }}
                                        @elseif (isset($addonData['pack_size']))
                                            +{{ $addonData['pack_size'] }} {{ str_contains($addonData['name'], 'Monitor') ? 'monitors' : 'team members' }}
                                        @endif
                                    </p>
                                </div>
                                <p class="text-lg font-bold text-gray-900">${{ $addonData['price'] ?? 0 }}/mo</p>
                            </div>
                        @endforeach

                        <!-- Total -->
                        <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                            <p class="text-lg font-bold text-gray-900">Total</p>
                            <p class="text-2xl font-bold text-blue-600">
                                ${{ ($planData['price'] ?? 0) + $totalAddonPrice }}<span class="text-sm text-gray-600">/mo</span>
                            </p>
                        </div>
                    </div>

                    <!-- Features -->
                    <div class="mb-8">
                        <h3 class="font-semibold text-gray-900 mb-4">What's Included</h3>

                        <ul class="space-y-3">
                            <li class="flex items-center text-gray-700">
                                <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <strong>{{ $planData['monitors'] }}</strong> {{ $planData['monitors'] === 1 ? 'Monitor' : 'Monitors' }}
                            </li>

                            <li class="flex items-center text-gray-700">
                                <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <strong>{{ $planData['check_interval'] }}</strong> check interval
                            </li>

                            <li class="flex items-center text-gray-700">
                                <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                @if ($planData['history_days'] > 1000)
                                    <strong>Full history</strong> retention
                                @else
                                    <strong>{{ $planData['history_days'] }}-day</strong> history retention
                                @endif
                            </li>

                            @if ($planData['features']['email_alerts'] ?? false)
                                <li class="flex items-center text-gray-700">
                                    <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Email alerts
                                </li>
                            @endif

                            @if ($planData['features']['slack_integration'] ?? false)
                                <li class="flex items-center text-gray-700">
                                    <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Slack integration
                                </li>
                            @endif

                            @if ($planData['features']['webhooks'] ?? false)
                                <li class="flex items-center text-gray-700">
                                    <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Webhooks
                                </li>
                            @endif

                            @if ($planData['features']['team_invitations'] ?? false)
                                <li class="flex items-center text-gray-700">
                                    <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Team management
                                </li>
                            @endif
                        </ul>
                    </div>

                    <!-- Paddle Checkout Button -->
                    <div class="mb-6">
                        <x-paddle-button
                            :checkout="$checkout"
                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold py-4 rounded-lg transition duration-200"
                        >
                            <div class="flex items-center justify-center">
                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                @if(($checkout['url'] ?? '#') === '#')
                                    Configure Paddle
                                @else
                                    Complete Payment Now
                                @endif
                            </div>
                        </x-paddle-button>
                    </div>

                    <!-- Trust Badges -->
                    <div class="flex items-center justify-center gap-4 text-sm text-gray-600">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Secure Payment
                        </div>
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Money-back Guarantee
                        </div>
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            Encrypted & Safe
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-8 py-6 border-t border-gray-200">
                    <p class="text-sm text-gray-600 text-center">
                        By clicking "Complete Payment", you agree to our <a href="#" class="text-blue-600 hover:text-blue-700">Terms of Service</a> and <a href="#" class="text-blue-600 hover:text-blue-700">Privacy Policy</a>. Billing is managed by Paddle.
                    </p>
                </div>
            </div>
        </div>
    </div>
