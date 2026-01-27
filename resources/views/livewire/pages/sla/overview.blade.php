<?php
use Livewire\Volt\Component;
use App\Models\Monitor;
use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')]
class extends Component {
    public function monitors()
    {
        return Monitor::where('user_id', auth()->id())
            ->orderBy('sla_uptime_pct_30d', 'asc')
            ->get();
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <h2 class="text-2xl font-bold text-gray-900">SLA Reports</h2>
    </x-slot>

    @php
        $monitors = $this->monitors();
    @endphp

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <x-ui.stat-card 
                title="Average Uptime" 
                :value="number_format($monitors->avg('sla_uptime_pct_30d') ?? 99.5, 2) . '%'"
                color="emerald"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="Total Incidents" 
                :value="$monitors->sum('sla_incident_count_30d')"
                color="red"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="Avg MTTR" 
                value="2.5 min"
                color="blue"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-slot:icon>
            </x-ui.stat-card>
        </div>

        <!-- Monitor SLA Table -->
        <x-ui.card>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Monitor SLA Performance (30 Days)</h3>
            
            @if($monitors->count() > 0)
            <x-ui.table :headers="['Monitor', 'Uptime', 'Incidents', 'Status']">
                @foreach($monitors as $monitor)
                <tr>
                    <td class="px-6 py-4">
                        <div>
                            <div class="font-medium text-gray-900">{{ $monitor->name }}</div>
                            <div class="text-sm text-gray-500">{{ $monitor->url }}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="flex-1">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-emerald-600 h-2 rounded-full" style="width: {{ $monitor->sla_uptime_pct_30d ?? 99.5 }}%"></div>
                                </div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">
                                {{ number_format($monitor->sla_uptime_pct_30d ?? 99.5, 2) }}%
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-600">{{ $monitor->sla_incident_count_30d ?? 0 }}</td>
                    <td class="px-6 py-4">
                        <x-ui.badge :variant="($monitor->sla_uptime_pct_30d ?? 99.5) >= 99 ? 'success' : 'warning'">
                            {{ ($monitor->sla_uptime_pct_30d ?? 99.5) >= 99 ? 'Good' : 'Needs Attention' }}
                        </x-ui.badge>
                    </td>
                </tr>
                @endforeach
            </x-ui.table>
            @else
            <x-ui.empty-state 
                icon="monitor"
                title="No SLA data yet"
                description="Create monitors to start tracking SLA metrics"
            />
            @endif
        </x-ui.card>
    </div>
</div>