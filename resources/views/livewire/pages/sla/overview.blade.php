<?php
use Livewire\Volt\Component;
use App\Models\Monitor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new
#[Layout('layouts.app')]
class extends Component {
    public string $timeRange = '30'; // 7, 30, 90 days
    
    #[Computed]
    public function monitors()
    {
        return Monitor::where('user_id', auth()->id())
            ->orderBy('sla_uptime_pct_30d', 'asc')
            ->get();
    }

    #[Computed]
    public function globalHistory()
    {
        // Get global uptime history for all monitors
        $days = (int) $this->timeRange;
        $history = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            
            $allChecks = 0;
            $successfulChecks = 0;
            
            foreach ($this->monitors() as $monitor) {
                $dayTotal = $monitor->checks()
                    ->whereBetween('checked_at', [$dayStart, $dayEnd])
                    ->count();
                
                $daySuccess = $monitor->checks()
                    ->whereBetween('checked_at', [$dayStart, $dayEnd])
                    ->where('ok', true)
                    ->count();
                
                $allChecks += $dayTotal;
                $successfulChecks += $daySuccess;
            }
            
            $uptime = $allChecks > 0 ? ($successfulChecks / $allChecks) * 100 : 100;
            
            $history[] = [
                'date' => $date->format($days > 30 ? 'M j' : 'M j'),
                'full_date' => $date->format('Y-m-d'),
                'uptime' => round($uptime, 2),
                'checks' => $allChecks,
                'successful' => $successfulChecks,
                'incidents' => $this->getIncidentsForDay($date),
            ];
        }
        
