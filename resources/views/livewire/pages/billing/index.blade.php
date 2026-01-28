<?php
// File: resources/views/livewire/pages/billing/index-with-cancel.blade.php
use Livewire\Volt\Component;
use App\Services\Billing\PlanLimits;
use App\Services\Billing\PaddleService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new
#[Layout('layouts.app')]
class extends Component {
    
    public $showCancelModal = false;
    public $cancelReason = '';
    
    #[Computed]
    public function billable()
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        
        // Use team if it has active subscription
        if ($team && $team->paddle_subscription_id && in_array($team->billing_status ?? '', ['active', 'grace'])) {
            return $team;
        }
        
        // Use user if they have active subscription
        if ($user->paddle_subscription_id && in_array($user->billing_status ?? '', ['active', 'grace'])) {
            return $user;
        }
        
        return $team ?? $user;
    }
    
    #[Computed]
    public function currentPlan()
    {
        $billable = $this->billable();
        $planSlug = $billable->billing_plan ?? 'free';
        $planConfig = config("billing.plans.{$planSlug}", config('billing.plans.free'));
        
        return [
            'slug' => $planSlug,
            'name' => $planConfig['name'] ?? ucfirst($planSlug),
            'price' => $planConfig['price'] ?? 0,
            'status' => $billable->billing_status ?? 'free',
            'isSubscribed' => in_array($billable->billing_status ?? '', ['active', 'grace']),
            'nextBillAt' => $billable->next_bill_at ?? null,
            'isTeamBilling' => $billable instanceof \App\Models\Team,
        ];
    }

    #[Computed]
    public function usage()
    {
        $user = auth()->user();
        $billable = $this->billable();
        
        if ($billable instanceof \App\Models\Team) {
            $activeMonitors = $billable->monitors()->count();
            $monitorLimit = PlanLimits::monitorLimitForTeam($billable);
            $intervalMinutes = PlanLimits::effectiveIntervalMinutesForTeam($billable);
            $teamUsers = $billable->users()->count();
        } else {
            $activeMonitors = \App\Models\Monitor::where('user_id', $user->id)->count();
            $monitorLimit = PlanLimits::monitorLimitForUser($user);
            $intervalMinutes = PlanLimits::effectiveIntervalMinutesForUser($user);
            $teamUsers = 1;
        }
        
        return [
            'monitors' => $activeMonitors,
            'monitorLimit' => $monitorLimit,
            'monitorPercent' => $monitorLimit > 0 ? min(100, round(($activeMonitors / $monitorLimit) * 100)) : 0,
            'checkInterval' => $intervalMinutes,
            'teamUsers' => $teamUsers,
        ];
    }

    #[Computed]
    public function plans()
    {
        return [
            'free' => config('billing.plans.free'),
            'pro' => config('billing.plans.pro'),
            'team' => config('billing.plans.team'),
        ];
    }

    #[Computed]
    public function availableAddons()
    {
        $currentPlan = $this->currentPlan()['slug'];
        $allAddons = config('billing.addons', []);
        $available = [];
        
        foreach ($allAddons as $key => $addon) {
            if (isset($addon['allowed_plans']) && in_array($currentPlan, $addon['allowed_plans'])) {
                $available[$key] = $addon;
            }
        }
        
        return $available;
    }

    public function upgradeToPlan($planSlug)
    {
        if ($planSlug === $this->currentPlan()['slug']) {
            session()->flash('info', 'You are already on this plan.');
            return;
        }
        
        return redirect()->route('billing.checkout.page', ['plan' => $planSlug]);
    }

    public function manageSubscription()
    {
        return redirect()->route('billing.portal');
    }
    
    public function openCancelModal()
    {
        if (!$this->currentPlan()['isSubscribed']) {
            session()->flash('error', 'You do not have an active subscription to cancel.');
            return;
        }
        
        $this->showCancelModal = true;
    }
    
    public function cancelSubscription()
    {
        try {
            $billable = $this->billable();
            
            if (!$billable->paddle_subscription_id) {
                session()->flash('error', 'No subscription found.');
                $this->showCancelModal = false;
                return;
            }
            
            // Use Paddle service to cancel
            $paddleService = app(PaddleService::class);
            $success = $paddleService->cancelSubscription($billable->paddle_subscription_id, false);
            
            if ($success) {
                // Update local status
                $billable->billing_status = 'canceled';
                $billable->save();
                
                session()->flash('success', 'Your subscription has been canceled. You will have access until ' . ($billable->next_bill_at?->format('M d, Y') ?? 'the end of your billing period') . '.');
            } else {
                session()->flash('error', 'Unable to cancel subscription. Please try using the Paddle portal or contact support.');
            }
            
            $this->showCancelModal = false;
            
        } catch (\Exception $e) {
            \Log::error('Subscription cancellation error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            
            session()->flash('error', 'An error occurred. Please contact support.');
            $this->showCancelModal = false;
        }
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
            <p class="mt-2 text-sm text-gray-600">Manage your plan, add-ons, and subscription</p>
        </div>

        <!-- Flash Messages -->
        @if (session('success'))
        <x-ui.alert type="success" class="mb-6">
            {{ session('success') }}
        </x-ui.alert>
        @endif

        @if (session('error'))
        <x-ui.alert type="danger" class="mb-6">
            {{ session('error') }}
        </x-ui.alert>
        @endif

        @if (session('info'))
        <x-ui.alert type="info" class="mb-6">
            {{ session('info') }}
        </x-ui.alert>
        @endif

        <!-- Current Plan Card -->
        <x-ui.card class="mb-8 border-2 border-emerald-500 bg-emerald-50">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <h2 class="text-2xl font-bold text-gray-900">{{ $this->currentPlan()['name'] }} Plan</h2>
                        @if($this->currentPlan()['isTeamBilling'])
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            Team Subscription
                        </span>
                        @endif
                    </div>
                    
                    @if($this->currentPlan()['slug'] !== 'free')
                    <div class="space-y-2 mb-4">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-600">Status:</span>
                            <span class="font-semibold {{ $this->currentPlan()['status'] === 'active' ? 'text-emerald-600' : 'text-gray-900' }}">
                                {{ ucfirst($this->currentPlan()['status']) }}
                            </span>
                        </div>
                        @if($this->currentPlan()['nextBillAt'])
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-600">Next billing:</span>
                            <span class="font-semibold text-gray-900">
                                {{ $this->currentPlan()['nextBillAt']->format('M d, Y') }}
                            </span>
                        </div>
                        @endif
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-600">Monthly cost:</span>
                            <span class="font-semibold text-gray-900">
                                ${{ $this->currentPlan()['price'] }}/month
                            </span>
                        </div>
                    </div>
                    @endif

                    <!-- Usage Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-600">Monitors</span>
                                <span class="text-lg font-bold text-gray-900">
                                    {{ $this->usage()['monitors'] }} / {{ $this->usage()['monitorLimit'] }}
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ $this->usage()['monitorPercent'] }}%"></div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                            <div class="text-sm text-gray-600 mb-2">Check Interval</div>
                            <div class="text-2xl font-bold text-gray-900">{{ $this->usage()['checkInterval'] }} min</div>
                        </div>

                        @if($this->currentPlan()['isTeamBilling'])
                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                            <div class="text-sm text-gray-600 mb-2">Team Members</div>
                            <div class="text-2xl font-bold text-gray-900">{{ $this->usage()['teamUsers'] }}</div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="ml-6 flex flex-col gap-3">
                    @if($this->currentPlan()['isSubscribed'])
                    <!-- Manage Subscription Button -->
                    <x-ui.button wire:click="manageSubscription" variant="secondary">
                        <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Manage Subscription
                    </x-ui.button>
                    
                    <!-- Cancel Button -->
                    <button wire:click="openCancelModal" 
                            class="inline-flex items-center justify-center px-4 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition">
                        <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancel Subscription
                    </button>
                    @endif
                </div>
            </div>
        </x-ui.card>

        <!-- Available Plans -->
        @if($this->currentPlan()['slug'] === 'free')
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Upgrade Your Plan</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach(['pro', 'team'] as $planSlug)
                @php $plan = $this->plans()[$planSlug]; @endphp
                <x-ui.card class="relative border-2 {{ $planSlug === 'team' ? 'border-emerald-600' : 'border-gray-200' }} hover:shadow-xl transition">
                    @if($planSlug === 'team')
                    <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                        <span class="bg-emerald-600 text-white px-4 py-1 rounded-full text-sm font-semibold">
                            Best Value
                        </span>
                    </div>
                    @endif

                    <div class="text-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan['name'] }}</h3>
                        <div class="mb-4">
                            <span class="text-5xl font-bold text-gray-900">${{ $plan['price'] }}</span>
                            <span class="text-gray-600">/month</span>
                        </div>
                        <p class="text-sm text-gray-600">{{ $plan['description'] }}</p>
                    </div>

                    <ul class="space-y-3 mb-6">
                        @foreach($plan['feature_list'] as $feature)
                        <li class="flex items-start gap-2">
                            <svg class="h-5 w-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-sm text-gray-700">{{ $feature }}</span>
                        </li>
                        @endforeach
                    </ul>

                    <x-ui.button wire:click="upgradeToPlan('{{ $planSlug }}')" class="w-full justify-center" variant="primary">
                        Upgrade to {{ $plan['name'] }}
                    </x-ui.button>
                </x-ui.card>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Add-ons Section -->
        @if(!empty($this->availableAddons()))
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Available Add-ons</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($this->availableAddons() as $key => $addon)
                <x-ui.card class="hover:shadow-lg transition">
                    <div class="mb-4">
                        <h4 class="text-lg font-bold text-gray-900 mb-2">{{ $addon['name'] }}</h4>
                        <p class="text-sm text-gray-600 mb-3">{{ $addon['description'] }}</p>
                        <div class="flex items-baseline gap-1">
                            <span class="text-3xl font-bold text-gray-900">${{ $addon['price'] }}</span>
                            <span class="text-sm text-gray-600">/month</span>
                        </div>
                    </div>

                    <a href="{{ route('billing.checkout.page', ['plan' => $this->currentPlan()['slug'], 'addon' => $key]) }}" class="block">
                        <x-ui.button class="w-full justify-center" variant="primary">
                            Add to Plan
                        </x-ui.button>
                    </a>
                </x-ui.card>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Help Section -->
        <div class="mt-12">
            <x-ui.card class="bg-gray-50">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Need Help?</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Have questions about billing or need assistance? We're here to help!
                        </p>
                        <a href="mailto:support@monitly.app" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                            Contact Support →
                        </a>
                    </div>
                </div>
            </x-ui.card>
        </div>

    </div>

    <!-- 🔥 CANCEL SUBSCRIPTION MODAL -->
    @if($showCancelModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showCancelModal') }">
        <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showCancelModal', false)"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2">
                            Cancel Subscription
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 mb-4">
                                Are you sure you want to cancel your <strong>{{ $this->currentPlan()['name'] }}</strong> subscription?
                            </p>
                            
                            @if($this->currentPlan()['nextBillAt'])
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-700">
                                            You'll keep access until <strong>{{ $this->currentPlan()['nextBillAt']->format('M d, Y') }}</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            @endif
                            
                            <div class="mb-4">
                                <label for="cancelReason" class="block text-sm font-medium text-gray-700 mb-1">
                                    Why are you canceling? (Optional)
                                </label>
                                <textarea 
                                    wire:model="cancelReason" 
                                    id="cancelReason"
                                    rows="3" 
                                    class="shadow-sm focus:ring-red-500 focus:border-red-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                    placeholder="Help us improve by sharing your feedback..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3">
                    <button type="button" 
                            wire:click="cancelSubscription"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Yes, Cancel Subscription
                    </button>
                    <button type="button" 
                            wire:click="$set('showCancelModal', false)"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">
                        Keep Subscription
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>