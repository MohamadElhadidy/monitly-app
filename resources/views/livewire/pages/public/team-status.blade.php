<?php

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.public')]
#[Title('Status')]
class extends Component
{
    public Team $team;

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
        return null;
    }

    public function mount(string $slug): void
    {
        $team = Team::query()->where('slug', $slug)->firstOrFail();

        // Do not expose team status if not enabled
        abort_unless((bool) $team->public_status_enabled, 404);

        $this->team = $team;
    }

    public function with(): array
    {
        $ttlSeconds = 30;
        $teamId = (int) $this->team->id;

        $data = Cache::remember("public_status:team:{$teamId}:v1", $ttlSeconds, function () use ($teamId) {
            $monitors = Monitor::query()
                ->where('team_id', $teamId)
                ->where('is_public', true)
                ->select([
                    'id', 'team_id', 'user_id', 'name', 'url',
                    'public_show_url', 'is_public', 'paused', 'last_status',
                ])
                ->addSelect([
                    'last_closed_downtime_seconds' => Incident::query()
                        ->select('downtime_seconds')
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
                if ($status === 'down' && $m->openIncident?->started_at) {
                    $recentIncidentLabel = 'Down for ' . $this->humanDuration((int) $m->openIncident->started_at->diffInSeconds($now));
                } elseif (! is_null($m->last_closed_downtime_seconds)) {
                    $recentIncidentLabel = 'Last ' . $this->humanDuration((int) $m->last_closed_downtime_seconds);
                }

                return [
                    'id' => (int) $m->id,
                    'name' => (string) $m->name,
                    'status' => $status,
                    'last_checked_at' => $lastChecked ? $lastChecked->format('Y-m-d H:i') : null,
                    'response_time_ms' => $rtt ? (int) $rtt : null,
                    'recent_incident_label' => $recentIncidentLabel,
                    'endpoint_host' => $this->hostOnly((string) $m->url),
                    'public_show_url' => (bool) $m->public_show_url,
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
            'showUrls' => (bool) $this->team->public_status_show_urls,
        ];
    }
};
?>

<div class="space-y-6">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="text-xl font-semibold text-slate-900">{{ $team->name }} Status</div>
                <div class="mt-1 text-sm text-slate-600">
                    Team public status page. Endpoints are {{ $showUrls ? 'shown (host only) when enabled per monitor' : 'hidden' }}.
                </div>
                <div class="mt-2 text-xs text-slate-500">URL: /status/{{ $team->slug }}</div>
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
                This team has not published any monitors.
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
                                    @if ($showUrls && $r['public_show_url'] && $r['endpoint_host'])
                                        <span class="font-medium text-slate-900">{{ $r['endpoint_host'] }}</span>
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
                Team URL visibility requires BOTH:
                <span class="font-mono">teams.public_status_show_urls = 1</span> and <span class="font-mono">monitors.public_show_url = 1</span>.
                Host-only is shown (never the full URL).
            </div>
        </div>
    @endif
</div>