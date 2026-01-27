<?php
use Livewire\Volt\Component;
use App\Models\Monitor;
use App\Services\Billing\PlanLimits;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new
#[Layout('layouts.app')]
class extends Component {
    public string $statusFilter = 'all';
    
    #[Computed]
    public function monitors()
    {
        $query = Monitor::where('user_id', auth()->id());
        
        if ($this->statusFilter !== 'all') {
            $query->where('last_status', $this->statusFilter);
        }
        
        return $query->latest()->get();
    }
    
    #[Computed]
    public function stats()
    {
        $user = auth()->user();
        $allMonitors = Monitor::where('user_id', $user->id)->get();
        
        return [
            'total' => $allMonitors->count(),
            'up' => $allMonitors->where('last_status', 'up')->count(),
            'down' => $allMonitors->where('last_status', 'down')->count(),
            'paused' => $allMonitors->where('paused', true)->count(),
            'limit' => PlanLimits::monitorLimitForUser($user),
            'interval' => PlanLimits::effectiveIntervalMinutesForUser($user),
            'plan' => ucfirst($user->billing_plan ?: 'free'),
        ];
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-2xl font-bold text-gray-900">Monitors</h2>
            <x-ui.button href="{{ route('monitors.create') }}" variant="primary">
                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create Monitor
            </x-ui.button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        @if(session('success'))
        <x-ui.alert variant="success" class="mb-6">
            {{ session('success') }}
        </x-ui.alert>
        @endif

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <x-ui.stat-card 
                title="Total Monitors" 
                :value="$this->stats['total'] . ' / ' . $this->stats['limit']"
                color="blue"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="Up" 
                :value="$this->stats['up']"
                color="emerald"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="Down" 
                :value="$this->stats['down']"
                :color="$this->stats['down'] > 0 ? 'red' : 'gray'"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="Check Interval" 
                :value="'Every ' . $this->stats['interval'] . ' min'"
                color="purple"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-slot:icon>
            </x-ui.stat-card>
        </div>

        <!-- Plan Usage Notice -->
        @if($this->stats['total'] >= $this->stats['limit'])
        <x-ui.alert variant="warning" class="mb-6">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-yellow-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-yellow-800">Monitor Limit Reached</h3>
                    <p class="text-sm text-yellow-700 mt-1">
                        You've reached your {{ $this->stats['plan'] }} plan limit of {{ $this->stats['limit'] }} monitors.
                        <a href="{{ route('billing.index') }}" class="font-medium underline hover:no-underline">
                            Upgrade your plan or purchase additional monitors
                        </a>
                    </p>
                </div>
            </div>
        </x-ui.alert>
        @endif

        <!-- Filters -->
        @if($this->monitors->count() > 0)
        <div class="mb-6 flex items-center gap-2">
            <span class="text-sm font-medium text-gray-700">Filter:</span>
            <button 
                wire:click="$set('statusFilter', 'all')"
                class="px-3 py-1 text-sm rounded-lg {{ $statusFilter === 'all' ? 'bg-blue-100 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}"
            >
                All
            </button>
            <button 
                wire:click="$set('statusFilter', 'up')"
                class="px-3 py-1 text-sm rounded-lg {{ $statusFilter === 'up' ? 'bg-emerald-100 text-emerald-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}"
            >
                Up
            </button>
            <button 
                wire:click="$set('statusFilter', 'down')"
                class="px-3 py-1 text-sm rounded-lg {{ $statusFilter === 'down' ? 'bg-red-100 text-red-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}"
            >
                Down
            </button>
            <button 
                wire:click="$set('statusFilter', 'unknown')"
                class="px-3 py-1 text-sm rounded-lg {{ $statusFilter === 'unknown' ? 'bg-gray-100 text-gray-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}"
            >
                Unknown
            </button>
        </div>
        @endif

        <!-- Monitors Table -->
        @if($this->monitors->count() > 0)
        <x-ui.card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Monitor
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                30d Uptime
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Last Check
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($this->monitors as $monitor)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('monitors.show', $monitor) }}" class="font-medium text-gray-900 hover:text-blue-600">
                                            {{ $monitor->name }}
                                        </a>
                                        @if($monitor->paused)
                                        <x-ui.badge variant="secondary" size="sm">Paused</x-ui.badge>
                                        @endif
                                        @if($monitor->is_public)
                                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Public">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-500 mt-1">{{ Str::limit($monitor->url, 60) }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if($monitor->paused)
                                    <x-ui.badge variant="secondary">Paused</x-ui.badge>
                                @else
                                    <x-ui.badge :variant="match($monitor->last_status) {
                                        'up' => 'success',
                                        'down' => 'danger',
                                        'degraded' => 'warning',
                                        default => 'secondary'
                                    }">
                                        {{ ucfirst($monitor->last_status) }}
                                    </x-ui.badge>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($monitor->sla_uptime_pct_30d !== null)
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 rounded-full h-2 max-w-[100px]">
                                        <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ min(100, $monitor->sla_uptime_pct_30d) }}%"></div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ number_format($monitor->sla_uptime_pct_30d, 2) }}%
                                    </span>
                                </div>
                                @else
                                <span class="text-sm text-gray-400">N/A</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    {{ $monitor->updated_at ? $monitor->updated_at->diffForHumans() : 'Never' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('monitors.show', $monitor) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        View
                                    </a>
                                    <a href="{{ route('monitors.edit', $monitor) }}" class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                                        Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
        @else
        <x-ui.empty-state 
            icon="monitor"
            title="No monitors yet"
            description="Create your first monitor to start tracking uptime and performance"
        >
            <x-slot:action>
                <x-ui.button href="{{ route('monitors.create') }}" variant="primary">
                    <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Create Your First Monitor
                </x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
        @endif
    </div>
</div>