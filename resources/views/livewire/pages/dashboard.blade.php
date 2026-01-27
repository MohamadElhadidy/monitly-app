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
        $teamIds = $user->teams()->select('teams.id')->pluck('teams.id')->toArray();

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

        $monitorIds = $monitors->pluck('id')->toArray();
        $totalDowntimeSeconds = 0;
        
        if (!empty($monitorIds)) {
            $totalDowntimeSeconds = DB::table('incidents')
                ->whereIn('incidents.monitor_id', $monitorIds)
                ->where('incidents.started_at', '>=', now()->subDays(30))
                ->whereNotNull('incidents.downtime_seconds')
                ->sum('incidents.downtime_seconds');
        }

        $totalDowntimeHours = round($totalDowntimeSeconds / 3600, 2);
        $avgSla = 0;
        
        if ($totalMonitors > 0) {
            $avgSla = round($monitors->avg('sla_uptime_pct_30d') ?? 99.5, 2);
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
        $teamIds = $user->teams()->select('teams.id')->pluck('teams.id')->toArray();

        return DB::table('incidents')
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
    }

    public function topDownMonitors()
    {
        $user = auth()->user();
        $teamIds = $user->teams()->select('teams.id')->pluck('teams.id')->toArray();

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

<div>
    <x-slot name="breadcrumbs">
        <h2 class="text-base font-semibold leading-7 text-white">Overview</h2>
    </x-slot>

    @php
        $plan = $this->plan();
        $stats = $this->monitorStats();
        $downMonitors = $this->topDownMonitors();
        $recentAlerts = $this->recentAlerts();
    @endphp

    <!-- Grace Period Alert -->
    @if ($plan['isInGrace'])
        <div class="border-b border-white/[0.08] bg-yellow-900/20">
            <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-yellow-200">
                            Your subscription payment failed. Please update your payment method to avoid service interruption.
                        </p>
                    </div>
                    <a href="{{ route('billing.index') }}" class="inline-flex items-center gap-2 rounded-md bg-yellow-500 px-3 py-1.5 text-sm font-semibold text-yellow-950 hover:bg-yellow-400 transition">
                        Update payment
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    @endif

    <!-- Main content -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            <!-- Total Monitors -->
            <div class="relative overflow-hidden rounded-lg bg-[#1a1a1a] border border-white/[0.08] px-4 py-5 shadow-sm sm:px-6 sm:py-6 hover:border-white/[0.12] transition-colors">
                <dt>
                    <div class="absolute rounded-md bg-white/[0.06] p-3">
                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                        </svg>
                    </div>
                    <p class="ml-16 truncate text-sm font-medium text-gray-400">Total Monitors</p>
                </dt>
                <dd class="ml-16 flex items-baseline mt-1">
                    <p class="text-2xl font-semibold text-white">{{ $stats['total'] }}</p>
                    <p class="ml-2 text-sm text-gray-500">of {{ $plan['monitors'] }}</p>
                </dd>
            </div>

            <!-- Uptime -->
            <div class="relative overflow-hidden rounded-lg bg-[#1a1a1a] border border-white/[0.08] px-4 py-5 shadow-sm sm:px-6 sm:py-6 hover:border-white/[0.12] transition-colors">
                <dt>
                    <div class="absolute rounded-md bg-emerald-500/10 p-3">
                        <svg class="h-6 w-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="ml-16 truncate text-sm font-medium text-gray-400">Online</p>
                </dt>
                <dd class="ml-16 flex items-baseline mt-1">
                    <p class="text-2xl font-semibold text-emerald-400">{{ $stats['up'] }}</p>
                </dd>
            </div>

            <!-- Down -->
            <div class="relative overflow-hidden rounded-lg bg-[#1a1a1a] border border-white/[0.08] px-4 py-5 shadow-sm sm:px-6 sm:py-6 hover:border-white/[0.12] transition-colors">
                <dt>
                    <div class="absolute rounded-md bg-red-500/10 p-3">
                        <svg class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <p class="ml-16 truncate text-sm font-medium text-gray-400">Offline</p>
                </dt>
                <dd class="ml-16 flex items-baseline mt-1">
                    <p class="text-2xl font-semibold text-red-400">{{ $stats['down'] }}</p>
                </dd>
            </div>

            <!-- SLA -->
            <div class="relative overflow-hidden rounded-lg bg-[#1a1a1a] border border-white/[0.08] px-4 py-5 shadow-sm sm:px-6 sm:py-6 hover:border-white/[0.12] transition-colors">
                <dt>
                    <div class="absolute rounded-md bg-blue-500/10 p-3">
                        <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>
                    <p class="ml-16 truncate text-sm font-medium text-gray-400">30d Uptime</p>
                </dt>
                <dd class="ml-16 flex items-baseline mt-1">
                    <p class="text-2xl font-semibold text-blue-400">{{ $stats['sla'] }}%</p>
                </dd>
            </div>
        </div>

        <!-- Down Monitors Alert -->
        @if ($downMonitors->count() > 0)
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold leading-7 text-white">Monitors Down</h2>
                    <a href="{{ route('monitors.index') }}" class="text-sm font-medium text-emerald-400 hover:text-emerald-300 transition-colors">
                        View all →
                    </a>
                </div>
                <div class="space-y-3">
                    @foreach ($downMonitors as $monitor)
                        <div class="rounded-lg bg-red-500/10 border border-red-500/20 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4 min-w-0 flex-1">
                                    <div class="flex-shrink-0">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-500/10">
                                            <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-white truncate">{{ $monitor->name }}</p>
                                        <p class="text-sm text-gray-400 truncate">{{ $monitor->url }}</p>
                                    </div>
                                </div>
                                <a href="{{ route('monitors.show', $monitor->id) }}" class="ml-4 flex-shrink-0 rounded-md bg-white/[0.06] px-3 py-1.5 text-sm font-semibold text-white hover:bg-white/[0.12] border border-white/[0.08] transition-colors">
                                    View details
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Recent Activity -->
        <div>
            <h2 class="text-base font-semibold leading-7 text-white mb-4">Recent Activity</h2>
            <div class="rounded-lg bg-[#1a1a1a] border border-white/[0.08] shadow-sm overflow-hidden">
                @if ($recentAlerts->count() > 0)
                    <ul role="list" class="divide-y divide-white/[0.08]">
                        @foreach ($recentAlerts as $alert)
                            <li class="px-6 py-4 hover:bg-white/[0.02] transition cursor-pointer" onclick="window.location='{{ route('monitors.show', $alert->monitor_id) }}'">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4 min-w-0">
                                        <div class="flex-shrink-0">
                                            @if ($alert->recovered_at)
                                                <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                                            @else
                                                <div class="h-2 w-2 rounded-full bg-red-500 animate-pulse"></div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-white truncate">{{ $alert->name }}</p>
                                            <p class="text-sm text-gray-400">
                                                <span>{{ \Carbon\Carbon::parse($alert->started_at)->diffForHumans() }}</span>
                                                @if ($alert->recovered_at)
                                                    <span class="mx-1">•</span>
                                                    <span class="text-emerald-400">Recovered {{ \Carbon\Carbon::parse($alert->recovered_at)->diffForHumans() }}</span>
                                                @else
                                                    <span class="mx-1">•</span>
                                                    <span class="text-red-400">Still down</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    <x-ui.badge :variant="$alert->recovered_at ? 'success' : 'danger'">
                                        {{ $alert->recovered_at ? 'Resolved' : 'Active' }}
                                    </x-ui.badge>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-ui.empty-state 
                        icon="success"
                        title="No incidents"
                        description="All systems are running smoothly!"
                    />
                @endif
            </div>
        </div>
    </div>
</div>