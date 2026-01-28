<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Incident;
use App\Models\Monitor;

new
#[Layout('layouts.app')]
class extends Component {
    use WithPagination;

    public $filter = 'all'; // all, open, resolved
    public $search = '';
    public $sortBy = 'started_at';
    public $sortDirection = 'desc';

    #[Computed]
    public function incidents()
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        // Get user's monitors
        $monitorIds = $team 
            ? $team->monitors()->pluck('id')
            : Monitor::where('user_id', $user->id)->pluck('id');

        $query = Incident::with('monitor')
            ->whereIn('monitor_id', $monitorIds)
            ->orderBy($this->sortBy, $this->sortDirection);

        // Apply filters
        if ($this->filter === 'open') {
            $query->whereNull('recovered_at');
        } elseif ($this->filter === 'resolved') {
            $query->whereNotNull('recovered_at');
        }

        // Search
        if ($this->search) {
            $query->whereHas('monitor', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('url', 'like', '%' . $this->search . '%');
            });
        }

        return $query->paginate(20);
    }

    #[Computed]
    public function stats()
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        $monitorIds = $team 
            ? $team->monitors()->pluck('id')
            : Monitor::where('user_id', $user->id)->pluck('id');

        $openCount = Incident::whereIn('monitor_id', $monitorIds)
            ->whereNull('recovered_at')
            ->count();

        $totalCount = Incident::whereIn('monitor_id', $monitorIds)->count();

        $last24h = Incident::whereIn('monitor_id', $monitorIds)
            ->where('started_at', '>=', now()->subDay())
            ->count();

        $avgDowntime = Incident::whereIn('monitor_id', $monitorIds)
            ->whereNotNull('recovered_at')
            ->avg('downtime_seconds');

        return [
            'open' => $openCount,
            'total' => $totalCount,
            'last24h' => $last24h,
            'avgDowntime' => $avgDowntime ? round($avgDowntime / 60, 1) : 0, // Convert to minutes
        ];
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }
    }

    public function formatDuration($seconds)
    {
        if (!$seconds) return 'Ongoing';
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($minutes > 0) {
            return "{$minutes}m {$secs}s";
        } else {
            return "{$secs}s";
        }
    }
}; ?>

<div>
    <x-slot name="breadcrumbs">
        <li class="flex items-center">
            <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
            <span class="ml-2 text-sm font-medium text-gray-700">Incidents</span>
        </li>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Incidents</h1>
            <p class="mt-2 text-sm text-gray-600">Monitor downtime and recovery events</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Open Incidents</p>
                        <p class="text-3xl font-bold text-red-600 mt-2">{{ $this->stats()['open'] }}</p>
                    </div>
                    <div class="h-12 w-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Total Incidents</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $this->stats()['total'] }}</p>
                    </div>
                    <div class="h-12 w-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <svg class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Last 24 Hours</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $this->stats()['last24h'] }}</p>
                    </div>
                    <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Avg Downtime</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $this->stats()['avgDowntime'] }}m</p>
                    </div>
                    <div class="h-12 w-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
            <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
                <!-- Filter Tabs -->
                <div class="flex gap-2">
                    <button 
                        wire:click="setFilter('all')"
                        class="{{ $filter === 'all' ? 'bg-emerald-100 text-emerald-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }} px-4 py-2 rounded-lg text-sm transition-colors"
                    >
                        All Incidents
                    </button>
                    <button 
                        wire:click="setFilter('open')"
                        class="{{ $filter === 'open' ? 'bg-red-100 text-red-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }} px-4 py-2 rounded-lg text-sm transition-colors"
                    >
                        Open
                    </button>
                    <button 
                        wire:click="setFilter('resolved')"
                        class="{{ $filter === 'resolved' ? 'bg-emerald-100 text-emerald-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }} px-4 py-2 rounded-lg text-sm transition-colors"
                    >
                        Resolved
                    </button>
                </div>

                <!-- Search -->
                <div class="relative w-full sm:w-64">
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search monitors..." 
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                    >
                    <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Incidents Table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" wire:click="sortBy('monitor_id')">
                                Monitor
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" wire:click="sortBy('started_at')">
                                Started
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Duration
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Recovered
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($this->incidents() as $incident)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($incident->is_open)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <span class="w-2 h-2 mr-1.5 bg-red-400 rounded-full animate-pulse"></span>
                                    Open
                                </span>
                                @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                    Resolved
                                </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="ml-0">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $incident->monitor->name }}
                                        </div>
                                        <div class="text-sm text-gray-500 truncate max-w-xs">
                                            {{ $incident->monitor->url }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $incident->started_at->format('M d, Y H:i') }}
                                <span class="text-xs text-gray-400 block">
                                    {{ $incident->started_at->diffForHumans() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $this->formatDuration($incident->downtime_seconds) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($incident->recovered_at)
                                    {{ $incident->recovered_at->format('M d, Y H:i') }}
                                    <span class="text-xs text-gray-400 block">
                                        {{ $incident->recovered_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-red-600 font-medium">In progress</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No incidents found</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    @if($filter === 'open')
                                        Great! All your monitors are running smoothly.
                                    @else
                                        Start monitoring URLs to track incidents.
                                    @endif
                                </p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($this->incidents()->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $this->incidents()->links() }}
            </div>
            @endif
        </div>
    </div>
</div>