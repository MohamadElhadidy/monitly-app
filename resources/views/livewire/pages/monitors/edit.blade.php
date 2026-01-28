<?php
use Livewire\Volt\Component;
use App\Models\Monitor;
use App\Rules\SafeMonitorUrl;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;

new
#[Layout('layouts.app')]
class extends Component {
    public Monitor $monitor;
    
    #[Validate('required|string|max:255')]
    public string $name = '';
    
    #[Validate('required|url|max:2048')]
    public string $url = '';
    
    #[Validate('boolean')]
    public bool $email_alerts_enabled = true;
    
    #[Validate('boolean')]
    public bool $paused = false;
    
    #[Validate('boolean')]
    public bool $is_public = false;

    public function mount(Monitor $monitor)
    {
        $this->authorize('update', $monitor);
        
        $this->monitor = $monitor;
        $this->name = $monitor->name;
        $this->url = $monitor->url;
        $this->email_alerts_enabled = $monitor->email_alerts_enabled;
        $this->paused = $monitor->paused;
        $this->is_public = $monitor->is_public;
    }

    public function save()
    {
        $this->authorize('update', $this->monitor);
        
        $this->validate([
            'url' => ['required', 'url', 'max:2048', new SafeMonitorUrl()],
        ]);

        $this->monitor->update([
            'name' => $this->name,
            'url' => $this->url,
            'email_alerts_enabled' => $this->email_alerts_enabled,
            'paused' => $this->paused,
            'is_public' => $this->is_public,
        ]);

        session()->flash('success', 'Monitor updated successfully!');
        return redirect()->route('monitors.show', $this->monitor);
    }

    public function delete()
    {
        $this->authorize('delete', $this->monitor);
        
        $this->monitor->delete();
        
        session()->flash('success', 'Monitor deleted successfully!');
        return redirect()->route('monitors.index');
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <a href="{{ route('monitors.show', $monitor) }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <h2 class="text-2xl font-bold text-gray-900">Edit Monitor</h2>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">
        @if(session('success'))
        <x-ui.alert variant="success" class="mb-6">
            {{ session('success') }}
        </x-ui.alert>
        @endif

        <x-ui.card>
            <form wire:submit.prevent="save" class="space-y-6">
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

                <!-- Paused -->
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <x-ui.checkbox 
                            id="paused"
                            wire:model="paused"
                        />
                    </div>
                    <div class="ml-3">
                        <label for="paused" class="text-sm font-medium text-gray-700">
                            Pause monitoring
                        </label>
                        <p class="text-sm text-gray-500">Temporarily stop checking this monitor (no alerts will be sent)</p>
                    </div>
                </div>

                <!-- Public Status -->
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <x-ui.checkbox 
                            id="is_public"
                            wire:model="is_public"
                        />
                    </div>
                    <div class="ml-3">
                        <label for="is_public" class="text-sm font-medium text-gray-700">
                            Show on public status page
                        </label>
                        <p class="text-sm text-gray-500">Display this monitor's status on your public status page</p>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-between pt-4 border-t">
                    <button 
                        type="button"
                        wire:click="delete"
                        wire:confirm="Are you sure you want to delete this monitor? This action cannot be undone."
                        class="px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg"
                    >
                        Delete Monitor
                    </button>
                    
                    <div class="flex items-center gap-3">
                        <a href="{{ route('monitors.show', $monitor) }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">
                            Cancel
                        </a>
                        <x-ui.button type="submit" variant="primary">
                            Save Changes
                        </x-ui.button>
                    </div>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>