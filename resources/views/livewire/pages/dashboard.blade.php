<?php

use App\Models\Monitor;
use App\Models\Incident;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use function Livewire\Volt\{state, computed, layout, title};

layout('layouts.app');
title('Dashboard');

state([
    // 24h | 7d | 30d | custom
    'range' => '7d',
    'from' => null, // YYYY-MM-DD
    'to' => null,   // YYYY-MM-DD
]);

$rangeWindow = computed(function (): array {
    $now = Carbon::now();

    if ($this->range === '24h') {
        return [$now->copy()->subHours(24), $now];
    }

    if ($this->range === '30d') {
        return [$now->copy()->subDays(30)->startOfDay(), $now];
    }

    if ($this->range === 'custom') {
        $from = $this->from ? Carbon::parse($this->from)->startOfDay() : $now->copy()->subDays(7)->startOfDay();
        $to = $this->to ? Carbon::parse($this->to)->endOfDay() : $now;
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        return [$from, $to];
    }

    // default 7d
    return [$now->copy()->subDays(7)->startOfDay(), $now];
});

$plan = computed(function (): array {
    $u = auth()->user();

    $rawPlan =
        data_get($u, 'subscription.plan')
        ?? data_get($u, 'billing.plan')
        ?? data_get($u, 'plan')
        ?? null;

    $key = is_string($rawPlan) ? strtolower(trim($rawPlan)) : null;

    // Your known limits:
    $limits = [
        'free' => 1,
        'pro' => 5,
        'team' => 20,
    ];

    // Normalize unknowns to free
    if (!in_array($key, ['free', 'pro', 'team'], true)) {
        $key = 'free';
    }

    return [
        'key' => $key,
        'label' => ucfirst($key),
        'is_team' => $key === 'team',
        'monitor_limit' => $limits[$key] ?? 1,
        'pro_features' => in_array($key, ['pro', 'team'], true),
    ];
});

$scope = computed(function () {
    $u = auth()->user();
    $p = $this->plan;

    $teamId = $u?->currentTeam?->id;

    // Team plan => scope by team_id
    if ($p['is_team'] && $teamId) {
        return ['mode' => 'team', 'team_id' => $teamId, 'user_id' => $u?->id];
    }

    // Individual => scope by user_id
    return ['mode' => 'user', 'team_id' => null, 'user_id' => $u?->id];
});

$monitorIds = computed(function (): array {
    $u = auth()->user();
    if (!$u) return [];

    $s = $this->scope;

    $q = Monitor::query()->select('id');

    if ($s['mode'] === 'team') {
        $q->where('team_id', $s['team_id']);
    } else {
        $q->where('user_id', $s['user_id']);
    }

    return $q->pluck('id')->all();
});

