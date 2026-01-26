<?php

use Livewire\Volt\Component;
use App\Services\Billing\PlanLimits;
use App\Models\Monitor;
use Illuminate\Support\Facades\DB;

use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')] 
class extends Component {
    public function plan()
    {
        $user = auth()->user();
        $planName = $user->billing_plan ?? 'free';

        return [
            'name' => $planName,
            'status' => $user->billing_status ?? 'free',
            'monitors' => PlanLimits::baseMonitorLimit($planName),
            'users' => PlanLimits::baseSeatLimit($planName),
            'check_interval' => PlanLimits::baseIntervalMinutes($planName),
            'history_days' => PlanLimits::historyDays($planName),
            'isSubscribed' => $user->isSubscribed(),
            'isInGrace' => $user->isInGrace(),
        ];
    }

    public function monitorStats()
    {
        $user = auth()->user();

        // Get team IDs safely
        $teamIds = $user->teams()
            ->select('teams.id')
            ->pluck('teams.id')
            ->toArray();

        // Get all monitors (user's personal + team monitors)
        $monitors = Monitor::query()
            ->where(function ($q) use ($user, $teamIds) {
                $q->where('monitors.user_id', $user->id);

                if (!empty($teamIds)) {
                    $q->orWhereIn('monitors.team_id', $teamIds);
                }
            })
            ->get();

        $totalMonitors = $monitors->count();
        $upMonitors = $monitors->where('last_status', 'up')->count();
        $downMonitors = $monitors->where('last_status', 'down')->count();
        $pausedMonitors = $monitors->where('paused', true)->count();

        // Calculate SLA using the incidents table (actual downtime)
        $monitorIds = $monitors->pluck('id')->toArray();

        // Get total downtime from incidents in last 30 days
        $totalDowntimeSeconds = 0;
        if (!empty($monitorIds)) {
            $totalDowntimeSeconds = DB::table('incidents')
                ->whereIn('incidents.monitor_id', $monitorIds)
                ->where('incidents.started_at', '>=', now()->subDays(30))
                ->whereNotNull('incidents.downtime_seconds')
                ->sum('incidents.downtime_seconds');
        }

        $totalDowntimeHours = round($totalDowntimeSeconds / 3600, 2);

        // Calculate SLA percentage (using stored SLA columns from monitors)
        $avgSla = 0;
        if ($totalMonitors > 0) {
            $avgSla = round(
                $monitors->avg('sla_uptime_pct_30d') ?? 99.5,
                2
            );
        }

        return [
            'total' => $totalMonitors,
            'up' => $upMonitors,
            'down' => $downMonitors,
            'paused' => $pausedMonitors,
            'sla' => $avgSla,
            'downtime_hours' => $totalDowntimeHours,
            'incident_count' => $totalMonitors > 0 ? $monitors->sum('sla_incident_count_30d') ?? 0 : 0,
        ];
    }

    public function recentAlerts()
    {
        $user = auth()->user();

        // Get team IDs safely
        $teamIds = $user->teams()
            ->select('teams.id')
            ->pluck('teams.id')
            ->toArray();

        // Get recent incidents (downtime events)
        $incidents = DB::table('incidents')
            ->join('monitors', 'incidents.monitor_id', '=', 'monitors.id')
            ->where(function ($q) use ($user, $teamIds) {
                $q->where('monitors.user_id', $user->id);

                if (!empty($teamIds)) {
                    $q->orWhereIn('monitors.team_id', $teamIds);
                }
            })
            ->select(
                'incidents.id',
                'incidents.monitor_id',
                'incidents.started_at',
                'incidents.recovered_at',
                'monitors.name'
            )
            ->orderBy('incidents.started_at', 'desc')
            ->limit(5)
            ->get();

        return $incidents;
    }

