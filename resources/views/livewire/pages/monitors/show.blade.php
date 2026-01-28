<?php
use Livewire\Volt\Component;
use App\Models\Monitor;
use App\Services\Billing\PlanLimits;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new
#[Layout('layouts.app')]
class extends Component {
    public Monitor $monitor;
    public string $activeTab = 'overview'; // overview, checks, incidents, history
    
    public function mount(Monitor $monitor)
    {
        $this->authorize('view', $monitor);
        $this->monitor = $monitor;
    }
    
    #[Computed]
    public function checkInterval()
    {
        $user = auth()->user();
        return PlanLimits::effectiveIntervalMinutesForUser($user);
    }
    
    #[Computed]
    public function recentChecks()
    {
        return $this->monitor->checks()
            ->latest('checked_at')
            ->limit(50)
            ->get();
    }
    
    #[Computed]
    public function recentIncidents()
    {
        return $this->monitor->incidents()
            ->latest('started_at')
            ->limit(30)
            ->get();
    }

    #[Computed]
    public function uptimeHistory()
    {
        // Get uptime by day for last 30 days
        $history = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            
            // Get total checks for the day
            $totalChecks = $this->monitor->checks()
                ->whereBetween('checked_at', [$dayStart, $dayEnd])
                ->count();
            
            // Get successful checks
            $successfulChecks = $this->monitor->checks()
                ->whereBetween('checked_at', [$dayStart, $dayEnd])
                ->where('ok', true)
                ->count();
            
            $uptime = $totalChecks > 0 ? ($successfulChecks / $totalChecks) * 100 : 100;
            
            $history[] = [
                'date' => $date->format('M j'),
                'uptime' => round($uptime, 2),
                'checks' => $totalChecks,
                'successful' => $successfulChecks,
            ];
        }
        
