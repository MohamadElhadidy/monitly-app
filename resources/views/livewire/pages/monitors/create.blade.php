<?php
use Livewire\Volt\Component;
use App\Models\Monitor;
use App\Services\Billing\PlanLimits;
use App\Rules\SafeMonitorUrl;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;

new
#[Layout('layouts.app')]
class extends Component {
    #[Validate('required|string|max:255')]
    public string $name = '';
    
    #[Validate('required|url|max:2048')]
    public string $url = '';
    
    #[Validate('boolean')]
    public bool $email_alerts_enabled = true;
    
    #[Validate('boolean')]
    public bool $paused = false;

    public function mount()
    {
        // Check if user can create more monitors
        $user = auth()->user();
        $currentCount = Monitor::where('user_id', $user->id)->count();
        $limit = PlanLimits::monitorLimitForUser($user);
        
        if ($currentCount >= $limit) {
            session()->flash('error', "You've reached your monitor limit ({$limit}). Please upgrade your plan or purchase additional monitors.");
            return redirect()->route('monitors.index');
        }
    }

    public function save()
    {
        $this->validate([
            'url' => ['required', 'url', 'max:2048', new SafeMonitorUrl()],
        ]);

        $user = auth()->user();
        $currentCount = Monitor::where('user_id', $user->id)->count();
        $limit = PlanLimits::monitorLimitForUser($user);
        
        if ($currentCount >= $limit) {
            $this->addError('general', "You've reached your monitor limit ({$limit}). Please upgrade your plan.");
            return;
        }

        $monitor = Monitor::create([
            'user_id' => $user->id,
            'team_id' => null,
            'name' => $this->name,
            'url' => $this->url,
            'email_alerts_enabled' => $this->email_alerts_enabled,
            'paused' => $this->paused,
            'last_status' => 'unknown',
            'consecutive_failures' => 0,
        ]);

        session()->flash('success', 'Monitor created successfully!');
        return redirect()->route('monitors.show', $monitor);
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <a href="{{ route('monitors.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <h2 class="text-2xl font-bold text-gray-900">Create Monitor</h2>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">
        @if(session('error'))
        <x-ui.alert variant="danger" class="mb-6">
            {{ session('error') }}
        </x-ui.alert>
        @endif

        @php
            $user = auth()->user();
            $currentCount = \App\Models\Monitor::where('user_id', $user->id)->count();
            $limit = \App\Services\Billing\PlanLimits::monitorLimitForUser($user);
            $plan = $user->billing_plan ?: 'free';
            $planName = ucfirst($plan);
            $interval = \App\Services\Billing\PlanLimits::effectiveIntervalMinutesForUser($user);
        @endphp

        <!-- Plan Info Banner -->
        <x-ui.card class="mb-6 bg-blue-50 border-blue-200">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-blue-900 mb-1">{{ $planName }} Plan</h3>
                    <p class="text-sm text-blue-700">
                        You're using {{ $currentCount }} of {{ $limit }} monitors. 
                        Checks will run every {{ $interval }} minutes.
                    </p>
                    @if($currentCount >= $limit)
                    <div class="mt-3">
                        <a href="{{ route('billing.index') }}" class="inline-flex items-center text-sm font-medium text-blue-700 hover:text-blue-800">
                            Upgrade your plan
                            <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <form wire:submit.prevent="save" class="space-y-6">
                @if($errors->has('general'))
                <x-ui.alert variant="danger">
                    {{ $errors->first('general') }}
                </x-ui.alert>
                @endif

                <!-- Monitor Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Monitor Name <span class="text-red-500">*</span>
                    </label>
                    <x-ui.input 
                        type="text" 
                        id="name"
                        wire:model="name"
                        placeholder="e.g., Production Website"
                        required
                    />
                    @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">A friendly name to identify this monitor</p>
                </div>

                <!-- URL -->
                <div>
                    <label for="url" class="block text-sm font-medium text-gray-700 mb-2">
                        URL to Monitor <span class="text-red-500">*</span>
                    </label>
                    <x-ui.input 
                        type="url" 
                        id="url"
                        wire:model="url"
                        placeholder="https://example.com"
                        required
                    />
                    @error('url')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">
                        The URL must be publicly accessible. Private IPs and localhost are not allowed.
                    </p>
                </div>

                <!-- Email Alerts -->
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <x-ui.checkbox 
                            id="email_alerts_enabled"
                            wire:model="email_alerts_enabled"
                        />
                    </div>
                    <div class="ml-3">
                        <label for="email_alerts_enabled" class="text-sm font-medium text-gray-700">
                            Enable email alerts
                        </label>
                        <p class="text-sm text-gray-500">Receive email notifications when this monitor goes down or recovers</p>
                    </div>
                </div>

                <!-- Start Paused -->
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <x-ui.checkbox 
                            id="paused"
                            wire:model="paused"
                        />
                    </div>
                    <div class="ml-3">
                        <label for="paused" class="text-sm font-medium text-gray-700">
                            Start paused
                        </label>
                        <p class="text-sm text-gray-500">Create the monitor in a paused state (no checks will run)</p>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t">
                    <a href="{{ route('monitors.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">
                        Cancel
                    </a>
                    <x-ui.button type="submit" variant="primary">
                        <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Create Monitor
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>