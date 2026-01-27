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
    public $showCancelModal = false;
    public $showAddonsModal = false;
    public $selectedAddons = [];
    public $cancelReason = '';

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

    public function openCancelModal()
    {
        $this->showCancelModal = true;
    }

    public function closeCancelModal()
    {
        $this->showCancelModal = false;
        $this->cancelReason = '';
    }

    public function confirmCancel()
    {
        $user = auth()->user();
        
        if (!$user->isSubscribed()) {
            session()->flash('error', 'No active subscription to cancel.');
            $this->closeCancelModal();
            return;
        }
        
        try {
            // Call the cancel route
            return redirect()->route('billing.cancel')->with('cancel_reason', $this->cancelReason);
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to cancel subscription. Please try again.');
            $this->closeCancelModal();
        }
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
        
        <!-- Alert Messages -->
        @if (session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg flex items-start gap-3">
            <svg class="h-5 w-5 text-green-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-sm text-green-800">{{ session('success') }}</p>
        </div>
        @endif
        
        @if (session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
            <svg class="h-5 w-5 text-red-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <p class="text-sm text-red-800">{{ session('error') }}</p>
        </div>
        @endif

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
                </x-ui.card>

                <!-- Usage Card -->
                <x-ui.card>
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Resource Usage</h4>
                    <div class="space-y-6">
                        <!-- Monitors -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700">Monitors</span>
                                </div>
                                <span class="text-sm font-semibold text-gray-900">
                                    {{ $this->usage['monitors'] }} / {{ $this->usage['monitorLimit'] }}
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-blue-600 h-2.5 rounded-full transition-all" 
                                     style="width: {{ $this->usage['monitorPercent'] }}%"></div>
                            </div>
                            @if($this->usage['monitorPercent'] >= 80)
                            <p class="mt-2 text-xs text-amber-600">
                                ⚠️ You're using {{ $this->usage['monitorPercent'] }}% of your monitors. Consider upgrading or adding more.
                            </p>
                            @else
                            <p class="mt-2 text-xs text-gray-600">
                                {{ $this->usage['monitorAvailable'] }} monitors available
                            </p>
                            @endif
                        </div>

                        <!-- Check Interval -->
                        <div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700">Check Interval</span>
                                </div>
                                <span class="text-sm font-semibold text-gray-900">
                                    Every {{ $this->usage['checkInterval'] }} minutes
                                </span>
                            </div>
                        </div>

                        <!-- Team Users (if Team plan) -->
                        @if($this->currentPlan['slug'] === 'team')
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700">Team Members</span>
                                </div>
                                <span class="text-sm font-semibold text-gray-900">
                                    {{ $this->usage['teamUsers'] }} / 5
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-purple-600 h-2.5 rounded-full transition-all" 
                                     style="width: {{ min(100, ($this->usage['teamUsers'] / 5) * 100) }}%"></div>
                            </div>
                        </div>
                        @endif
                    </div>
                </x-ui.card>

                <!-- Active Add-ons Card -->
                @if(!empty($this->addons()))
                <x-ui.card>
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-lg font-semibold text-gray-900">Active Add-ons</h4>
                        <x-ui.badge variant="success">{{ count($this->addons()) }} Active</x-ui.badge>
                    </div>
                    <div class="space-y-3">
                        @foreach($this->addons() as $addon)
                        <div class="flex items-center justify-between p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-900">{{ $addon['name'] }}</p>
                                <p class="text-sm text-gray-600">{{ $addon['description'] }}</p>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">
                                ${{ $addon['price'] }}/mo
                            </span>
                        </div>
                        @endforeach
                    </div>
                </x-ui.card>
                @endif

                <!-- Plan Features -->
                <x-ui.card>
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Plan Features</h4>
                    @php
                        $features = config("billing.plans.{$this->currentPlan['slug']}.features", []);
                    @endphp
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Email Alerts</p>
                                <p class="text-xs text-gray-600">Instant notifications</p>
                            </div>
                        </div>

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
                                <p class="text-xs text-gray-500">Upgrade to unlock</p>
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
                                <p class="text-xs text-gray-500">Upgrade to unlock</p>
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
                                <p class="text-xs text-gray-500">Upgrade to unlock</p>
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
                        @if(!$this->currentPlan['isSubscribed'])
                        <a href="{{ route('billing.checkout.page') }}" class="block">
                            <x-ui.button class="w-full justify-center" variant="primary">
                                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Upgrade Now
                            </x-ui.button>
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

                        @if($this->currentPlan['isSubscribed'])
                        <button wire:click="openCancelModal" class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 transition cursor-pointer w-full text-left">
                            <svg class="h-5 w-5 text-red-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

    <!-- Cancel Subscription Modal -->
    @if($showCancelModal)
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50" wire:click="closeCancelModal"></div>
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg" wire:click.stop>
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left flex-1">
                            <h3 class="text-lg font-semibold leading-6 text-gray-900">Cancel Subscription</h3>
                            <div class="mt-4 space-y-4">
                                <p class="text-sm text-gray-500">
                                    We're sorry to see you go! Your subscription will remain active until the end of your billing period.
                                </p>
                                <div>
                                    <label for="cancelReason" class="block text-sm font-medium text-gray-700 mb-2">
                                        Help us improve - Why are you cancelling? (optional)
                                    </label>
                                    <textarea 
                                        wire:model="cancelReason"
                                        id="cancelReason"
                                        rows="3"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
                                        placeholder="Your feedback helps us improve..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-3">
                    <x-ui.button wire:click="confirmCancel" variant="danger" class="w-full sm:w-auto justify-center">
                        Yes, Cancel Subscription
                    </x-ui.button>
                    <x-ui.button wire:click="closeCancelModal" variant="secondary" class="w-full sm:w-auto justify-center mt-3 sm:mt-0">
                        Nevermind, Keep Subscription
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
    @endif

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