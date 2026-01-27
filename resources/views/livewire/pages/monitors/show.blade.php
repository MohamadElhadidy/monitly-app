<?php
use Livewire\Volt\Component;
use App\Models\Monitor;
use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')]
class extends Component {
    public Monitor $monitor;
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-2xl font-bold text-gray-900">{{ $monitor->name }}</h2>
            <div class="flex gap-2">
                <x-ui.button href="{{ route('monitors.edit', $monitor) }}" variant="secondary" size="sm">
                    Edit
                </x-ui.button>
                <x-ui.button variant="danger" size="sm">
                    Delete
                </x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        <!-- Status Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <x-ui.stat-card 
                title="Current Status" 
                :value="ucfirst($monitor->last_status)"
                :color="$monitor->last_status === 'up' ? 'emerald' : 'red'"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="30d Uptime" 
                :value="number_format($monitor->sla_uptime_pct_30d ?? 99.5, 2) . '%'"
                color="blue"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="Incidents" 
                :value="$monitor->sla_incident_count_30d ?? 0"
                color="yellow"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </x-slot:icon>
            </x-ui.stat-card>

            <x-ui.stat-card 
                title="Check Interval" 
                value="Every 10 min"
                color="gray"
            >
                <x-slot:icon>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-slot:icon>
            </x-ui.stat-card>
        </div>

        <!-- Monitor Details -->
        <x-ui.card class="mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Monitor Details</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500">URL</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $monitor->url }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Created</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $monitor->created_at->format('M j, Y') }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <!-- Recent Checks -->
        <x-ui.card>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Checks</h3>
            <x-ui.table :headers="['Time', 'Status', 'Response Time']">
                @foreach($monitor->checks()->latest()->limit(10)->get() as $check)
                <tr>
                    <td class="px-6 py-4">{{ $check->checked_at->format('M j, Y H:i') }}</td>
                    <td class="px-6 py-4">
                        <x-ui.badge :variant="$check->ok ? 'success' : 'danger'">
                            {{ $check->ok ? 'Up' : 'Down' }}
                        </x-ui.badge>
                    </td>
                    <td class="px-6 py-4">{{ $check->response_time_ms }}ms</td>
                </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    </div>
</div>