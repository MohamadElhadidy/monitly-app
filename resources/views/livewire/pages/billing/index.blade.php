<?php
use Livewire\Volt\Component;
use App\Services\Billing\PlanLimits;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new
#[Layout('layouts.app')]
class extends Component {
    
    #[Computed]
    public function billable()
    {
        $user = auth()->user();
        $team = $user->currentTeam;
        
        // If user has a team with subscription, use team
        if ($team && $team->paddle_subscription_id) {
            return $team;
        }
        
        // Otherwise use user
        return $user;
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
            'isSubscribed' => method_exists($billable, 'isSubscribed') ? $billable->isSubscribed() : false,
            'nextBillAt' => $billable->next_bill_at ?? null,
            'isTeamBilling' => $billable instanceof \App\Models\Team,
        ];
    }

    #[Computed]
    public function usage()
    {
        $user = auth()->user();
        $billable = $this->billable();
        
        // Get monitor count and limit
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
            <p class="mt-2 text-sm text-gray-600">Manage your plan and add-ons</p>
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

                <div class="ml-6">
                    @if($this->currentPlan()['isSubscribed'])
                    <x-ui.button wire:click="manageSubscription" variant="secondary">
                        <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Manage Subscription
                    </x-ui.button>
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
</div>