$kpis = computed(function (): array {
    $u = auth()->user();
    if (!$u) {
        return [
            'total' => 0, 'up' => 0, 'down' => 0, 'paused' => 0,
            'open_incidents' => 0,
            'checks' => null,
            'avg_ms' => null,
            'uptime_pct' => null,
        ];
    }

    $s = $this->scope;
    [$from, $to] = $this->rangeWindow;

    $cacheKey = 'dash:kpis:v1:' . implode(':', [
        'u' . $u->id,
        $s['mode'] === 'team' ? ('t' . $s['team_id']) : 'solo',
        'r' . $this->range,
        'f' . $from->format('YmdHi'),
        't' . $to->format('YmdHi'),
    ]);

    return Cache::remember($cacheKey, 30, function () use ($s, $from, $to) {
        $monitors = Monitor::query();

        if ($s['mode'] === 'team') {
            $monitors->where('team_id', $s['team_id']);
        } else {
            $monitors->where('user_id', $s['user_id']);
        }

        $total = (clone $monitors)->count();
        $paused = (clone $monitors)->where('paused', true)->count();
        $up = (clone $monitors)->where('paused', false)->where('last_status', 'up')->count();
        $down = (clone $monitors)->where('paused', false)->where('last_status', 'down')->count();

        $ids = (clone $monitors)->pluck('id')->all();

        $openIncidents = 0;
        if (!empty($ids)) {
            $openIncidents = Incident::query()
                ->whereIn('monitor_id', $ids)
                ->whereNull('recovered_at')
                ->count();
        }

        // Optional: checks-based KPIs (only if you have a MonitorCheck model)
        $checks = null;
        $avgMs = null;
        $uptimePct = null;

        if (!empty($ids) && class_exists(\App\Models\MonitorCheck::class)) {
            $checkQ = \App\Models\MonitorCheck::query()
                ->whereIn('monitor_id', $ids)
                ->whereBetween('checked_at', [$from, $to]);

            $checks = (clone $checkQ)->count();

            $avgMs = (clone $checkQ)->whereNotNull('response_time_ms')->avg('response_time_ms');
            $avgMs = $avgMs !== null ? (int) round($avgMs) : null;

            $okCount = (clone $checkQ)->where('ok', true)->count();
            $uptimePct = $checks > 0 ? round(($okCount / $checks) * 100, 2) : null;
        }

        return [
            'total' => $total,
            'up' => $up,
            'down' => $down,
            'paused' => $paused,
            'open_incidents' => $openIncidents,
            'checks' => $checks,
            'avg_ms' => $avgMs,
            'uptime_pct' => $uptimePct,
        ];
    });
});

$uptimeSeries = computed(function (): array {
    $ids = $this->monitorIds;
    if (empty($ids) || !class_exists(\App\Models\MonitorCheck::class)) {
        return [];
    }

    [$from, $to] = $this->rangeWindow;

    // Limit series to daily bars (even for 24h we still show daily, keeps UI consistent)
    $start = $from->copy()->startOfDay();
    $end = $to->copy()->endOfDay();

    $rows = \App\Models\MonitorCheck::query()
        ->selectRaw('DATE(checked_at) as d, COUNT(*) as total, SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) as ok_count')
        ->whereIn('monitor_id', $ids)
        ->whereBetween('checked_at', [$start, $end])
        ->groupBy('d')
        ->orderBy('d')
        ->get()
        ->keyBy('d');

    $out = [];
    $cursor = $start->copy();

    while ($cursor->lte($end)) {
        $d = $cursor->toDateString();
        $row = $rows->get($d);

        $total = $row?->total ? (int) $row->total : 0;
        $ok = $row?->ok_count ? (int) $row->ok_count : 0;

        $pct = $total > 0 ? round(($ok / $total) * 100, 2) : null;

        $out[] = [
            'date' => $d,
            'label' => $cursor->format('M j'),
            'pct' => $pct,
        ];

        $cursor->addDay();
    }

    // Keep it readable: if range is huge, slice last 30 days
    if (count($out) > 30) {
        $out = array_slice($out, -30);
    }

    return $out;
});

$recentIncidents = computed(function (): array {
    $ids = $this->monitorIds;
    if (empty($ids)) return [];

    $rows = Incident::query()
        ->with(['monitor:id,name'])
        ->whereIn('monitor_id', $ids)
        ->orderByDesc('started_at')
        ->limit(10)
        ->get();

    return $rows->map(function ($i) {
        $started = $i->started_at ? Carbon::parse($i->started_at) : null;
        $recovered = $i->recovered_at ? Carbon::parse($i->recovered_at) : null;

        $duration = null;
        if ($started && $recovered) {
            $duration = $started->diffForHumans($recovered, true);
        }

        return [
            'id' => $i->id,
            'monitor' => $i->monitor?->name ?? ('Monitor #' . $i->monitor_id),
            'started_at' => $started?->toDayDateTimeString(),
            'recovered_at' => $recovered?->toDayDateTimeString(),
            'is_open' => $i->recovered_at === null,
            'duration' => $duration,
        ];
    })->all();
});

