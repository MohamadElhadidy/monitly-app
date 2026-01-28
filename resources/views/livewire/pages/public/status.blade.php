<?php
use Livewire\Volt\Component;
use App\Models\Monitor;
use App\Models\User;
use App\Models\Team;
use App\Models\Incident;
use App\Helpers\TimezoneHelper;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new
#[Layout('layouts.public')]
class extends Component {
    public $identifier; // Can be user ID, team slug, or monitor UUID
    public $type; // 'user', 'team', or 'monitor'
    public $showIncidents = true;
    public $days = 90;
    
    public function mount($identifier, $type = 'auto')
    {
        $this->identifier = $identifier;
        
        // Auto-detect type if not specified
        if ($type === 'auto') {
            $this->type = $this->detectType($identifier);
        } else {
            $this->type = $type;
        }
    }
    
    private function detectType($identifier)
    {
        // Check if it's a UUID (monitor)
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            return 'monitor';
        }
        
        // Check if it's numeric (user ID)
        if (is_numeric($identifier)) {
            return 'user';
        }
        
        // Otherwise assume it's a team slug
        return 'team';
    }
    
    #[Computed]
    public function statusData()
    {
        $cacheKey = "public_status_{$this->type}_{$this->identifier}";
        
        return Cache::remember($cacheKey, 30, function () {
            switch ($this->type) {
                case 'user':
                    return $this->getUserStatusData();
                case 'team':
                    return $this->getTeamStatusData();
                case 'monitor':
                    return $this->getMonitorStatusData();
                default:
                    abort(404);
            }
        });
    }
    
    private function getUserStatusData()
    {
        $user = User::findOrFail($this->identifier);
        
        $monitors = Monitor::where('user_id', $user->id)
            ->where('is_public', true)
            ->where('paused', false)
            ->with(['latestCheck', 'openIncident'])
            ->get();
            
        if ($monitors->isEmpty()) {
            abort(404, 'No public monitors found');
        }
        
        return [
            'title' => $user->name . "'s Services",
            'subtitle' => 'Service Status Dashboard',
            'monitors' => $monitors,
            'owner_type' => 'user',
            'show_incidents' => true,
        ];
    }
    
    private function getTeamStatusData()
    {
        $team = Team::where('public_status_slug', $this->identifier)
            ->where('public_status_enabled', true)
            ->firstOrFail();
            
        $monitors = Monitor::where('team_id', $team->id)
            ->where('is_public', true)
            ->where('paused', false)
            ->with(['latestCheck', 'openIncident'])
            ->get();
            
        if ($monitors->isEmpty()) {
            abort(404, 'No public monitors found');
        }
        
        return [
            'title' => $team->name,
            'subtitle' => 'System Status',
            'monitors' => $monitors,
            'owner_type' => 'team',
            'show_incidents' => $team->public_show_incidents ?? true,
        ];
    }
    
    private function getMonitorStatusData()
    {
        $monitor = Monitor::where('id', $this->identifier)
            ->where('is_public', true)
            ->where('paused', false)
            ->with(['latestCheck', 'openIncident', 'owner'])
            ->firstOrFail();
            
        return [
            'title' => $monitor->name,
            'subtitle' => 'Service Status',
            'monitors' => collect([$monitor]),
            'owner_type' => 'monitor',
            'show_incidents' => true,
        ];
    }
    
    #[Computed]
    public function overallStatus()
    {
        $monitors = $this->statusData['monitors'];
        
        if ($monitors->isEmpty()) {
            return ['status' => 'unknown', 'label' => 'Unknown', 'color' => 'gray'];
        }
        
        $downCount = $monitors->where('last_status', 'down')->count();
        $degradedCount = $monitors->where('last_status', 'degraded')->count();
        
        if ($downCount > 0) {
            return [
                'status' => 'down', 
                'label' => 'Service Disruption',
                'message' => "{$downCount} service(s) are currently down",
                'color' => 'red',
                'icon' => 'error'
            ];
        }
        
        if ($degradedCount > 0) {
            return [
                'status' => 'degraded',
                'label' => 'Partial Outage',
                'message' => "{$degradedCount} service(s) are experiencing issues",
                'color' => 'yellow',
                'icon' => 'warning'
            ];
        }
        
        return [
            'status' => 'operational',
            'label' => 'All Systems Operational',
            'message' => 'All monitored services are running smoothly',
            'color' => 'green',
            'icon' => 'check'
        ];
    }
    
    #[Computed]
    public function uptimeStats()
    {
        $monitors = $this->statusData['monitors'];
        
        $totalUptime = 0;
        $count = 0;
        
        foreach ($monitors as $monitor) {
            if ($monitor->sla_uptime_pct_30d !== null) {
                $totalUptime += $monitor->sla_uptime_pct_30d;
                $count++;
            }
        }
        
        return [
            'average' => $count > 0 ? round($totalUptime / $count, 2) : 100,
            'period' => '30 days',
        ];
    }
    
    #[Computed]
    public function recentIncidents()
    {
        if (!$this->statusData['show_incidents']) {
            return collect();
        }
        
        $monitorIds = $this->statusData['monitors']->pluck('id');
        
        return Incident::whereIn('monitor_id', $monitorIds)
            ->where('started_at', '>=', now()->subDays($this->days))
            ->with('monitor')
            ->orderBy('started_at', 'desc')
            ->limit(20)
            ->get();
    }
    
    #[Computed]
    public function uptimeHistory()
    {
        $monitors = $this->statusData['monitors'];
        $days = 90;
        $history = [];
        
        foreach (range(0, $days - 1) as $daysAgo) {
            $date = now()->subDays($daysAgo);
            $dateKey = $date->format('Y-m-d');
            
            // Count incidents on this day
            $incidentCount = 0;
            foreach ($monitors as $monitor) {
                $count = Incident::where('monitor_id', $monitor->id)
                    ->whereDate('started_at', $date)
                    ->count();
                $incidentCount += $count;
            }
            
            $history[$dateKey] = [
                'date' => $dateKey,
                'status' => $incidentCount === 0 ? 'up' : ($incidentCount > 2 ? 'down' : 'degraded'),
                'incidents' => $incidentCount,
            ];
        }
        
        return array_reverse($history);
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-slate-100">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $this->statusData['title'] }}</h1>
                    <p class="text-gray-600 mt-1">{{ $this->statusData['subtitle'] }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <button 
                        wire:click="$refresh" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Overall Status Banner -->
        <div class="mb-8">
            <div class="bg-white rounded-2xl shadow-lg border-2 {{ $this->overallStatus['color'] === 'green' ? 'border-emerald-500' : ($this->overallStatus['color'] === 'yellow' ? 'border-yellow-500' : 'border-red-500') }} p-8">
                <div class="flex items-center gap-4">
                    @if($this->overallStatus['icon'] === 'check')
                    <div class="flex-shrink-0 w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center">
                        <svg class="h-10 w-10 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    @elseif($this->overallStatus['icon'] === 'warning')
                    <div class="flex-shrink-0 w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center">
                        <svg class="h-10 w-10 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    @else
                    <div class="flex-shrink-0 w-16 h-16 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="h-10 w-10 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    @endif
                    
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold {{ $this->overallStatus['color'] === 'green' ? 'text-emerald-900' : ($this->overallStatus['color'] === 'yellow' ? 'text-yellow-900' : 'text-red-900') }}">
                            {{ $this->overallStatus['label'] }}
                        </h2>
                        <p class="text-gray-600 mt-1">{{ $this->overallStatus['message'] }}</p>
                    </div>
                    
                    <div class="text-right">
                        <div class="text-4xl font-bold text-emerald-600">{{ $this->uptimeStats['average'] }}%</div>
                        <div class="text-sm text-gray-500 mt-1">{{ $this->uptimeStats['period'] }} uptime</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Services Grid -->
        <div class="mb-8">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Services</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($this->statusData['monitors'] as $monitor)
                <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-6 border border-gray-200">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-900 text-lg">{{ $monitor->name }}</h4>
                            <p class="text-sm text-gray-500 mt-1 break-all">{{ $monitor->url }}</p>
                        </div>
                        <div class="ml-4">
                            @if($monitor->last_status === 'up')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800">
                                <span class="w-2 h-2 bg-emerald-500 rounded-full mr-2 animate-pulse"></span>
                                Operational
                            </span>
                            @elseif($monitor->last_status === 'down')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                <span class="w-2 h-2 bg-red-500 rounded-full mr-2 animate-pulse"></span>
                                Down
                            </span>
                            @elseif($monitor->last_status === 'degraded')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                <span class="w-2 h-2 bg-yellow-500 rounded-full mr-2 animate-pulse"></span>
                                Degraded
                            </span>
                            @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                <span class="w-2 h-2 bg-gray-500 rounded-full mr-2"></span>
                                Unknown
                            </span>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Stats Row -->
                    <div class="grid grid-cols-3 gap-4 pt-4 border-t border-gray-100">
                        <div>
                            <div class="text-2xl font-bold {{ $monitor->sla_uptime_pct_30d >= 99 ? 'text-emerald-600' : ($monitor->sla_uptime_pct_30d >= 95 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $monitor->sla_uptime_pct_30d !== null ? number_format($monitor->sla_uptime_pct_30d, 2) : '—' }}%
                            </div>
                            <div class="text-xs text-gray-500 mt-1">30d Uptime</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900">
                                @if($monitor->latestCheck && $monitor->latestCheck->response_time_ms)
                                {{ $monitor->latestCheck->response_time_ms }}ms
                                @else
                                —
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Response Time</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900">
                                {{ $monitor->sla_incident_count_30d ?? 0 }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Incidents (30d)</div>
                        </div>
                    </div>
                    
                    @if($monitor->latestCheck)
                    <div class="mt-4 text-xs text-gray-500">
                        Last checked {{ TimezoneHelper::diffForHumans($monitor->latestCheck->checked_at) }}
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        <!-- 90-Day Uptime History -->
        <div class="mb-8">
            <h3 class="text-xl font-bold text-gray-900 mb-4">90-Day Uptime History</h3>
            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200">
                <div class="flex items-center gap-1 flex-wrap">
                    @foreach($this->uptimeHistory as $day)
                    <div 
                        class="w-2 h-8 rounded-sm transition-all hover:scale-110 cursor-pointer {{ $day['status'] === 'up' ? 'bg-emerald-500' : ($day['status'] === 'degraded' ? 'bg-yellow-500' : 'bg-red-500') }}"
                        title="{{ $day['date'] }}: {{ $day['incidents'] }} incident(s)"
                    ></div>
                    @endforeach
                </div>
                <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
                    <span>90 days ago</span>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-emerald-500 rounded-sm"></div>
                            <span>Operational</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-yellow-500 rounded-sm"></div>
                            <span>Degraded</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-red-500 rounded-sm"></div>
                            <span>Down</span>
                        </div>
                    </div>
                    <span>Today</span>
                </div>
            </div>
        </div>

        <!-- Recent Incidents -->
        @if($this->statusData['show_incidents'] && $this->recentIncidents->count() > 0)
        <div class="mb-8">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Recent Incidents</h3>
            <div class="bg-white rounded-xl shadow-md border border-gray-200 divide-y divide-gray-200">
                @foreach($this->recentIncidents->take(10) as $incident)
                <div class="p-6 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            @if($incident->recovered_at)
                            <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                                <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            @else
                            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                </svg>
                            </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h4 class="font-semibold text-gray-900">{{ $incident->monitor->name }}</h4>
                                @if($incident->recovered_at)
                                <span class="text-xs px-2 py-1 bg-emerald-100 text-emerald-700 rounded-full font-medium">Resolved</span>
                                @else
                                <span class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded-full font-medium animate-pulse">Ongoing</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-600 mb-2">{{ $incident->cause_summary }}</p>
                            <div class="flex items-center gap-4 text-xs text-gray-500">
                                <span>Started: {{ TimezoneHelper::formatWithTimezone($incident->started_at) }}</span>
                                @if($incident->recovered_at)
                                <span>•</span>
                                <span>Resolved: {{ TimezoneHelper::formatWithTimezone($incident->recovered_at) }}</span>
                                <span>•</span>
                                <span class="font-medium">Duration: {{ gmdate('H:i:s', $incident->downtime_seconds ?? 0) }}</span>
                                @else
                                <span>•</span>
                                <span class="font-medium text-red-600">Duration: {{ TimezoneHelper::diffForHumans($incident->started_at) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Footer -->
        <div class="text-center text-sm text-gray-500 py-6">
            <p>This page updates automatically every 30 seconds</p>
            <p class="mt-2">Powered by <a href="https://monitly.app" class="text-blue-600 hover:text-blue-800 font-medium">Monitly</a></p>
        </div>
    </div>
    
    <!-- Auto-refresh script -->
    <script>
        setInterval(function() {
            @this.call('$refresh');
        }, 30000); // Refresh every 30 seconds
    </script>
</div>