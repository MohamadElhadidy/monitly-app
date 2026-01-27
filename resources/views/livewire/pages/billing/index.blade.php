<?php
use Livewire\Volt\Component;
use App\Services\Billing\PlanLimits;
use App\Models\Monitor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new
#[Layout('layouts.app')]
class extends Component {
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
        ];
    }

    #[Computed]
    public function usage()
    {
        $user = auth()->user();
        $activeMonitors = Monitor::where('user_id', $user->id)->count();
        $monitorLimit = PlanLimits::monitorLimitForUser($user);
        $intervalMinutes = PlanLimits::effectiveIntervalMinutesForUser($user);
        
        return [
            'monitors' => $activeMonitors,
            'monitorLimit' => $monitorLimit,
            'monitorPercent' => $monitorLimit > 0 ? round(($activeMonitors / $monitorLimit) * 100) : 0,
            'checkInterval' => $intervalMinutes,
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
            ];
        }
        
        if ($user->addon_interval_override_minutes) {
            $minutes = $user->addon_interval_override_minutes;
            $addons[] = [
                'name' => 'Faster Check Interval',
                'quantity' => 1,
                'description' => "{$minutes}-minute checks",
            ];
        }
        
        return $addons;
    }

    public function manageSubscription()
    {
        $user = auth()->user();
        
        if (!$user->paddle_customer_id) {
            $this->redirect(route('billing.checkout.page'));
            return;
        }
        
        // Open Paddle customer portal
        $this->dispatch('open-paddle-portal');
    }

    public function cancelSubscription()
    {
        $this->dispatch('open-cancel-modal');
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Billing & Subscription</h2>
            @if($this->currentPlan['isSubscribed'])
            <x-ui.badge :variant="$this->currentPlan['isInGrace'] ? 'warning' : 'success'">
                {{ $this->currentPlan['isInGrace'] ? 'Grace Period' : 'Active' }}
            </x-ui.badge>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column - Current Plan -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Current Plan Card -->
                <x-ui.card>
                    <div class="flex items-start justify-between mb-6">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $this->currentPlan['name'] }} Plan</h3>
                            <div class="flex items-center gap-3">
                                <x-ui.badge :variant="$this->currentPlan['isSubscribed'] ? 'success' : 'default'">
                                    {{ ucfirst($this->currentPlan['status']) }}
                                </x-ui.badge>
                                @if($this->currentPlan['price'] > 0)
                                <span class="text-2xl font-bold text-gray-900">
                                    ${{ number_format($this->currentPlan['price'], 0) }}
                                    <span class="text-sm font-normal text-gray-600">/month</span>
                                </span>
                                @else
                                <span class="text-2xl font-bold text-gray-900">Free Forever</span>
                                @endif
                            </div>
                            
                            @if($this->currentPlan['isInGrace'] && $this->currentPlan['graceEndsAt'])
                            <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <p class="text-sm text-yellow-800">
                                    <svg class="inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    Grace period ends {{ $this->currentPlan['graceEndsAt']->diffForHumans() }}
                                </p>
                            </div>
                            @elseif($this->currentPlan['nextBillAt'])
                            <p class="mt-2 text-sm text-gray-600">
                                Next billing date: {{ $this->currentPlan['nextBillAt']->format('F j, Y') }}
                            </p>
                            @endif
                        </div>
                        
                        @if($this->currentPlan['slug'] !== 'team')
                        <a href="{{ route('billing.checkout.page') }}">
                            <x-ui.button variant="primary">
                                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Upgrade Plan
                            </x-ui.button>
                        </a>
                        @endif
                    </div>

                    <!-- Usage Stats -->
                    <div class="border-t border-gray-200 pt-6">
                        <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-4">Usage & Limits</h4>
                        <div class="space-y-4">
                            <!-- Monitor Usage -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">Monitors</span>
                                    <span class="text-sm font-semibold text-gray-900">
                                        {{ $this->usage['monitors'] }} / {{ $this->usage['monitorLimit'] }}
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-emerald-600 h-2.5 rounded-full transition-all duration-300" 
                                         style="width: {{ min($this->usage['monitorPercent'], 100) }}%"></div>
                                </div>
                                @if($this->usage['monitorPercent'] >= 100)
                                <p class="mt-1 text-xs text-red-600">You've reached your monitor limit</p>
                                @endif
                            </div>

                            <!-- Check Interval -->
                            <div class="flex items-center justify-between py-3 border-t border-gray-100">
                                <span class="text-sm font-medium text-gray-700">Check Interval</span>
                                <span class="text-sm font-semibold text-gray-900">
                                    Every {{ $this->usage['checkInterval'] }} minutes
                                </span>
                            </div>

                            <!-- History Retention -->
                            <div class="flex items-center justify-between py-3 border-t border-gray-100">
                                <span class="text-sm font-medium text-gray-700">History Retention</span>
                                <span class="text-sm font-semibold text-gray-900">
                                    @if($this->currentPlan['slug'] === 'free')
                                        7 days
                                    @else
                                        Unlimited
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Active Add-ons -->
                    @if(count($this->addons) > 0)
                    <div class="border-t border-gray-200 pt-6 mt-6">
                        <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-4">Active Add-ons</h4>
                        <div class="space-y-3">
                            @foreach($this->addons as $addon)
                            <div class="flex items-center justify-between p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $addon['name'] }}</p>
                                        <p class="text-xs text-gray-600">{{ $addon['description'] }}</p>
                                    </div>
                                </div>
                                @if($addon['quantity'] > 1)
                                <x-ui.badge variant="success">x{{ $addon['quantity'] }}</x-ui.badge>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </x-ui.card>

                <!-- Plan Features -->
                <x-ui.card>
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Plan Features</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @php
                            $features = config("billing.plans.{$this->currentPlan['slug']}.features", []);
                        @endphp
                        
                        @if($features['email_alerts'] ?? false)
                        <div class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Email Alerts</p>
                                <p class="text-xs text-gray-600">Instant notifications</p>
                            </div>
                        </div>
                        @endif

                        @if($features['slack_integration'] ?? false)
                        <div class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Slack Integration</p>
                                <p class="text-xs text-gray-600">Team notifications</p>
                            </div>
                        </div>
                        @else
                        <div class="flex items-start gap-3 opacity-50">
                            <svg class="h-5 w-5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Slack Integration</p>
                                <p class="text-xs text-gray-500">Available in Team plan</p>
                            </div>
                        </div>
                        @endif

                        @if($features['webhooks'] ?? false)
                        <div class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Webhook Alerts</p>
                                <p class="text-xs text-gray-600">Custom integrations</p>
                            </div>
                        </div>
                        @else
                        <div class="flex items-start gap-3 opacity-50">
                            <svg class="h-5 w-5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Webhook Alerts</p>
                                <p class="text-xs text-gray-500">Available in Team plan</p>
                            </div>
                        </div>
                        @endif

                        @if($features['team_invitations'] ?? false)
                        <div class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Team Members</p>
                                <p class="text-xs text-gray-600">Collaborate together</p>
                            </div>
                        </div>
                        @else
                        <div class="flex items-start gap-3 opacity-50">
                            <svg class="h-5 w-5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Team Members</p>
                                <p class="text-xs text-gray-500">Available in Team plan</p>
                            </div>
                        </div>
                        @endif
                    </div>
                </x-ui.card>

            </div>

            <!-- Right Column - Quick Actions -->
            <div class="space-y-6">
                
                <!-- Quick Actions Card -->
                <x-ui.card>
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h4>
                    <div class="space-y-3">
                        @if($this->currentPlan['isSubscribed'])
                        <x-ui.button wire:click="manageSubscription" class="w-full justify-start" variant="secondary">
                            <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Manage Subscription
                        </x-ui.button>
                        @else
                        <a href="{{ route('billing.checkout.page') }}">
                            <x-ui.button class="w-full justify-start" variant="primary">
                                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Upgrade to Pro
                            </x-ui.button>
                        </a>
                        @endif

                        <a href="{{ route('billing.checkout.page') }}" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-emerald-300 hover:bg-emerald-50 transition cursor-pointer">
                            <svg class="h-5 w-5 text-gray-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Add-ons</p>
                                <p class="text-xs text-gray-600">Extra monitors, faster checks</p>
                            </div>
                        </a>

                        @if($this->currentPlan['isSubscribed'])
                        <button wire:click="cancelSubscription" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 transition cursor-pointer w-full text-left">
                            <svg class="h-5 w-5 text-gray-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Cancel Subscription</p>
                                <p class="text-xs text-gray-600">End your subscription</p>
                            </div>
                        </button>
                        @endif
                    </div>
                </x-ui.card>

                <!-- Support Card -->
                <x-ui.card>
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Need Help?</h4>
                    <p class="text-sm text-gray-600 mb-4">
                        Have questions about billing or your subscription? We're here to help!
                    </p>
                    <x-ui.button class="w-full" variant="secondary">
                        <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Contact Support
                    </x-ui.button>
                </x-ui.card>

            </div>
        </div>
    </div>
</div>