    public function topDownMonitors()
    {
        $user = auth()->user();

        // Get team IDs safely
        $teamIds = $user->teams()
            ->select('teams.id')
            ->pluck('teams.id')
            ->toArray();

        return Monitor::query()
            ->where(function ($q) use ($user, $teamIds) {
                $q->where('monitors.user_id', $user->id);

                if (!empty($teamIds)) {
                    $q->orWhereIn('monitors.team_id', $teamIds);
                }
            })
            ->where('monitors.last_status', 'down')
            ->orderBy('monitors.updated_at', 'desc')
            ->limit(5)
            ->get();
    }
}; ?>
<div class="min-h-screen bg-gray-50">
        <!-- Header Section -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Dashboard</h1>
                <p class="text-lg text-gray-600">Welcome back! Here's your monitoring overview.</p>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            @php
                $plan = $this->plan();
            @endphp

            <!-- Grace Period Alert -->
            @if ($plan['isInGrace'])
                <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <strong>Payment Issue:</strong> Your subscription is in grace period. Please update your payment method.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Plan Status Card -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-8 border-l-4 border-blue-600">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Your Plan</h2>
                        <p class="text-gray-600 mt-1">{{ $plan['status'] === 'active' ? '✓ Plan active and running' : 'Upgrade to unlock premium features' }}</p>
                    </div>
                    <span class="inline-block bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-2 rounded-full font-semibold capitalize text-lg">
                        {{ $plan['name'] }}
                    </span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4">
                        <p class="text-gray-600 text-sm font-medium">Monitors</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2">{{ $plan['monitors'] }}</p>
                    </div>

                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4">
                        <p class="text-gray-600 text-sm font-medium">Team Members</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2">{{ $plan['users'] }}</p>
                    </div>

                    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-lg p-4">
                        <p class="text-gray-600 text-sm font-medium">Check Interval</p>
                        <p class="text-3xl font-bold text-emerald-600 mt-2">{{ $plan['check_interval'] }}</p>
                    </div>

                    <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg p-4">
                        <p class="text-gray-600 text-sm font-medium">History</p>
                        <p class="text-3xl font-bold text-orange-600 mt-2">
                            @if ($plan['history_days'] > 1000)
                                Unlimited
                            @else
                                {{ $plan['history_days'] }}d
                            @endif
                        </p>
                    </div>
                </div>

                <a href="{{ route('billing.index') }}" class="inline-block bg-black hover:bg-gray-900 text-white font-semibold py-3 px-8 rounded-lg transition-all">
                    Manage Billing →
                </a>
            </div>

            <!-- Monitoring Stats -->
            @php
                $stats = $this->monitorStats();
            @endphp

            <h3 class="text-2xl font-bold text-gray-900 mb-6">Monitoring Overview</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-8">
                <!-- Total Monitors -->
                <div class="bg-white rounded-lg shadow p-6 border-t-4 border-blue-600 hover:shadow-lg transition">
                    <p class="text-gray-600 text-sm font-medium">Total</p>
                    <p class="text-4xl font-bold text-gray-900 mt-2">{{ $stats['total'] }}</p>
                </div>

                <!-- Up -->
                <div class="bg-white rounded-lg shadow p-6 border-t-4 border-green-600 hover:shadow-lg transition">
                    <p class="text-gray-600 text-sm font-medium">Up</p>
                    <p class="text-4xl font-bold text-green-600 mt-2">{{ $stats['up'] }}</p>
                </div>

                <!-- Down -->
                <div class="bg-white rounded-lg shadow p-6 border-t-4 border-red-600 hover:shadow-lg transition">
                    <p class="text-gray-600 text-sm font-medium">Down</p>
                    <p class="text-4xl font-bold text-red-600 mt-2">{{ $stats['down'] }}</p>
                </div>

                <!-- Paused -->
                <div class="bg-white rounded-lg shadow p-6 border-t-4 border-yellow-600 hover:shadow-lg transition">
                    <p class="text-gray-600 text-sm font-medium">Paused</p>
                    <p class="text-4xl font-bold text-yellow-600 mt-2">{{ $stats['paused'] }}</p>
                </div>

                <!-- SLA -->
                <div class="bg-white rounded-lg shadow p-6 border-t-4 border-purple-600 hover:shadow-lg transition">
                    <p class="text-gray-600 text-sm font-medium">SLA (30d)</p>
                    <p class="text-4xl font-bold text-purple-600 mt-2">{{ $stats['sla'] }}%</p>
                </div>

                <!-- Downtime -->
                <div class="bg-white rounded-lg shadow p-6 border-t-4 border-indigo-600 hover:shadow-lg transition">
                    <p class="text-gray-600 text-sm font-medium">Downtime</p>
                    <p class="text-4xl font-bold text-indigo-600 mt-2">{{ $stats['downtime_hours'] }}h</p>
                </div>
            </div>

            <!-- Down Monitors Alert -->
            @php
                $downMonitors = $this->topDownMonitors();
            @endphp

            @if ($downMonitors->count() > 0)
                <h3 class="text-2xl font-bold text-gray-900 mb-6">⚠️ Monitors Down</h3>

                <div class="grid gap-4 mb-8">
                    @foreach ($downMonitors as $monitor)
                        <div class="bg-gradient-to-r from-red-50 to-orange-50 rounded-lg shadow p-6 border-l-4 border-red-600">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-900">{{ $monitor->name }}</h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ $monitor->url }}</p>
                                </div>
                                <a href="{{ route('monitors.show', $monitor->id) }}" class="bg-black hover:bg-gray-900 text-white font-semibold py-2 px-6 rounded-lg transition">
                                    View
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Quick Actions -->
            <h3 class="text-2xl font-bold text-gray-900 mb-6">Quick Actions</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                <!-- Create Monitor -->
                <a href="{{ route('monitors.index') }}" class="bg-white rounded-lg shadow p-8 hover:shadow-lg transition transform hover:scale-105 cursor-pointer group">
                    <div class="bg-blue-100 w-12 h-12 rounded-lg flex items-center justify-center mb-4 group-hover:bg-blue-600 transition">
                        <svg class="h-6 w-6 text-blue-600 group-hover:text-white transition" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">Create Monitor</h4>
                    <p class="text-gray-600 text-sm">Start monitoring a new service</p>
                </a>

                <!-- View Reports -->
                <a href="{{ route('sla.overview') }}" class="bg-white rounded-lg shadow p-8 hover:shadow-lg transition transform hover:scale-105 cursor-pointer group">
                    <div class="bg-purple-100 w-12 h-12 rounded-lg flex items-center justify-center mb-4 group-hover:bg-purple-600 transition">
                        <svg class="h-6 w-6 text-purple-600 group-hover:text-white transition" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <polyline points="19 12 16 9 16 15"></polyline>
                            <polyline points="5 12 8 9 8 15"></polyline>
                        </svg>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">View Reports</h4>
                    <p class="text-gray-600 text-sm">Check SLA and performance</p>
                </a>

                <!-- Manage Billing -->
                <a href="{{ route('billing.index') }}" class="bg-white rounded-lg shadow p-8 hover:shadow-lg transition transform hover:scale-105 cursor-pointer group">
                    <div class="bg-green-100 w-12 h-12 rounded-lg flex items-center justify-center mb-4 group-hover:bg-green-600 transition">
                        <svg class="h-6 w-6 text-green-600 group-hover:text-white transition" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                            <line x1="1" y1="10" x2="23" y2="10"></line>
                        </svg>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">Manage Billing</h4>
                    <p class="text-gray-600 text-sm">Upgrade plan & subscription</p>
                </a>
            </div>

            <!-- Recent Incidents -->
            @php
                $recentAlerts = $this->recentAlerts();
            @endphp

            <h3 class="text-2xl font-bold text-gray-900 mb-6">Recent Activity</h3>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h4 class="font-semibold text-gray-900">Latest Incidents (Last 30 days)</h4>
                </div>

                @if ($recentAlerts->count() > 0)
                    <div class="divide-y divide-gray-200">
                        @foreach ($recentAlerts as $alert)
                            <div class="px-6 py-4 hover:bg-gray-50 transition cursor-pointer" onclick="window.location='{{ route('monitors.show', $alert->monitor_id) }}'">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $alert->name }}</p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            Started: {{ \Carbon\Carbon::parse($alert->started_at)->diffForHumans() }}
                                            @if ($alert->recovered_at)
                                                • Recovered: {{ \Carbon\Carbon::parse($alert->recovered_at)->diffForHumans() }}
                                            @else
                                                • <span class="text-red-600 font-semibold">Still Down</span>
                                            @endif
                                        </p>
                                    </div>
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold {{ $alert->recovered_at ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $alert->recovered_at ? 'Recovered' : 'Active' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <p class="text-gray-600 font-medium">No incidents</p>
                        <p class="text-gray-500 text-sm mt-1">All systems are running smoothly!</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
