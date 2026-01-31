<?php
use Livewire\Volt\Component;
use App\Services\Dashboard\DashboardStatsService;
use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')]
class extends Component {
    public array $stats = [];

    public function mount(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $service = new DashboardStatsService(auth()->user());
        $this->stats = $service->getAllStats();
    }

    public function refresh(): void
    {
        $service = new DashboardStatsService(auth()->user());
        $service->clearCache();
        $this->loadStats();
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-2xl font-bold leading-7 text-gray-900">Dashboard</h2>
            <div class="flex items-center gap-3">
                <button wire:click="refresh" wire:loading.attr="disabled" 
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors disabled:opacity-50">
                    <svg wire:loading.class="animate-spin" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    <span wire:loading.remove>Refresh</span>
                    <span wire:loading>Refreshing…</span>
                </button>
                <a href="{{ route('monitors.create') }}" 
                   class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    New Monitor
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $monitors = $stats['monitors'] ?? [];
        $incidents = $stats['incidents'] ?? [];
        $performance = $stats['performance'] ?? [];
        $checks = $stats['checks'] ?? [];
        $plan = $stats['plan'] ?? [];
        $recentMonitors = $stats['recent_monitors'] ?? collect();
        $recentIncidents = $stats['recent_incidents'] ?? collect();
    @endphp

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        
        {{-- Payment Issue Alert --}}
        @if (($plan['status'] ?? 'free') === 'past_due')
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <div>
                        <h3 class="text-sm font-semibold text-amber-900">Payment Issue</h3>
                        <p class="text-sm text-amber-700">Your subscription payment is past due. Please update your payment method.</p>
                    </div>
                </div>
                <a href="{{ route('billing.index') }}" class="rounded-lg bg-amber-100 px-3 py-1.5 text-sm font-semibold text-amber-900 hover:bg-amber-200 transition-colors">
                    Update Payment
                </a>
            </div>
        </div>
        @endif

        {{-- KPI Stats Grid --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 mb-8">
            {{-- Monitors Up --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ $monitors['up'] ?? 0 }}<span class="text-sm font-normal text-gray-500">/{{ $monitors['total'] ?? 0 }}</span></p>
                        <p class="text-xs text-gray-500">Monitors Up</p>
                    </div>
                </div>
            </div>

            {{-- Open Incidents --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 {{ ($incidents['open'] ?? 0) > 0 ? 'border-red-200 bg-red-50' : '' }}">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg {{ ($incidents['open'] ?? 0) > 0 ? 'bg-red-100' : 'bg-gray-100' }}">
                        <svg class="h-5 w-5 {{ ($incidents['open'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold {{ ($incidents['open'] ?? 0) > 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $incidents['open'] ?? 0 }}</p>
                        <p class="text-xs {{ ($incidents['open'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-500' }}">Open Incidents</p>
                    </div>
                </div>
            </div>

            {{-- Avg Response --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100">
                        <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ $performance['avg_response_1h_ms'] ?? '—' }}<span class="text-sm font-normal text-gray-500">ms</span></p>
                        <p class="text-xs text-gray-500">Avg Response (1h)</p>
                    </div>
                </div>
            </div>

            {{-- Checks Last 24h --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100">
                        <svg class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($checks['total_24h'] ?? 0) }}</p>
                        <p class="text-xs text-gray-500">Checks (24h)</p>
                    </div>
                </div>
            </div>

            {{-- Uptime 24h --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-100">
                        <svg class="h-5 w-5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ $checks['uptime_24h'] ?? 100 }}<span class="text-sm font-normal text-gray-500">%</span></p>
                        <p class="text-xs text-gray-500">Uptime (24h)</p>
                    </div>
                </div>
            </div>

            {{-- Plan & Usage --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100">
                        <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-gray-900 capitalize">{{ $plan['name'] ?? 'Free' }}</p>
                        <p class="text-xs text-gray-500">{{ $plan['monitor_count'] ?? 0 }}/{{ $plan['monitor_limit'] ?? 3 }} monitors</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Recent Monitors Table --}}
            <div class="lg:col-span-2 rounded-xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-900">Recent Monitors</h2>
                    <a href="{{ route('monitors.index') }}" class="text-sm font-medium text-emerald-600 hover:text-emerald-700">
                        View all →
                    </a>
                </div>
                @if ($recentMonitors->count() > 0)
                <div class="divide-y divide-gray-100">
                    @foreach ($recentMonitors->take(8) as $monitor)
                    <div class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="flex-shrink-0">
                                @if ($monitor->paused)
                                    <span class="flex h-2.5 w-2.5 rounded-full bg-gray-400"></span>
                                @elseif ($monitor->last_status === 'up')
                                    <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                @elseif ($monitor->last_status === 'down')
                                    <span class="relative flex h-2.5 w-2.5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                                    </span>
                                @elseif ($monitor->last_status === 'degraded')
                                    <span class="flex h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                @else
                                    <span class="flex h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $monitor->name }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ Str::limit($monitor->url, 40) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-right">
                            <div class="hidden sm:block">
                                @if ($monitor->last_response_time_ms)
                                    <p class="text-sm font-medium text-gray-900">{{ $monitor->last_response_time_ms }}ms</p>
                                @else
                                    <p class="text-sm text-gray-400">—</p>
                                @endif
                                <p class="text-xs text-gray-500">
                                    @if ($monitor->last_check_at)
                                        {{ \Carbon\Carbon::parse($monitor->last_check_at)->diffForHumans(short: true) }}
                                    @else
                                        Never
                                    @endif
                                </p>
                            </div>
                            <a href="{{ route('monitors.show', $monitor->id) }}" class="text-gray-400 hover:text-gray-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="px-5 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                    </svg>
                    <h3 class="mt-4 text-sm font-semibold text-gray-900">No monitors yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating your first monitor.</p>
                    <a href="{{ route('monitors.create') }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Create Monitor
                    </a>
                </div>
                @endif
            </div>

            {{-- Recent Incidents --}}
            <div class="rounded-xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-900">Recent Incidents</h2>
                    <a href="{{ route('incidents.index') }}" class="text-sm font-medium text-emerald-600 hover:text-emerald-700">
                        View all →
                    </a>
                </div>
                @if ($recentIncidents->count() > 0)
                <div class="divide-y divide-gray-100 max-h-[400px] overflow-y-auto">
                    @foreach ($recentIncidents->take(10) as $incident)
                    <div class="px-5 py-3">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 mt-0.5">
                                @if ($incident->recovered_at)
                                    <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                @else
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                    </span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $incident->monitor?->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ \Carbon\Carbon::parse($incident->started_at)->diffForHumans() }}
                                    @if ($incident->recovered_at)
                                        <span class="mx-1">•</span>
                                        <span class="text-emerald-600">Resolved</span>
                                    @else
                                        <span class="mx-1">•</span>
                                        <span class="text-red-600">Active</span>
                                    @endif
                                </p>
                                @if ($incident->cause)
                                    <p class="mt-1 text-xs text-gray-400 truncate">{{ $incident->cause }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="px-5 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-emerald-200" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-4 text-sm font-semibold text-gray-900">All clear!</h3>
                    <p class="mt-1 text-sm text-gray-500">No incidents recorded.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
