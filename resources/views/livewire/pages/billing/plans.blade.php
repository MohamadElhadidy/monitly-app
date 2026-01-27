<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')]
class extends Component {
    public function selectPlan($plan)
    {
        $user = auth()->user();
        $currentPlan = $user->billing_plan ?? 'free';
        
        if ($plan === $currentPlan) {
            session()->flash('info', 'You are already on this plan.');
            return;
        }
        
        if ($plan === 'free') {
            // Redirect to cancel
            return redirect()->route('billing.index')->with('info', 'To downgrade to Free, please cancel your subscription.');
        }
        
        // Redirect to checkout
        return redirect()->route('billing.checkout', ['plan' => $plan]);
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Choose Your Plan</h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        
        <!-- Current Plan Badge -->
        @php
            $currentPlan = auth()->user()->billing_plan ?? 'free';
        @endphp
        
        <!-- Pricing Toggle (Monthly/Annual) -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Simple, Transparent Pricing</h1>
            <p class="text-xl text-gray-600 mb-8">Start monitoring in minutes. No credit card required for Free plan.</p>
        </div>

        <!-- Pricing Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            
            @foreach(config('billing.plans') as $planKey => $plan)
            @php
                $isCurrentPlan = $planKey === $currentPlan;
                $popular = $plan['popular'] ?? false;
                $bestValue = $plan['best_value'] ?? false;
            @endphp
            
            <div class="relative flex flex-col bg-white rounded-2xl shadow-lg border-2 {{ $isCurrentPlan ? 'border-emerald-500' : ($popular ? 'border-blue-500' : 'border-gray-200') }} overflow-hidden transition-all hover:shadow-2xl">
                
                <!-- Badge -->
                @if($popular || $bestValue || $isCurrentPlan)
                <div class="absolute top-0 right-0 -mr-1 -mt-1">
                    <div class="inline-flex items-center px-4 py-1 rounded-bl-lg rounded-tr-2xl text-sm font-semibold {{ $isCurrentPlan ? 'bg-emerald-500' : ($bestValue ? 'bg-purple-500' : 'bg-blue-500') }} text-white">
                        @if($isCurrentPlan)
                            Current Plan
                        @elseif($bestValue)
                            Best Value
                        @else
                            Popular
                        @endif
                    </div>
                </div>
                @endif
                
                <div class="p-8">
                    <!-- Plan Header -->
                    <div class="mb-6">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan['name'] }}</h3>
                        <p class="text-gray-600">{{ $plan['description'] }}</p>
                    </div>
                    
                    <!-- Price -->
                    <div class="mb-6">
                        @if($plan['price'] > 0)
                        <div class="flex items-baseline">
                            <span class="text-5xl font-extrabold text-gray-900">${{ $plan['price'] }}</span>
                            <span class="ml-2 text-xl text-gray-600">/month</span>
                        </div>
                        @else
                        <div class="flex items-baseline">
                            <span class="text-5xl font-extrabold text-gray-900">Free</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">Forever</p>
                        @endif
                    </div>
                    
                    <!-- Features List -->
                    <ul class="space-y-4 mb-8">
                        @foreach($plan['feature_list'] as $feature)
                        <li class="flex items-start">
                            <svg class="h-6 w-6 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="ml-3 text-gray-700">{{ $feature }}</span>
                        </li>
                        @endforeach
                    </ul>
                    
                    <!-- CTA Button -->
                    @if($isCurrentPlan)
                    <button disabled class="w-full py-3 px-6 rounded-lg border-2 border-emerald-500 bg-emerald-50 text-emerald-700 font-semibold cursor-not-allowed">
                        Current Plan
                    </button>
                    @else
                    <button 
                        wire:click="selectPlan('{{ $planKey }}')" 
                        class="w-full py-3 px-6 rounded-lg font-semibold transition-all {{ $popular || $bestValue ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-gray-900 hover:bg-gray-800 text-white' }}">
                        @if($planKey === 'free')
                            Downgrade to Free
                        @else
                            @php
                                $planHierarchy = ['free' => 0, 'pro' => 1, 'team' => 2];
                                $isUpgrade = ($planHierarchy[$planKey] ?? 0) > ($planHierarchy[$currentPlan] ?? 0);
                            @endphp
                            {{ $isUpgrade ? 'Upgrade' : 'Switch' }} to {{ $plan['name'] }}
                        @endif
                    </button>
                    @endif
                </div>
            </div>
            @endforeach
            
        </div>

        <!-- Add-ons Section -->
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-8 mb-12">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-3">Supercharge Your Monitoring</h2>
                <p class="text-lg text-gray-600">Add extra capabilities to any plan</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach(config('billing.addons') as $addonKey => $addon)
                <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200 hover:shadow-lg transition-all">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h4 class="text-lg font-bold text-gray-900 mb-1">{{ $addon['name'] }}</h4>
                            <p class="text-sm text-gray-600">{{ $addon['description'] }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-bold text-gray-900">${{ $addon['price'] }}</span>
                            <span class="text-sm text-gray-600 block">/month</span>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 mb-4">
                        Available for: 
                        @foreach($addon['allowed_plans'] as $allowedPlan)
                            <span class="inline-block bg-gray-100 rounded px-2 py-1 mr-1">{{ ucfirst($allowedPlan) }}</span>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Frequently Asked Questions</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Can I change plans anytime?</h4>
                    <p class="text-gray-600 text-sm">Yes! You can upgrade, downgrade, or cancel your subscription at any time. Changes take effect immediately with prorated billing.</p>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">What payment methods do you accept?</h4>
                    <p class="text-gray-600 text-sm">We accept all major credit cards, PayPal, and other payment methods through our secure payment processor, Paddle.</p>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Is there a free trial?</h4>
                    <p class="text-gray-600 text-sm">The Free plan is available forever with no credit card required. You can upgrade to a paid plan anytime to unlock more features.</p>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">What happens if I cancel?</h4>
                    <p class="text-gray-600 text-sm">You'll retain access to your paid features until the end of your billing period. After that, your account will automatically downgrade to the Free plan.</p>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Do you offer refunds?</h4>
                    <p class="text-gray-600 text-sm">Yes, we offer a 30-day money-back guarantee. If you're not satisfied, contact support for a full refund within 30 days of purchase.</p>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Can I add more monitors later?</h4>
                    <p class="text-gray-600 text-sm">Absolutely! You can purchase Extra Monitor Pack add-ons at any time to increase your monitoring capacity without upgrading your entire plan.</p>
                </div>
            </div>
        </div>

        <!-- CTA Banner -->
        <div class="mt-12 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-8 text-center text-white">
            <h2 class="text-3xl font-bold mb-4">Ready to Start Monitoring?</h2>
            <p class="text-xl mb-6 text-blue-100">Join thousands of developers who trust Monitly to keep their services online.</p>
            <a href="{{ route('billing.checkout.page', ['plan' => 'pro']) }}">
                <button class="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-all">
                    Start with Pro Plan
                </button>
            </a>
        </div>

    </div>
</div>