        return $history;
    }
    
    public function togglePause()
    {
        $this->authorize('update', $this->monitor);
        
        $this->monitor->update([
            'paused' => !$this->monitor->paused
        ]);
        
        $this->monitor->refresh();
        
        session()->flash('success', 
            $this->monitor->paused 
                ? 'Monitor paused successfully' 
                : 'Monitor resumed successfully'
        );
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <a href="{{ route('monitors.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <h2 class="text-2xl font-bold text-gray-900">{{ $monitor->name }}</h2>
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
            </div>
            <div class="flex gap-2">
                <button 
                    wire:click="togglePause"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
                >
                    @if($monitor->paused)
                    <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                    </svg>
                    Resume
                    @else
                    <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                    </svg>
                    Pause
                    @endif
                </button>
                <x-ui.button href="{{ route('monitors.edit', $monitor) }}" variant="secondary">
                    <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                    </svg>
                    Edit
                </x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        @if(session('success'))
        <x-ui.alert variant="success" class="mb-6">
            {{ session('success') }}
        </x-ui.alert>
        @endif

        <!-- Status Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <x-ui.stat-card 
                title="Current Status" 
                :value="$monitor->paused ? 'Paused' : ucfirst($monitor->last_status)"
                :color="$monitor->paused ? 'gray' : match($monitor->last_status) {
                    'up' => 'emerald',
                    'down' => 'red',
                    'degraded' => 'yellow',
                    default => 'gray'
                }"
            >
                <x-slot:icon>
                    @if($monitor->paused)
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                    @else
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    @endif
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="30d Uptime" 
                :value="$monitor->sla_uptime_pct_30d ? number_format($monitor->sla_uptime_pct_30d, 2) . '%' : 'N/A'"
                color="blue"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="30d Incidents" 
                :value="$monitor->sla_incident_count_30d ?? 0"
                :color="($monitor->sla_incident_count_30d ?? 0) > 0 ? 'yellow' : 'gray'"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="Check Interval" 
                :value="'Every ' . $this->checkInterval . ' min'"
                color="purple"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-slot:icon>
            </x-ui.stat-card>
        </div>

        <!-- Tabs -->
        <div class="mb-6 border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <button 
                    wire:click="setTab('overview')"
                    class="{{ $activeTab === 'overview' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                >
                    Overview
                </button>
                <button 
                    wire:click="setTab('checks')"
                    class="{{ $activeTab === 'checks' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                >
                    Recent Checks ({{ $this->recentChecks()->count() }})
                </button>
                <button 
                    wire:click="setTab('incidents')"
                    class="{{ $activeTab === 'incidents' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                >
                    Incidents ({{ $this->recentIncidents()->count() }})
                </button>
                <button 
                    wire:click="setTab('history')"
                    class="{{ $activeTab === 'history' ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                >
                    30-Day History
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        @if($activeTab === 'overview')
            <!-- Monitor Details -->
            <x-ui.card class="mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Monitor Details</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 mb-1">URL</dt>
                        <dd class="text-sm text-gray-900 break-all">
                            <a href="{{ $monitor->url }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 hover:underline">
                                {{ $monitor->url }}
                                <svg class="inline h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 mb-1">Created</dt>
                        <dd class="text-sm text-gray-900">{{ $monitor->created_at->format('M j, Y g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 mb-1">Last Check</dt>
                        <dd class="text-sm text-gray-900">
                            {{ $monitor->updated_at ? $monitor->updated_at->diffForHumans() : 'Never' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 mb-1">Next Check</dt>
                        <dd class="text-sm text-gray-900">
                            @if($monitor->paused)
                            <span class="text-gray-400">Paused</span>
                            @else
                            {{ $monitor->next_check_at ? $monitor->next_check_at->diffForHumans() : 'Pending' }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 mb-1">Email Alerts</dt>
                        <dd class="text-sm text-gray-900">
                            @if($monitor->email_alerts_enabled)
                            <x-ui.badge variant="success" size="sm">Enabled</x-ui.badge>
                            @else
                            <x-ui.badge variant="secondary" size="sm">Disabled</x-ui.badge>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 mb-1">Public Status</dt>
                        <dd class="text-sm text-gray-900">
                            @if($monitor->is_public)
                            <x-ui.badge variant="success" size="sm">Visible</x-ui.badge>
                            @else
                            <x-ui.badge variant="secondary" size="sm">Private</x-ui.badge>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-ui.card>
        @endif

        @if($activeTab === 'checks')
            <!-- Recent Checks -->
            <x-ui.card>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Checks</h3>
                    <span class="text-sm text-gray-500">Last 50 checks</span>
                </div>
                
                @if($this->recentChecks()->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Time
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Response Time
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status Code
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Error
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($this->recentChecks() as $check)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $check->checked_at->format('M j, H:i') }}
                                    <span class="text-xs text-gray-500 block">{{ $check->checked_at->diffForHumans() }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-ui.badge :variant="$check->ok ? 'success' : 'danger'" size="sm">
                                        {{ $check->ok ? 'Up' : 'Down' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $check->response_time_ms ? $check->response_time_ms . 'ms' : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $check->status_code ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                    {{ $check->error_message ?? '-' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-sm text-gray-500 text-center py-8">No checks yet. Monitoring will start shortly.</p>
                @endif
            </x-ui.card>
        @endif

        @if($activeTab === 'incidents')
            <!-- Recent Incidents -->
            <x-ui.card>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Incidents</h3>
                    <span class="text-sm text-gray-500">Last 30 incidents</span>
                </div>
                
                @if($this->recentIncidents()->count() > 0)
                <div class="space-y-4">
                    @foreach($this->recentIncidents() as $incident)
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    @if($incident->recovered_at)
                                    <x-ui.badge variant="secondary" size="sm">Recovered</x-ui.badge>
                                    @else
                                    <x-ui.badge variant="danger" size="sm">Ongoing</x-ui.badge>
                                    @endif
                                    <span class="text-sm text-gray-600">
                                        Started {{ $incident->started_at->format('M j, H:i') }}
                                    </span>
                                </div>
                                
                                @if($incident->cause_summary)
                                <p class="text-sm text-gray-700 mb-2">{{ $incident->cause_summary }}</p>
                                @endif
                                
                                @if($incident->recovered_at)
                                <div class="flex items-center gap-4 text-xs text-gray-500">
                                    <span>
                                        Recovered: {{ $incident->recovered_at->format('M j, H:i') }}
                                    </span>
                                    <span>
                                        Duration: {{ gmdate('H:i:s', $incident->downtime_seconds) }}
                                    </span>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-sm text-gray-500 text-center py-8">No incidents recorded. Great job!</p>
                @endif
            </x-ui.card>
        @endif

        @if($activeTab === 'history')
            <!-- 30-Day History -->
            <x-ui.card>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">30-Day Uptime History</h3>
                
                <div class="space-y-3">
                    @foreach($this->uptimeHistory() as $day)
                    <div class="flex items-center gap-4">
                        <div class="w-16 text-sm text-gray-600">
                            {{ $day['date'] }}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-gray-200 rounded-full h-6">
                                    <div 
                                        class="h-6 rounded-full transition-all {{ $day['uptime'] >= 99 ? 'bg-emerald-500' : ($day['uptime'] >= 95 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                        style="width: {{ $day['uptime'] }}%"
                                    ></div>
                                </div>
                                <span class="w-16 text-sm font-medium text-gray-900">
                                    {{ number_format($day['uptime'], 1) }}%
                                </span>
                            </div>
                        </div>
                        <div class="w-24 text-xs text-gray-500 text-right">
                            {{ $day['successful'] }}/{{ $day['checks'] }} checks
                        </div>
                    </div>
                    @endforeach
                </div>

                @if(count($this->uptimeHistory()) === 0)
                <p class="text-sm text-gray-500 text-center py-8">No history data available yet.</p>
                @endif
            </x-ui.card>
        @endif
    </div>
</div>