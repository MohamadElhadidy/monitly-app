<?php
use Livewire\Volt\Component;
use App\Services\Billing\PlanLimits;
use App\Services\Billing\BillingService;
use App\Models\Monitor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new
#[Layout('layouts.app')]
class extends Component {
    public $showAddonsModal = false;
    public $selectedAddons = [];

    #[Computed]
    public function currentPlan()
    {
        $user = auth()->user();
        $planName = $user->billing_plan ?? 'free';
        $planConfig = config("billing.plans.{$planName}", config('billing.plans.free'));
        
        return [
            'name' => $planConfig['name'] ?? ucfirst($planName),
            'slug' => $planName,
            'price' => $planConfig['price'] ?? 0,
            'isSubscribed' => $user->isSubscribed(),
            'status' => $user->billing_status ?? 'free',
            'nextBillAt' => $user->next_bill_at,
            'graceEndsAt' => $user->grace_ends_at,
            'isInGrace' => $user->isInGrace(),
            'hasPaddleCustomer' => !empty($user->paddle_customer_id),
        ];
    }

    #[Computed]
    public function usage()
    {
        $user = auth()->user();
        $activeMonitors = Monitor::where('user_id', $user->id)->count();
        $monitorLimit = PlanLimits::monitorLimitForUser($user);
        $intervalMinutes = PlanLimits::effectiveIntervalMinutesForUser($user);
        $teamUsers = $user->currentTeam ? $user->currentTeam->users()->count() : 1;
        
        return [
            'monitors' => $activeMonitors,
            'monitorLimit' => $monitorLimit,
            'monitorPercent' => $monitorLimit > 0 ? min(100, round(($activeMonitors / $monitorLimit) * 100)) : 0,
            'monitorAvailable' => max(0, $monitorLimit - $activeMonitors),
            'checkInterval' => $intervalMinutes,
            'teamUsers' => $teamUsers,
        ];
    }

    #[Computed]
    public function addons()
    {
        $user = auth()->user();
        $addons = [];
        
        if ($user->addon_extra_monitor_packs > 0) {
            $addons[] = [
                'name' => 'Extra Monitor Pack',
                'quantity' => $user->addon_extra_monitor_packs,
                'description' => ($user->addon_extra_monitor_packs * 5) . ' additional monitors',
                'price' => 5 * $user->addon_extra_monitor_packs,
            ];
        }
        
        if ($user->addon_interval_override_minutes) {
            $minutes = $user->addon_interval_override_minutes;
            $addons[] = [
                'name' => 'Faster Check Interval',
                'quantity' => 1,
                'description' => "{$minutes}-minute checks",
                'price' => 7,
            ];
        }
        
        return $addons;
    }

    #[Computed]
    public function availableAddons()
    {
        $user = auth()->user();
        $currentPlan = $user->billing_plan ?? 'free';
        $allAddons = config('billing.addons');
        $available = [];
        
        foreach ($allAddons as $key => $addon) {
            if (in_array($currentPlan, $addon['allowed_plans'])) {
                $available[$key] = $addon;
            }
        }
        
        return $available;
    }

    public function openAddonsModal()
    {
        $this->showAddonsModal = true;
        $this->selectedAddons = [];
    }

    public function closeAddonsModal()
    {
        $this->showAddonsModal = false;
        $this->selectedAddons = [];
    }

    public function toggleAddon($addonKey)
    {
        if (in_array($addonKey, $this->selectedAddons)) {
            $this->selectedAddons = array_diff($this->selectedAddons, [$addonKey]);
        } else {
            $this->selectedAddons[] = $addonKey;
        }
    }

    public function purchaseAddons()
    {
        if (empty($this->selectedAddons)) {
            session()->flash('error', 'Please select at least one add-on.');
            return;
        }
        
        $user = auth()->user();
        $plan = $user->billing_plan ?? 'pro';
        
        return redirect()->route('billing.checkout', [
            'plan' => $plan,
            'addons' => $this->selectedAddons,
        ]);
    }

