<?php
use Livewire\Volt\Component;
use App\Models\Monitor;
use Livewire\Attributes\Layout;

new
#[Layout('layouts.app')]
class extends Component {
    public function monitors()
    {
        return Monitor::where('user_id', auth()->id())->latest()->get();
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-2xl font-bold text-gray-900">Monitors</h2>
            <x-ui.button href="{{ route('monitors.create') }}" variant="primary">
                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create Monitor
            </x-ui.button>
        </div>
    </x-slot>

    @php
        $monitors = $this->monitors();
    @endphp

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        @if($monitors->count() > 0)
        <x-ui.table :headers="['Name', 'URL', 'Status', 'Last Check', 'Actions']">
            @foreach($monitors as $monitor)
            <tr>
                <td class="px-6 py-4 font-medium text-gray-900">{{ $monitor->name }}</td>
                <td class="px-6 py-4 text-gray-600">{{ Str::limit($monitor->url, 50) }}</td>
                <td class="px-6 py-4">
                    <x-ui.badge :variant="$monitor->last_status === 'up' ? 'success' : 'danger'">
                        {{ $monitor->last_status }}
                    </x-ui.badge>
                </td>
                <td class="px-6 py-4 text-gray-600">
                    {{ $monitor->updated_at->diffForHumans() }}
                </td>
                <td class="px-6 py-4">
                    <x-ui.button href="{{ route('monitors.show', $monitor) }}" variant="secondary" size="sm">
                        View
                    </x-ui.button>
                </td>
            </tr>
            @endforeach
        </x-ui.table>
        @else
        <x-ui.empty-state 
            icon="monitor"
            title="No monitors yet"
            description="Create your first monitor to start tracking uptime"
        >
            <x-slot:action>
                <x-ui.button href="{{ route('monitors.create') }}" variant="primary">
                    Create Monitor
                </x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
        @endif
    </div>
</div>