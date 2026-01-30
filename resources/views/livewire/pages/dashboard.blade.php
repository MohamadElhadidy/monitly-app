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
        ];
    }

    public function recentIncidents()
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
                'monitors.name',
                'monitors.url'
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
            ->limit(3)
            ->get();
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <h2 class="text-2xl font-bold leading-7 text-gray-900">Dashboard</h2>
    </x-slot>

    @php
        $plan = $this->plan();
        $stats = $this->monitorStats();
        $downMonitors = $this->topDownMonitors();
        $recentIncidents = $this->recentIncidents();
    @endphp

    <!-- Main content -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Payment Issue Alert -->
        @if ($plan['isInGrace'])
        <x-ui.alert type="warning" :dismissible="true" class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium">Payment Issue</h3>
                    <p class="mt-1 text-sm">Your subscription payment is past due. Please update your payment method to avoid service interruption.</p>
                </div>
                <x-ui.button href="{{ route('billing.index') }}" variant="secondary" size="sm">
                    Update Payment
                </x-ui.button>
            </div>
        </x-ui.alert>
        @endif

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            <!-- Total Monitors -->
            <x-ui.stat-card 
                title="Total Monitors" 
                :value="$stats['total'] . ' / ' . $plan['monitors']"
                color="gray"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                </x-slot:icon>
            </x-ui.stat-card>

            <!-- Online -->
            <x-ui.stat-card 
                title="Online" 
                :value="$stats['up']"
                color="emerald"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <!-- Offline -->
            <x-ui.stat-card 
                title="Offline" 
                :value="$stats['down']"
                color="red"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <!-- 30d Uptime -->
            <x-ui.stat-card 
                title="30d Uptime" 
                :value="$stats['sla'] . '%'"
                color="blue"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </x-slot:icon>
            </x-ui.stat-card>
        </div>

        <!-- Down Monitors Alert -->
        @if ($downMonitors->count() > 0)
        <x-ui.card class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Monitors Down</h2>
                <x-ui.button href="{{ route('monitors.index') }}" variant="ghost" size="sm">
                    View all →
                </x-ui.button>
            </div>
            <div class="space-y-3">
                @foreach ($downMonitors as $monitor)
                <div class="flex items-center justify-between p-4 rounded-lg bg-red-50 border border-red-200">
                    <div class="flex items-center gap-4 min-w-0 flex-1">
                        <div class="flex-shrink-0">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-100">
                                <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $monitor->name }}</p>
                            <p class="text-sm text-gray-600 truncate">{{ $monitor->url }}</p>
                        </div>
                    </div>
                    <x-ui.button href="{{ route('monitors.show', $monitor->id) }}" variant="secondary" size="sm">
                        View details
                    </x-ui.button>
                </div>
                @endforeach
            </div>
        </x-ui.card>
        @endif

        <!-- Recent Activity -->
        <x-ui.card>
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h2>
            @if ($recentIncidents->count() > 0)
            <div class="space-y-3">
                @foreach ($recentIncidents as $incident)
                <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
                    <div class="flex items-center gap-4 min-w-0 flex-1">
                        <div class="flex-shrink-0">
                            @if ($incident->recovered_at)
                            <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                            @else
                            <div class="h-2 w-2 rounded-full bg-red-500 animate-pulse"></div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $incident->name }}</p>
                            <p class="text-sm text-gray-500">
                                <span>{{ \Carbon\Carbon::parse($incident->started_at)->diffForHumans() }}</span>
                                @if ($incident->recovered_at)
                                <span class="mx-1">•</span>
                                <span class="text-emerald-600">Recovered</span>
                                @else
                                <span class="mx-1">•</span>
                                <span class="text-red-600">Still down</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <x-ui.badge :variant="$incident->recovered_at ? 'success' : 'danger'">
                        {{ $incident->recovered_at ? 'Resolved' : 'Active' }}
                    </x-ui.badge>
                </div>
                @endforeach
            </div>
            @else
            <x-ui.empty-state 
                icon="success"
                title="No incidents"
                description="All systems are running smoothly!"
            />
            @endif
        </x-ui.card>
    </div>
</div>
