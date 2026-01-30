<?php

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Incidents Health')]
class extends Component
{
    public bool $loadError = false;

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function with(): array
    {
        $openIncidents = DB::table('incidents')->whereNull('recovered_at')->count();

        $flapping = DB::table('incidents')
            ->select('monitor_id', DB::raw('count(*) as total'))
            ->where('started_at', '>=', now()->subDay())
            ->groupBy('monitor_id')
            ->having('total', '>=', 3)
            ->get();

        $longIncidents = DB::table('incidents')
            ->whereNull('recovered_at')
            ->where('started_at', '<=', now()->subHours(6))
            ->orderBy('started_at')
            ->limit(10)
            ->get();

        $longIncidentRows = $longIncidents->map(function ($incident) {
            $monitor = Monitor::query()->find($incident->monitor_id);
            return [
                'monitor' => $monitor?->name ?? 'Monitor',
                'started_at' => $incident->started_at,
                'duration' => \Carbon\Carbon::parse($incident->started_at)->diffForHumans(),
            ];
        });

        return [
            'openIncidents' => $openIncidents,
            'flappingCount' => $flapping->count(),
            'longIncidentRows' => $longIncidentRows,
        ];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Incidents Health</h1>
        <p class="text-sm text-slate-600">Read-only system status. No customer edits.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">Open incidents</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $openIncidents }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">Flapping monitors (24h)</div>
            <div class="mt-2 text-2xl font-semibold text-amber-600">{{ $flappingCount }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">Long incidents (6h+)</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ $longIncidentRows->count() }}</div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="text-sm font-semibold text-slate-900">Long incidents (6h+)</div>
        <div class="mt-3 space-y-3 text-sm">
            @forelse($longIncidentRows as $row)
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-slate-900">{{ $row['monitor'] }}</div>
                        <div class="text-xs text-slate-500">Started: {{ \Carbon\Carbon::parse($row['started_at'])->toDateTimeString() }}</div>
                    </div>
                    <div class="text-rose-600">{{ $row['duration'] }}</div>
                </div>
            @empty
                <div class="text-sm text-slate-500">No long incidents.</div>
            @endforelse
        </div>
    </div>
</div>
