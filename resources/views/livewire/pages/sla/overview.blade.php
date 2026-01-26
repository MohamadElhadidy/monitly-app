<?php

use App\Models\Monitor;
use App\Services\Sla\SlaCalculator;
use App\Services\Sla\SlaTargetResolver;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('SLA Overview')]
class extends Component
{
    public string $search = '';
    public bool $breachedOnly = false;

    public ?string $flashError = null;

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

    public function with(): array
    {
        $user = auth()->user();

        $team = $user->currentTeam;
        $isTeamAccount = $team && ! $team->personal_team && strtolower((string) ($team->billing_plan ?? '')) === 'team';

        $query = Monitor::query()->orderBy('name');

        if ($isTeamAccount) {
            $query->where('team_id', $team->id);
        } else {
            $query->whereNull('team_id')->where('user_id', $user->id);
        }

        if ($this->search) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('url', 'like', "%{$s}%");
            });
        }

        $monitors = $query->get();

        /** @var SlaCalculator $calc */
        $calc = app(SlaCalculator::class);
        /** @var SlaTargetResolver $targets */
        $targets = app(SlaTargetResolver::class);

        $batch = $calc->forMonitors($monitors, now(), 30);

        $target = $isTeamAccount
            ? 99.9
            : $targets->targetPctForUser($user);

        $per = $batch['per_monitor'];
        $rows = [];

        foreach ($monitors as $m) {
            $s = $per[$m->id] ?? null;
            if (! $s) continue;

            $breach = ((float)$s['uptime_pct']) < $target;

            if ($this->breachedOnly && ! $breach) continue;

            $rows[] = [
                'id' => (int) $m->id,
                'name' => (string) $m->name,
                'url' => (string) $m->url,
                'status' => (string) ($m->last_status ?? 'unknown'),
                'uptime_pct' => (float) $s['uptime_pct'],
                'downtime_seconds' => (int) $s['downtime_seconds'],
                'incident_count' => (int) $s['incident_count'],
                'mttr_seconds' => $s['mttr_seconds'],
                'breach' => $breach,
            ];
        }

        $agg = $batch['aggregate'];
        $aggBreach = ((float)$agg['uptime_pct']) < $target;

        return [
            'isTeamAccount' => $isTeamAccount,
            'teamName' => $isTeamAccount ? (string) $team->name : null,
            'target' => $target,
            'aggregate' => $agg + ['breach' => $aggBreach],
            'rows' => $rows,
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">SLA Overview</h1>
            <p class="mt-1 text-sm text-slate-600">
                Rolling 30-day SLA based on real incident downtime durations.
                @if ($isTeamAccount)
                    Scope: <span class="font-medium text-slate-900">{{ $teamName }}</span>
                @else
                    Scope: <span class="font-medium text-slate-900">Individual</span>
                @endif
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
            <div class="w-full sm:w-80">
                <input type="text"
                       wire:model.live="search"
                       placeholder="Search monitors…"
                       class="w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900">
            </div>

            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <input type="checkbox" wire:model.live="breachedOnly"
                       class="rounded border-slate-200 focus:border-slate-900 focus:ring-slate-900">
                Breached only
            </label>
        </div>
    </div>

    <x-ui.card title="Account SLA (weighted)" description="Aggregated uptime across monitors, weighted by time window per monitor.">
        @php
            $uptime = number_format((float)($aggregate['uptime_pct'] ?? 0), 4);
            $targetFmt = number_format((float)$target, 1);
            $downtime = $this->humanDuration((int)($aggregate['downtime_seconds'] ?? 0));
            $inc = (int)($aggregate['incident_count'] ?? 0);
            $mttr = $aggregate['mttr_seconds'] ? $this->humanDuration((int)$aggregate['mttr_seconds']) : '—';
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="text-xs text-slate-500">Uptime (30d)</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ $uptime }}%</div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="text-xs text-slate-500">Target</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ $targetFmt }}%</div>
                <div class="mt-2">
                    @if ($aggregate['breach'])
                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset bg-rose-50 text-rose-700 ring-rose-200">BREACHED</span>
                    @else
                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-200">OK</span>
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="text-xs text-slate-500">Total downtime</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ $downtime }}</div>
                <div class="mt-1 text-sm text-slate-600">Counted incidents only</div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="text-xs text-slate-500">Incidents / MTTR</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ $inc }}</div>
                <div class="mt-1 text-sm text-slate-600">MTTR: {{ $mttr }}</div>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Monitors" description="Per-monitor SLA metrics (rolling 30 days).">
        <div wire:loading class="space-y-3">
            @for ($i=0; $i<6; $i++)
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="h-4 w-80 bg-slate-100 rounded"></div>
                    <div class="mt-2 h-3 w-96 bg-slate-100 rounded"></div>
                </div>
            @endfor
        </div>

        <div wire:loading.remove>
            @if (count($rows) === 0)
                <x-ui.empty-state title="No monitors found" description="Try clearing filters or create monitors first.">
                    <x-slot:icon>
                        <svg class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none">
                            <path d="M4 5h16v14H4V5Z" stroke="currentColor" stroke-width="2"/>
                            <path d="M7 9h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M7 13h7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </x-slot:icon>
                </x-ui.empty-state>
            @else
                <x-ui.table>
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Monitor</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Uptime</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Downtime</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Incidents</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">MTTR</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-700">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($rows as $r)
                            @php
                                $u = number_format((float)$r['uptime_pct'], 4);
                                $d = $this->humanDuration((int)$r['downtime_seconds']);
                                $mt = $r['mttr_seconds'] ? $this->humanDuration((int)$r['mttr_seconds']) : '—';
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-slate-900">{{ $r['name'] }}</div>
                                    <div class="text-sm text-slate-600 break-all">{{ $r['url'] }}</div>
                                    @if ($r['breach'])
                                        <div class="mt-2">
                                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset bg-rose-50 text-rose-700 ring-rose-200">BREACHED</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <x-ui.badge :variant="$r['status']">{{ strtoupper($r['status']) }}</x-ui.badge>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-900">{{ $u }}%</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $d }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ (int)$r['incident_count'] }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $mt }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('monitors.show', $r['id']) }}"
                                       class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </div>
    </x-ui.card>
</div>