    /**
     * Redirect to Paddle Customer Portal for seamless subscription management
     */
    public function manageSubscription()
    {
        return redirect()->route('billing.manage');
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <li class="flex items-center">
            <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
            <span class="ml-2 text-sm font-medium text-gray-700">Billing</span>
        </li>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Billing & Subscription</h1>
            <p class="mt-2 text-sm text-gray-600">Manage your subscription, payment methods, and billing preferences</p>
        </div>

        <!-- Flash Messages -->
        @if (session('success'))
        <x-ui.alert type="success" class="mb-6">
            {{ session('success') }}
        </x-ui.alert>
        @endif

        @if (session('error'))
        <x-ui.alert type="error" class="mb-6">
            {{ session('error') }}
        </x-ui.alert>
        @endif

        <!-- Grace Period Warning -->
        @if($this->currentPlan['isInGrace'])
        <x-ui.alert type="warning" class="mb-6">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-semibold mb-1">Payment Issue Detected</h3>
                    <p class="text-sm">Your subscription payment failed. Please update your payment method by {{ $this->currentPlan['graceEndsAt']->format('M d, Y') }} to avoid service interruption.</p>
                </div>
                <a href="{{ route('billing.manage') }}" class="ml-4 flex-shrink-0">
                    <x-ui.button variant="warning" size="sm">
                        Update Payment
                    </x-ui.button>
                </a>
            </div>
        </x-ui.alert>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column - Main Content -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Current Plan Card -->
                <x-ui.card>
                    <div class="flex items-start justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Current Plan</h2>
                            <p class="text-sm text-gray-600 mt-1">{{ $this->currentPlan['name'] }} Plan</p>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-gray-900">
                                ${{ number_format($this->currentPlan['price'], 0) }}
                            </div>
                            <div class="text-sm text-gray-600">/month</div>
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div class="mb-6">
                        @if($this->currentPlan['isSubscribed'])
                            @if($this->currentPlan['status'] === 'active')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800">
                                <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Active Subscription
                            </span>
                            @elseif($this->currentPlan['status'] === 'grace')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                Grace Period
                            </span>
                            @elseif($this->currentPlan['status'] === 'canceled')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                Cancelled
                            </span>
                            @endif
                        @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                            Free Plan
                        </span>
                        @endif
                    </div>

                    <!-- Next Billing Date -->
                    @if($this->currentPlan['nextBillAt'] && $this->currentPlan['isSubscribed'])
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">Next billing date:</span>
                            <span class="font-medium text-gray-900">{{ $this->currentPlan['nextBillAt']->format('M d, Y') }}</span>
                        </div>
                    </div>
                    @endif

                    <!-- Action Buttons -->
                    <div class="flex flex-wrap gap-3">
                        @if(!$this->currentPlan['isSubscribed'] || $this->currentPlan['slug'] === 'free')
                        <a href="{{ route('billing.plans') }}">
                            <x-ui.button variant="primary">
                                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                                Upgrade Plan
                            </x-ui.button>
                        </a>
                        @else
                        <!-- Manage Subscription via Paddle Customer Portal -->
                        <a href="{{ route('billing.portal') }}">
                            <x-ui.button variant="primary">
                                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Manage Subscription
                            </x-ui.button>
                        </a>
                        
                        <a href="{{ route('billing.plans') }}">
                            <x-ui.button variant="secondary">
                                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                </svg>
                                Change Plan
                            </x-ui.button>
                        </a>
                        @endif
                    </div>

                    <!-- What You Can Manage Info -->
                    @if($this->currentPlan['isSubscribed'])
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <p class="text-sm font-medium text-gray-700 mb-3">With Manage Subscription, you can:</p>
                        <ul class="text-sm text-gray-600 space-y-2">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-emerald-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Update your payment method securely
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-emerald-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Cancel or pause your subscription
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-emerald-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                View and download invoices
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-emerald-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Update billing information and preferences
                            </li>
                        </ul>
                    </div>
                    @endif
                </x-ui.card>

                <!-- Usage Card -->
                <x-ui.card>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Plan Usage</h3>
                    
                    <!-- Monitors Usage -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Monitors</span>
                            <span class="text-sm text-gray-600">
                                {{ $this->usage['monitors'] }} / {{ $this->usage['monitorLimit'] === PHP_INT_MAX ? '∞' : $this->usage['monitorLimit'] }}
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-emerald-600 h-2 rounded-full transition-all" style="width: {{ $this->usage['monitorPercent'] }}%"></div>
                        </div>
                        @if($this->usage['monitors'] >= $this->usage['monitorLimit'] && $this->usage['monitorLimit'] !== PHP_INT_MAX)
                        <p class="text-xs text-red-600 mt-1">You've reached your monitor limit. Upgrade to add more!</p>
                        @endif
                    </div>

                    <!-- Check Interval -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-sm text-gray-600 mb-1">Check Interval</div>
                            <div class="text-2xl font-bold text-gray-900">{{ $this->usage['checkInterval'] }}</div>
                            <div class="text-xs text-gray-600">minutes</div>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-sm text-gray-600 mb-1">Team Members</div>
                            <div class="text-2xl font-bold text-gray-900">{{ $this->usage['teamUsers'] }}</div>
                            <div class="text-xs text-gray-600">users</div>
                        </div>
                    </div>
                </x-ui.card>

                <!-- Current Add-ons -->
                @if(!empty($this->addons()))
                <x-ui.card>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Active Add-ons</h3>
                    <div class="space-y-3">
                        @foreach($this->addons() as $addon)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-900">{{ $addon['name'] }}</p>
                                <p class="text-sm text-gray-600">{{ $addon['description'] }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-lg font-bold text-gray-900">${{ $addon['price'] }}</span>
                                <span class="text-sm text-gray-600">/mo</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </x-ui.card>
                @endif

            </div>

            <!-- Right Column - Quick Actions & Support -->
            <div class="space-y-6">
                
                <!-- Quick Actions Card -->
                <x-ui.card>
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h4>
                    <div class="space-y-3">
                        <a href="{{ route('billing.plans') }}" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-emerald-300 hover:bg-emerald-50 transition cursor-pointer w-full text-left">
                            <svg class="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">View All Plans</p>
                                <p class="text-xs text-gray-600">Compare features & pricing</p>
                            </div>
                        </a>

                        @if($this->currentPlan['isSubscribed'])
                        <a href="{{ route('billing.manage') }}" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition cursor-pointer w-full text-left">
                            <svg class="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Payment Methods</p>
                                <p class="text-xs text-gray-600">Update card details</p>
                            </div>
                        </a>

                        <a href="{{ route('billing.invoices') }}" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-purple-300 hover:bg-purple-50 transition cursor-pointer w-full text-left">
                            <svg class="h-5 w-5 text-purple-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Billing History</p>
                                <p class="text-xs text-gray-600">View invoices</p>
                            </div>
                        </a>
                        @endif

                        <button wire:click="openAddonsModal" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-emerald-300 hover:bg-emerald-50 transition cursor-pointer w-full text-left">
                            <svg class="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Browse Add-ons</p>
                                <p class="text-xs text-gray-600">Extra monitors, faster checks</p>
                            </div>
                        </button>
                    </div>
                </x-ui.card>

                <!-- Support Card -->
                <x-ui.card>
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Need Help?</h4>
                    <p class="text-sm text-gray-600 mb-4">
                        Have questions about billing or your subscription? We're here to help!
                    </p>
                    <a href="mailto:support@monitly.app">
                        <x-ui.button class="w-full justify-center" variant="secondary">
                            <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Contact Support
                        </x-ui.button>
                    </a>
                </x-ui.card>

            </div>
        </div>
    </div>

    <!-- Add-ons Modal -->
    @if($showAddonsModal)
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50" wire:click="closeAddonsModal"></div>
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl" wire:click.stop>
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-900">Available Add-ons</h3>
                        <button wire:click="closeAddonsModal" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    @if(empty($this->availableAddons()))
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <p class="mt-4 text-gray-600">No add-ons available for your current plan.</p>
                        <p class="mt-2 text-sm text-gray-500">Upgrade to Pro or Team to access add-ons!</p>
                    </div>
                    @else
                    <div class="space-y-4">
                        @foreach($this->availableAddons() as $key => $addon)
                        <div class="border border-gray-200 rounded-lg p-4 hover:border-emerald-300 transition cursor-pointer {{ in_array($key, $this->selectedAddons) ? 'bg-emerald-50 border-emerald-500' : '' }}"
                             wire:click="toggleAddon('{{ $key }}')">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <input 
                                            type="checkbox" 
                                            checked="{{ in_array($key, $this->selectedAddons) }}"
                                            class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded"
                                            onclick="event.stopPropagation()"
                                        >
                                        <h4 class="font-semibold text-gray-900">{{ $addon['name'] }}</h4>
                                    </div>
                                    <p class="text-sm text-gray-600 ml-7">{{ $addon['description'] }}</p>
                                </div>
                                <div class="text-right ml-4">
                                    <span class="text-lg font-bold text-gray-900">${{ $addon['price'] }}</span>
                                    <span class="text-sm text-gray-600">/mo</span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                
                @if(!empty($this->availableAddons()))
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-3">
                    <x-ui.button 
                        wire:click="purchaseAddons" 
                        variant="primary" 
                        class="w-full sm:w-auto justify-center"
                        :disabled="empty($this->selectedAddons)">
                        @if(!empty($this->selectedAddons))
                            Purchase {{ count($this->selectedAddons) }} Add-on{{ count($this->selectedAddons) > 1 ? 's' : '' }}
                        @else
                            Select Add-ons
                        @endif
                    </x-ui.button>
                    <x-ui.button wire:click="closeAddonsModal" variant="secondary" class="w-full sm:w-auto justify-center mt-3 sm:mt-0">
                        Close
                    </x-ui.button>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>