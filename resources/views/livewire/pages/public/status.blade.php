<?php

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.public')]
#[Title('Status')]
class extends Component
{
    private function humanDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds === 0) return '0s';

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        $parts = [];
        if ($h > 0) $parts[] = "{$h}h";
        if ($m > 0) $parts[] = "{$m}m";
        if ($s > 0) $parts[] = "{$s}s";

        return implode(' ', $parts);
    }

    private function hostOnly(?string $url): ?string
    {
        if (! $url) return null;
        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') return $host;

        // Fallback: never leak full URL; return null instead
        return null;
    }

    public function with(): array
    {
        $ttlSeconds = 30;

        $data = Cache::remember('public_status:global:v1', $ttlSeconds, function () {
            $monitors = Monitor::query()
                ->where('is_public', true)
                ->select([
                    'id', 'team_id', 'user_id', 'name', 'url',
                    'public_show_url', 'is_public', 'paused', 'last_status',
                ])
                ->addSelect([
                    // Latest closed incident downtime (in seconds), if any
                    'last_closed_downtime_seconds' => Incident::query()
                        ->select('downtime_seconds')
                        ->whereColumn('monitor_id', 'monitors.id')
                        ->whereNotNull('recovered_at')
                        ->orderByDesc('recovered_at')
                        ->limit(1),
                    'last_closed_recovered_at' => Incident::query()
                        ->select('recovered_at')
                        ->whereColumn('monitor_id', 'monitors.id')
                        ->whereNotNull('recovered_at')
                        ->orderByDesc('recovered_at')
                        ->limit(1),
                ])
                ->with([
                    'latestCheck:id,monitor_checks.monitor_id,checked_at,response_time_ms,ok',
                    'openIncident:id,incidents.monitor_id,started_at,recovered_at',
                ])
                ->orderBy('name')
                ->get();

            $now = now();

            $rows = $monitors->map(function ($m) use ($now) {
                $status = $m->paused ? 'degraded' : (string) $m->last_status;

                $lastChecked = $m->latestCheck?->checked_at;
                $rtt = $m->latestCheck?->response_time_ms;

                $recentIncidentLabel = '—';
                $recentIncidentSeconds = null;

                if ($status === 'down' && $m->openIncident?->started_at) {
                    $recentIncidentSeconds = (int) $m->openIncident->started_at->diffInSeconds($now);
                    $recentIncidentLabel = 'Down for ' . $this->humanDuration($recentIncidentSeconds);
                } elseif (! is_null($m->last_closed_downtime_seconds)) {
                    $recentIncidentSeconds = (int) $m->last_closed_downtime_seconds;
                    $recentIncidentLabel = 'Last ' . $this->humanDuration($recentIncidentSeconds);
                }

                $endpoint = null;
                if ((bool) $m->public_show_url) {
                    $endpoint = $this->hostOnly((string) $m->url);
                }

                return [
                    'id' => (int) $m->id,
                    'name' => (string) $m->name,
                    'status' => $status, // up/down/degraded
                    'last_checked_at' => $lastChecked ? $lastChecked->format('Y-m-d H:i') : null,
                    'response_time_ms' => $rtt ? (int) $rtt : null,
                    'recent_incident_label' => $recentIncidentLabel,
                    'endpoint' => $endpoint, // host-only or null
                ];
            })->values()->all();

            $counts = [
                'total' => count($rows),
                'up' => collect($rows)->where('status', 'up')->count(),
                'down' => collect($rows)->where('status', 'down')->count(),
                'degraded' => collect($rows)->where('status', 'degraded')->count(),
            ];

            return [
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'rows' => $rows,
                'counts' => $counts,
            ];
        });

        return [
            'generatedAt' => $data['generated_at'],
            'rows' => $data['rows'],
            'counts' => $data['counts'],
        ];
    }
};
?>

<div class="space-y-6">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="text-xl font-semibold text-slate-900">Global Status</div>
                <div class="mt-1 text-sm text-slate-600">
                    Showing monitors explicitly marked as public. Endpoints are hidden unless enabled per monitor.
                </div>
            </div>

            <div class="text-sm text-slate-600">
                <span class="font-medium text-slate-900">{{ $counts['up'] }}</span> Up ·
                <span class="font-medium text-slate-900">{{ $counts['down'] }}</span> Down ·
                <span class="font-medium text-slate-900">{{ $counts['degraded'] }}</span> Degraded
                <div class="mt-1 text-xs text-slate-500">Cached at: {{ $generatedAt }}</div>
            </div>
        </div>
    </div>

    @if (count($rows) === 0)
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
            <div class="text-sm font-semibold text-slate-900">No public monitors</div>
            <div class="mt-1 text-sm text-slate-600">
                Nothing is published yet.
            </div>
        </div>
    @else
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Monitor</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Last checked</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">RTT</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Recent incident</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Endpoint</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach ($rows as $r)
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-semibold text-slate-900">{{ $r['name'] }}</div>
                                    <div class="text-xs text-slate-500">ID: {{ $r['id'] }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <x-ui.badge :variant="$r['status']">{{ strtoupper($r['status']) }}</x-ui.badge>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ $r['last_checked_at'] ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ $r['response_time_ms'] ? ($r['response_time_ms'].' ms') : '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ $r['recent_incident_label'] }}
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    @if ($r['endpoint'])
                                        <span class="font-medium text-slate-900">{{ $r['endpoint'] }}</span>
                                    @else
                                        <span class="text-slate-500">Hidden</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 bg-slate-50 text-xs text-slate-500">
                Tip: To enable endpoint display, set <span class="font-mono">monitors.public_show_url = 1</span> for a public monitor.
            </div>
        </div>
    @endif
</div>