<?php

use App\Models\Monitor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin • Monitors')]
class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = 'all'; // all|up|down|degraded
    public string $locked = 'all'; // all|yes|no

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('access-admin'), 403);
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingLocked(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'locked']);
        $this->resetPage();
    }

    public function with(): array
    {
        $monitors = Monitor::query()
            ->when($this->search, function ($qq) {
                $s = trim($this->search);
                $qq->where(function ($w) use ($s) {
                    $w->where('name', 'like', "%{$s}%")
                      ->orWhere('url', 'like', "%{$s}%")
                      ->orWhere('id', (int) $s);
                });
            })
            ->when($this->status !== 'all', fn ($qq) => $qq->where('last_status', $this->status))
            ->when($this->locked === 'yes', fn ($qq) => $qq->where('locked_by_plan', true))
            ->when($this->locked === 'no', fn ($qq) => $qq->where('locked_by_plan', false))
            ->orderByDesc('created_at')
            ->paginate(25);

        return compact('monitors');
    }
};
?>

<div class="space-y-6">
    <div class="sticky top-0 z-20 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-4 bg-white/80 backdrop-blur border-b border-slate-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xl font-semibold text-slate-900">Monitors</div>
                <div class="mt-1 text-sm text-slate-600">Global listing (including plan-locked monitors).</div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Overview</a>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
        <div class="flex items-center justify-between gap-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 flex-1">
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Search</label>
                    <input wire:model.live="search" class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900" placeholder="Name, url, id">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600">Status</label>
                    <select wire:model.live="status" class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900">
                        <option value="all">All</option>
                        <option value="up">Up</option>
                        <option value="down">Down</option>
                        <option value="degraded">Degraded</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600">Plan locked</label>
                    <select wire:model.live="locked" class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900">
                        <option value="all">All</option>
                        <option value="no">No</option>
                        <option value="yes">Yes</option>
                    </select>
                </div>
            </div>

            <button wire:click="clearFilters" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear</button>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div wire:loading.delay class="p-6">
            <div class="animate-pulse space-y-4">
                <div class="h-4 w-1/3 rounded bg-slate-200"></div>
                <div class="space-y-3">
                    @for ($i = 0; $i < 10; $i++)
                        <div class="h-10 rounded bg-slate-100 border border-slate-200"></div>
                    @endfor
                </div>
            </div>
        </div>

        <div wire:loading.remove>
            @if ($monitors->isEmpty())
                <div class="p-10">
                    <div class="mx-auto max-w-md text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16v16H4z"/><path d="M8 12h8"/><path d="M12 8v8"/>
                            </svg>
                        </div>
                        <div class="mt-4 text-sm font-semibold text-slate-900">No monitors found</div>
                        <div class="mt-1 text-sm text-slate-600">Try widening your filters.</div>
                        <div class="mt-6">
                            <button wire:click="clearFilters" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                                Clear filters
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Monitor</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Owner</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Paused</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Locked</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($monitors as $m)
                                @php
                                    $st = (string) ($m->last_status ?? '');
                                    $stClass = match($st) {
                                        'up' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
                                        'down' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
                                        'degraded' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
                                        default => 'bg-slate-50 text-slate-700 ring-1 ring-slate-200',
                                    };
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-slate-900">{{ $m->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $m->url }} · #{{ $m->id }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        user #{{ $m->user_id }} @if($m->team_id) · team #{{ $m->team_id }} @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $stClass }}">
                                            {{ $st ? strtoupper($st) : '—' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $m->paused ? 'Yes' : 'No' }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        @if ($m->locked_by_plan)
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-rose-50 text-rose-700 ring-1 ring-rose-200">Yes</span>
                                            <div class="mt-1 text-xs text-slate-500">{{ $m->locked_reason }}</div>
                                        @else
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-50 text-slate-700 ring-1 ring-slate-200">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 bg-white">
                    {{ $monitors->links() }}
                </div>
            @endif
        </div>
    </div>
</div>