        return $history;
    }

    private function getIncidentsForDay($date)
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        
        $count = 0;
        foreach ($this->monitors() as $monitor) {
            $count += $monitor->incidents()
                ->whereBetween('started_at', [$dayStart, $dayEnd])
                ->count();
        }
        
        return $count;
    }

    public function setTimeRange($range)
    {
        $this->timeRange = $range;
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <h2 class="text-2xl font-bold text-gray-900">SLA Reports</h2>
    </x-slot>

    @php
        $monitors = $this->monitors();
    @endphp

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <x-ui.stat-card 
                title="Average Uptime" 
                :value="number_format($monitors->avg('sla_uptime_pct_30d') ?? 100, 2) . '%'"
                color="emerald"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="Total Incidents" 
                :value="$monitors->sum('sla_incident_count_30d')"
                color="red"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="Total Monitors" 
                :value="$monitors->count()"
                color="blue"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="Avg Downtime" 
                :value="round($monitors->avg('sla_downtime_seconds_30d') / 60, 1) . ' min'"
                color="purple"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-slot:icon>
            </x-ui.stat-card>
        </div>

        <!-- Uptime History Chart -->
        <x-ui.card class="mb-8">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Global Uptime History</h3>
                
                <!-- Time Range Selector -->
                <div class="flex gap-2">
                    <button 
                        wire:click="setTimeRange('7')"
                        class="{{ $timeRange === '7' ? 'bg-emerald-100 text-emerald-700 font-semibold' : 'bg-white text-gray-700 hover:bg-gray-50' }} px-3 py-1.5 rounded-lg border border-gray-200 text-sm transition-colors"
                    >
                        7 Days
                    </button>
                    <button 
                        wire:click="setTimeRange('30')"
                        class="{{ $timeRange === '30' ? 'bg-emerald-100 text-emerald-700 font-semibold' : 'bg-white text-gray-700 hover:bg-gray-50' }} px-3 py-1.5 rounded-lg border border-gray-200 text-sm transition-colors"
                    >
                        30 Days
                    </button>
                    <button 
                        wire:click="setTimeRange('90')"
                        class="{{ $timeRange === '90' ? 'bg-emerald-100 text-emerald-700 font-semibold' : 'bg-white text-gray-700 hover:bg-gray-50' }} px-3 py-1.5 rounded-lg border border-gray-200 text-sm transition-colors"
                    >
                        90 Days
                    </button>
                </div>
            </div>

            <!-- Uptime Chart -->
            <div class="space-y-2">
                @foreach($this->globalHistory() as $day)
                <div class="flex items-center gap-3 py-1.5 hover:bg-gray-50 px-2 rounded transition-colors">
                    <div class="w-20 text-xs font-medium text-gray-600">
                        {{ $day['date'] }}
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-gray-200 rounded-full h-6 relative overflow-hidden">
                                <div 
                                    class="h-6 rounded-full transition-all {{ $day['uptime'] >= 99 ? 'bg-emerald-500' : ($day['uptime'] >= 95 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                    style="width: {{ $day['uptime'] }}%"
                                >
                                    <span class="absolute inset-0 flex items-center justify-center text-xs font-semibold {{ $day['uptime'] > 50 ? 'text-white' : 'text-gray-700' }}">
                                        {{ number_format($day['uptime'], 1) }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span class="w-24 text-right">
                            {{ $day['successful'] }}/{{ $day['checks'] }} checks
                        </span>
                        @if($day['incidents'] > 0)
                        <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full font-medium">
                            {{ $day['incidents'] }} incident{{ $day['incidents'] > 1 ? 's' : '' }}
                        </span>
                        @else
                        <span class="w-20 text-emerald-600 font-medium">
                            ✓ No issues
                        </span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            @if(count($this->globalHistory()) === 0)
            <p class="text-sm text-gray-500 text-center py-8">No history data available yet.</p>
            @endif
        </x-ui.card>

        <!-- Monitor SLA Table -->
        <x-ui.card>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Monitor SLA Performance (30 Days)</h3>
            
            @if($monitors->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Monitor
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Uptime
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Downtime
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Incidents
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($monitors as $monitor)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="font-medium text-gray-900">{{ $monitor->name }}</div>
                                    <div class="text-sm text-gray-500 truncate max-w-xs">{{ $monitor->url }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 min-w-[100px]">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div 
                                                class="h-2 rounded-full {{ ($monitor->sla_uptime_pct_30d ?? 100) >= 99 ? 'bg-emerald-500' : (($monitor->sla_uptime_pct_30d ?? 100) >= 95 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                                style="width: {{ $monitor->sla_uptime_pct_30d ?? 100 }}%"
                                            ></div>
                                        </div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 w-16 text-right">
                                        {{ number_format($monitor->sla_uptime_pct_30d ?? 100, 2) }}%
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                @php
                                    $downtime = $monitor->sla_downtime_seconds_30d ?? 0;
                                    if ($downtime < 60) {
                                        echo $downtime . 's';
                                    } elseif ($downtime < 3600) {
                                        echo round($downtime / 60, 1) . 'm';
                                    } else {
                                        echo round($downtime / 3600, 1) . 'h';
                                    }
                                @endphp
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                @if(($monitor->sla_incident_count_30d ?? 0) > 0)
                                <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full font-medium text-xs">
                                    {{ $monitor->sla_incident_count_30d }}
                                </span>
                                @else
                                <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <x-ui.badge :variant="($monitor->sla_uptime_pct_30d ?? 100) >= 99 ? 'success' : (($monitor->sla_uptime_pct_30d ?? 100) >= 95 ? 'warning' : 'danger')" size="sm">
                                    {{ ($monitor->sla_uptime_pct_30d ?? 100) >= 99 ? 'Excellent' : (($monitor->sla_uptime_pct_30d ?? 100) >= 95 ? 'Good' : 'Needs Attention') }}
                                </x-ui.badge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('monitors.show', $monitor) }}" class="text-emerald-600 hover:text-emerald-700 font-medium text-sm">
                                    View Details →
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No monitors yet</h3>
                <p class="mt-1 text-sm text-gray-500">Create monitors to start tracking SLA metrics</p>
                <div class="mt-6">
                    <a href="{{ route('monitors.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-emerald-600 hover:bg-emerald-700">
                        <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Create Monitor
                    </a>
                </div>
            </div>
            @endif
        </x-ui.card>
    </div>
</div>