$usage = computed(function (): array {
    $p = $this->plan;
    $used = (int) ($this->kpis['total'] ?? 0);
    $limit = (int) ($p['monitor_limit'] ?? 1);

    $pct = $limit > 0 ? min(100, (int) round(($used / $limit) * 100)) : 0;

    return [
        'used' => $used,
        'limit' => $limit,
        'pct' => $pct,
        'over' => $used > $limit,
    ];
});

?>

@php
    $kpis = $this->kpis;
    $plan = $this->plan;
    $usage = $this->usage;
    $uptimeSeries = $this->uptimeSeries;
    $recentIncidents = $this->recentIncidents;
@endphp
<div class="space-y-6">
    {{-- Header row --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-600">
                Overview of uptime, incidents, and usage for your workspace.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1">
                <button wire:click="$set('range','24h')"
                        class="px-3 py-1.5 text-sm rounded-md {{ $range === '24h' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50' }}">
                    24h
                </button>
                <button wire:click="$set('range','7d')"
                        class="px-3 py-1.5 text-sm rounded-md {{ $range === '7d' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50' }}">
                    7d
                </button>
                <button wire:click="$set('range','30d')"
                        class="px-3 py-1.5 text-sm rounded-md {{ $range === '30d' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50' }}">
                    30d
                </button>
                <button wire:click="$set('range','custom')"
                        class="px-3 py-1.5 text-sm rounded-md {{ $range === 'custom' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50' }}">
                    Custom
                </button>
            </div>

            @if ($range === 'custom')
                <div class="flex items-center gap-2">
                    <input type="date" wire:model.live="from"
                           class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900">
                    <span class="text-sm text-slate-500">to</span>
                    <input type="date" wire:model.live="to"
                           class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900">
                </div>
            @endif
        </div>
    </div>

    {{-- Empty state --}}
    @if (($kpis['total'] ?? 0) === 0)
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-lg font-semibold text-slate-900">No monitors yet</div>
                    <div class="mt-1 text-sm text-slate-600">
                        Add your first monitor to start collecting uptime, response time, and incident history.
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @if (Route::has('monitors.index'))
                        <a href="{{ route('monitors.index') }}"
                           class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Add monitor
                        </a>
                    @endif
                    @if (Route::has('billing.index'))
                        <a href="{{ route('billing.index') }}"
                           class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Manage plan
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- KPIs --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-600">Total monitors</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format((int) ($kpis['total'] ?? 0)) }}</div>
            <div class="mt-2 text-xs text-slate-500">Includes paused monitors.</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-600">Up</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format((int) ($kpis['up'] ?? 0)) }}</div>
            <div class="mt-2 text-xs text-slate-500">Based on last known status.</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-600">Down</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format((int) ($kpis['down'] ?? 0)) }}</div>
            <div class="mt-2 text-xs text-slate-500">Excludes paused monitors.</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-600">Open incidents</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format((int) ($kpis['open_incidents'] ?? 0)) }}</div>
            <div class="mt-2 text-xs text-slate-500">Currently ongoing incidents.</div>
        </div>
    </div>

    {{-- Secondary KPIs + Usage --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-base font-semibold text-slate-900">Uptime</div>
                    <div class="mt-1 text-sm text-slate-600">
                        @if ($kpis['uptime_pct'] !== null)
                            {{ $kpis['uptime_pct'] }}% uptime ({{ number_format((int) $kpis['checks']) }} checks)
                        @else
                            <span class="text-slate-500">No check data available yet.</span>
                        @endif
                    </div>
                </div>

                <div class="text-right">
                    <div class="text-sm text-slate-600">Avg response</div>
                    <div class="mt-1 text-lg font-semibold text-slate-900">
                        @if ($kpis['avg_ms'] !== null)
                            {{ number_format((int) $kpis['avg_ms']) }} ms
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>

            {{-- Uptime bars (no external chart dependency) --}}
            @if (!empty($uptimeSeries))
                <div class="mt-5">
                    <div class="flex items-end gap-1 h-24">
                        @foreach ($uptimeSeries as $p)
                            @php
                                $val = $p['pct'];
                                $h = $val === null ? 6 : max(6, min(100, (int) round($val)));
                                $title = $p['label'] . ': ' . ($val === null ? '—' : ($val . '%'));
                            @endphp
                            <div class="flex-1" title="{{ $title }}">
                                <div class="w-full rounded-md bg-slate-100 overflow-hidden">
                                    <div class="w-full bg-slate-900/80" style="height: {{ $h }}px"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex justify-between text-[11px] text-slate-500">
                        <span>{{ $uptimeSeries[0]['label'] ?? '' }}</span>
                        <span>{{ $uptimeSeries[count($uptimeSeries)-1]['label'] ?? '' }}</span>
                    </div>
                </div>
            @else
                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                    Uptime chart will appear after checks start running.
                </div>
            @endif

            {{-- Pro/Team hint --}}
            @if (!$plan['pro_features'])
                <div class="mt-5 rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-sm font-semibold text-slate-900">Unlock deeper analytics</div>
                    <div class="mt-1 text-sm text-slate-600">
                        Upgrade to Pro to keep full history and get richer SLA reporting.
                    </div>
                    @if (Route::has('billing.index'))
                        <a href="{{ route('billing.index') }}" class="mt-3 inline-flex text-sm font-semibold text-slate-900 underline">
                            View plans
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-base font-semibold text-slate-900">Plan usage</div>
            <div class="mt-1 text-sm text-slate-600">
                {{ $plan['label'] }} plan
            </div>

            <div class="mt-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-600">Monitors</span>
                    <span class="font-semibold text-slate-900">{{ $usage['used'] }} / {{ $usage['limit'] }}</span>
                </div>
                <div class="mt-2 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-2 bg-slate-900" style="width: {{ $usage['pct'] }}%"></div>
                </div>
                @if ($usage['over'])
                    <div class="mt-2 text-xs text-rose-600">
                        You are over the limit. Upgrade or remove monitors to avoid enforcement.
                    </div>
                @else
                    <div class="mt-2 text-xs text-slate-500">
                        Usage updates in real time.
                    </div>
                @endif
            </div>

            <div class="mt-5 grid grid-cols-1 gap-2">
                @if (Route::has('monitors.index'))
                    <a href="{{ route('monitors.index') }}"
                       class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Manage monitors
                    </a>
                @endif

                @if (Route::has('public.status'))
                    <a href="{{ route('public.status') }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        View public status
                    </a>
                @endif

                @if (Route::has('billing.index'))
                    <a href="{{ route('billing.index') }}"
                       class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Billing
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Recent incidents --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <div>
                <div class="text-base font-semibold text-slate-900">Recent incidents</div>
                <div class="mt-1 text-sm text-slate-600">Latest incident activity across your monitors.</div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-6 py-3 text-left font-semibold">Monitor</th>
                    <th class="px-6 py-3 text-left font-semibold">Status</th>
                    <th class="px-6 py-3 text-left font-semibold">Started</th>
                    <th class="px-6 py-3 text-left font-semibold">Recovered</th>
                    <th class="px-6 py-3 text-left font-semibold">Duration</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                @forelse ($recentIncidents as $row)
                    <tr>
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $row['monitor'] }}</td>
                        <td class="px-6 py-3">
                            @if ($row['is_open'])
                                <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">Open</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Resolved</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-slate-700">{{ $row['started_at'] ?? '—' }}</td>
                        <td class="px-6 py-3 text-slate-700">{{ $row['recovered_at'] ?? ($row['is_open'] ? '—' : '—') }}</td>
                        <td class="px-6 py-3 text-slate-700">
                            {{ $row['is_open'] ? 'Ongoing' : ($row['duration'] ?? '—') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-6 text-center text-slate-600">
                            No incidents yet.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>