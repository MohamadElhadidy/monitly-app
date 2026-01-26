<?php

use App\Models\Incident;
use App\Models\Monitor;
use App\Rules\SafeMonitorUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Monitors')]
class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all'; // all|up|down|degraded|unknown
    public string $publicFilter = 'all'; // all|yes|no
    public string $pausedFilter = 'all'; // all|yes|no

    public bool $showModal = false;
    public string $modalMode = 'create'; // create|edit
    public ?int $editingId = null;

    // Form fields
    public string $name = '';
    public string $url = '';
    public bool $is_public = false;

    public ?string $toast = null;
    public ?string $toastError = null;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingPublicFilter(): void { $this->resetPage(); }
    public function updatingPausedFilter(): void { $this->resetPage(); }

    private function currentTeamOrNullForCreate(): mixed
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        // Personal-team context => individual account (team_id null)
        if (! $team || (bool) $team->personal_team) {
            return null;
        }

        return $team;
    }

    private function baseQuery(): Builder
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        if (! $team || (bool) $team->personal_team) {
            return Monitor::query()
                ->whereNull('team_id')
                ->where('user_id', $user->id);
        }

        $q = Monitor::query()->where('team_id', $team->id);

        if ($user->ownsTeam($team) || $user->hasTeamRole($team, 'admin')) {
            return $q;
        }

        return $q->whereHas('memberPermissions', function (Builder $sub) use ($user) {
            $sub->where('user_id', $user->id)
                ->where(function (Builder $x) {
                    $x->where('view_logs', true)
                      ->orWhere('receive_alerts', true)
                      ->orWhere('pause_resume', true)
                      ->orWhere('edit_settings', true);
                });
        });
    }

    private function applyFilters(Builder $q): Builder
    {
        return $q
            ->when($this->search !== '', function (Builder $qq) {
                $s = $this->search;
                $qq->where(function (Builder $x) use ($s) {
                    $x->where('name', 'like', "%{$s}%")
                      ->orWhere('url', 'like', "%{$s}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn (Builder $qq) => $qq->where('last_status', $this->statusFilter))
            ->when($this->publicFilter !== 'all', fn (Builder $qq) => $qq->where('is_public', $this->publicFilter === 'yes'))
            ->when($this->pausedFilter !== 'all', fn (Builder $qq) => $qq->where('paused', $this->pausedFilter === 'yes'));
    }

    private function computeUptime30dForPage(array $monitorIds): array
    {
        if (count($monitorIds) === 0) {
            return [];
        }

        $now = now();
        $windowStart = $now->copy()->subDays(30);
        $windowSeconds = max(1, $windowStart->diffInSeconds($now));

        $incidents = Incident::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('sla_counted', true)
            ->where('started_at', '<=', $now)
            ->where(function (Builder $q) use ($windowStart) {
                $q->whereNull('recovered_at')
                  ->orWhere('recovered_at', '>=', $windowStart);
            })
            ->get(['monitor_id', 'started_at', 'recovered_at']);

        $byMonitor = [];
        foreach ($monitorIds as $id) {
            $byMonitor[(int)$id] = 0;
        }

        foreach ($incidents as $inc) {
            $mId = (int) $inc->monitor_id;
            $start = $inc->started_at ? $inc->started_at->copy() : null;
            if (! $start) continue;

            $end = $inc->recovered_at ? $inc->recovered_at->copy() : $now->copy();

            $effectiveStart = $start->lessThan($windowStart) ? $windowStart->copy() : $start;
            $effectiveEnd = $end->greaterThan($now) ? $now->copy() : $end;

            if ($effectiveEnd->lessThanOrEqualTo($effectiveStart)) {
                continue;
            }

            $byMonitor[$mId] = ($byMonitor[$mId] ?? 0) + $effectiveStart->diffInSeconds($effectiveEnd);
        }

        $uptime = [];
        foreach ($byMonitor as $mId => $downSeconds) {
            $ratio = 1 - min(1, max(0, $downSeconds) / $windowSeconds);
            $uptime[$mId] = round($ratio * 100, 3);
        }

        return $uptime;
    }

    public function openCreate(): void
    {
        $this->toast = null;
        $this->toastError = null;

        $teamOrNull = $this->currentTeamOrNullForCreate();
        abort_unless(auth()->user()->can('create', [Monitor::class, $teamOrNull]), 403);

        $this->modalMode = 'create';
        $this->editingId = null;
        $this->name = '';
        $this->url = '';
        $this->is_public = false;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $monitorId): void
    {
        $this->toast = null;
        $this->toastError = null;

        $monitor = $this->baseQuery()->findOrFail($monitorId);
        abort_unless(auth()->user()->can('editSettings', $monitor), 403);

        $this->modalMode = 'edit';
        $this->editingId = $monitor->id;
        $this->name = $monitor->name;
        $this->url = $monitor->url;
        $this->is_public = (bool) $monitor->is_public;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(): void
    {
        $this->toast = null;
        $this->toastError = null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', new SafeMonitorUrl()],
            'is_public' => ['boolean'],
        ];

        $this->validate($rules);

        $team = auth()->user()->currentTeam;
        $teamOrNull = (! $team || (bool) $team->personal_team) ? null : $team;

        if ($this->modalMode === 'create') {
            abort_unless(auth()->user()->can('create', [Monitor::class, $teamOrNull]), 403);

            $monitor = Monitor::query()->create([
                'team_id' => $teamOrNull?->id,
                'user_id' => auth()->id(),
                'name' => trim($this->name),
                'url' => trim($this->url),
                'is_public' => (bool) $this->is_public,
                'paused' => false,
                'last_status' => 'unknown',
                'consecutive_failures' => 0,
                'next_check_at' => now()->addMinutes(5),
            ]);

            $this->toast = 'Monitor created.';
            $this->showModal = false;
            return;
        }

        // edit
        $monitor = $this->baseQuery()->findOrFail((int) $this->editingId);
        abort_unless(auth()->user()->can('editSettings', $monitor), 403);

        $monitor->update([
            'name' => trim($this->name),
            'url' => trim($this->url),
            'is_public' => (bool) $this->is_public,
        ]);

        $this->toast = 'Monitor updated.';
        $this->showModal = false;
    }

    public function togglePaused(int $monitorId): void
    {
        $monitor = $this->baseQuery()->findOrFail($monitorId);
        abort_unless(auth()->user()->can('pauseResume', $monitor), 403);

        $monitor->paused = ! $monitor->paused;
        $monitor->save();

        $this->toast = $monitor->paused ? 'Monitor paused.' : 'Monitor resumed.';
        $this->toastError = null;
    }

    public bool $showDeleteModal = false;
    public ?int $deletingId = null;

    public function confirmDelete(int $monitorId): void
    {
        $monitor = $this->baseQuery()->findOrFail($monitorId);
        abort_unless(auth()->user()->can('delete', $monitor), 403);

        $this->deletingId = $monitorId;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    public function delete(): void
    {
        if (!$this->deletingId) {
            return;
        }

        $monitor = $this->baseQuery()->findOrFail($this->deletingId);
        abort_unless(auth()->user()->can('delete', $monitor), 403);

        $monitorName = $monitor->name;
        $monitor->delete();

        $this->toast = "Monitor '{$monitorName}' deleted.";
        $this->toastError = null;
        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    public function with(): array
    {
        $monitors = $this->applyFilters(
                $this->baseQuery()
                    ->with([
    'latestCheck:monitor_checks.id,monitor_checks.monitor_id,monitor_checks.checked_at,monitor_checks.response_time_ms,monitor_checks.ok,monitor_checks.status_code,monitor_checks.error_code'
])

            )
            ->orderByDesc('created_at')
            ->paginate(20);

        $monitorIds = $monitors->getCollection()->pluck('id')->map(fn ($v) => (int) $v)->all();
        $uptimeMap = $this->computeUptime30dForPage($monitorIds);

        $rows = $monitors->getCollection()->map(function (Monitor $m) use ($uptimeMap) {
            $user = auth()->user();

            $last = $m->latestCheck;
            $lastCheckAt = $last?->checked_at?->format('Y-m-d H:i:s');
            $rtt = $last?->response_time_ms;

            return [
                'id' => (int) $m->id,
                'name' => $m->name,
                'url' => $m->url,
                'status' => $m->last_status,
                'paused' => (bool) $m->paused,
                'is_public' => (bool) $m->is_public,
                'last_check_at' => $lastCheckAt ?? '—',
                'response_time_ms' => $rtt ? ($rtt.' ms') : '—',
                'uptime_30d' => isset($uptimeMap[(int)$m->id]) ? number_format($uptimeMap[(int)$m->id], 3).'%' : '—',
                'can_edit' => $user->can('editSettings', $m),
                'can_pause' => $user->can('pauseResume', $m),
                'can_delete' => $user->can('delete', $m),
            ];
        })->all();

        $team = auth()->user()->currentTeam;
        $teamOrNull = (! $team || (bool) $team->personal_team) ? null : $team;
        $canCreate = auth()->user()->can('create', [Monitor::class, $teamOrNull]);

        return [
            'paginator' => $monitors,
            'rows' => $rows,
            'canCreate' => $canCreate,
        ];
    }
};
?>

<div class="space-y-6">
    @if ($toast)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 shadow-sm p-6">
            <div class="text-sm font-semibold text-emerald-800">Success</div>
            <div class="mt-1 text-sm text-emerald-700">{{ $toast }}</div>
        </div>
    @endif

    @if ($toastError)
        <div class="rounded-xl border border-rose-200 bg-rose-50 shadow-sm p-6">
            <div class="text-sm font-semibold text-rose-800">Error</div>
            <div class="mt-1 text-sm text-rose-700">{{ $toastError }}</div>
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm text-slate-600">
            Manage monitors, status visibility, and access.
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button
                :disabled="!$canCreate"
                wire:click="openCreate"
                class="{{ $canCreate ? '' : 'opacity-50 cursor-not-allowed' }}"
                title="{{ $canCreate ? 'Create a monitor' : 'You do not have permission to create monitors' }}"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none">
                    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Add monitor
            </x-ui.button>
        </div>
    </div>

    <x-ui.card title="Monitors" description="Search and filter monitors. Uptime is calculated over a rolling 30-day window.">
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                <input
                    type="text"
                    wire:model.live="search"
                    placeholder="Search name or URL…"
                    class="rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900 text-sm"
                />

                <select wire:model.live="statusFilter" class="rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900 text-sm">
                    <option value="all">All statuses</option>
                    <option value="up">Up</option>
                    <option value="down">Down</option>
                    <option value="degraded">Degraded</option>
                    <option value="unknown">Unknown</option>
                </select>

                <select wire:model.live="publicFilter" class="rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900 text-sm">
                    <option value="all">Public: All</option>
                    <option value="yes">Public only</option>
                    <option value="no">Private only</option>
                </select>

                <select wire:model.live="pausedFilter" class="rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900 text-sm">
                    <option value="all">Paused: All</option>
                    <option value="yes">Paused</option>
                    <option value="no">Active</option>
                </select>
            </div>
        </x-slot:actions>

        {{-- Loading skeleton --}}
        <div wire:loading class="space-y-3">
            @for ($i = 0; $i < 6; $i++)
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex-1 space-y-2">
                            <div class="h-4 w-48 bg-slate-100 rounded"></div>
                            <div class="h-3 w-96 bg-slate-100 rounded"></div>
                        </div>
                        <div class="h-8 w-28 bg-slate-100 rounded-lg"></div>
                    </div>
                </div>
            @endfor
        </div>

        <div wire:loading.remove>
            @if (count($rows) === 0)
                <x-ui.empty-state
                    title="Add your first monitor"
                    description="Create a monitor to start tracking uptime, incidents, and SLA."
                >
                    <x-slot:icon>
                        <svg class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none">
                            <path d="M13 2L3 14h7l-1 8 10-12h-7l1-8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                    </x-slot:icon>

                    <x-slot:actions>

                        <x-ui.button
                        type="button" 
                            :disabled="!$canCreate"
                            wire:click="openCreate"
                            class="{{ $canCreate ? '' : 'opacity-50 cursor-not-allowed' }}"
                        >
                            Add monitor
                        </x-ui.button>

                        <x-ui.button variant="secondary" wire:click="$set('statusFilter','all')">
                            Clear filters
                        </x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @else
                <x-ui.table>
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">URL</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Last check</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">RTT</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700">Uptime (30d)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-700">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200">
                        @foreach ($rows as $r)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-slate-900 flex items-center gap-2">
                                        {{ $r['name'] }}
                                        @if ($r['paused'])
                                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset bg-slate-50 text-slate-700 ring-slate-200">
                                                PAUSED
                                            </span>
                                        @endif
                                        @if ($r['is_public'])
                                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset bg-slate-50 text-slate-700 ring-slate-200">
                                                PUBLIC
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-sm text-slate-600 break-all">
                                    {{ $r['url'] }}
                                </td>

                                <td class="px-4 py-3 text-sm whitespace-nowrap">
                                    <x-ui.badge :variant="$r['status']">{{ strtoupper($r['status']) }}</x-ui.badge>
                                </td>

                                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">
                                    {{ $r['last_check_at'] }}
                                </td>

                                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">
                                    {{ $r['response_time_ms'] }}
                                </td>

                                <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">
                                    {{ $r['uptime_30d'] }}
                                </td>

                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <a
                                            href="{{ route('monitors.show', $r['id']) }}"
                                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            View
                                        </a>

                                        <button
                                            type="button"
                                            wire:click="openEdit({{ $r['id'] }})"
                                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 {{ $r['can_edit'] ? '' : 'opacity-50 cursor-not-allowed' }}"
                                            @if (! $r['can_edit']) disabled @endif
                                            title="{{ $r['can_edit'] ? 'Edit settings' : 'No permission to edit settings' }}"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="togglePaused({{ $r['id'] }})"
                                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 {{ $r['can_pause'] ? '' : 'opacity-50 cursor-not-allowed' }}"
                                            @if (! $r['can_pause']) disabled @endif
                                            title="{{ $r['can_pause'] ? 'Pause/resume checks' : 'No permission to pause/resume' }}"
                                        >
                                            {{ $r['paused'] ? 'Resume' : 'Pause' }}
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $r['id'] }})"
                                            class="rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 {{ $r['can_delete'] ? '' : 'opacity-50 cursor-not-allowed' }}"
                                            @if (! $r['can_delete']) disabled @endif
                                            title="{{ $r['can_delete'] ? 'Delete monitor' : 'No permission to delete' }}"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>

                <div class="mt-4">
                    {{ $paginator->links() }}
                </div>
            @endif
        </div>
    </x-ui.card>

    {{-- Modal --}}
<div
    x-cloak
    x-show="$wire.showModal"
    x-transition.opacity
    style="display: none;"
    class="fixed inset-0 z-50"
>

        
        
<div class="absolute inset-0 bg-slate-900/50" @click="open = false; $wire.closeModal()"></div>

        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-xl rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="p-6 flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xl font-semibold text-slate-900">
                            {{ $modalMode === 'create' ? 'Add monitor' : 'Edit monitor' }}
                        </div>
                        <div class="mt-1 text-sm text-slate-600">
                            URLs must be http/https and cannot resolve to private networks.
                        </div>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        @click="open = false; $wire.closeModal()"
                    >
                        Close
                    </button>
                    

                </div>

                <div class="border-t border-slate-200"></div>

                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Name</label>
                        <input
                            type="text"
                            wire:model.defer="name"
                            class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900 text-sm"
                            placeholder="e.g. Marketing Website"
                        />
                        @error('name')
                            <div class="mt-1 text-sm text-rose-700">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">URL</label>
                        <input
                            type="text"
                            wire:model.defer="url"
                            class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900 text-sm"
                            placeholder="https://example.com/health"
                        />
                        @error('url')
                            <div class="mt-1 text-sm text-rose-700">{{ $message }}</div>
                        @enderror
                        <div class="mt-1 text-xs text-slate-500">
                            Tip: Avoid internal hostnames, localhost, and private IPs.
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="is_public"
                            type="checkbox"
                            wire:model.defer="is_public"
                            class="rounded border-slate-200 focus:border-slate-900 focus:ring-slate-900"
                        />
                        <label for="is_public" class="text-sm text-slate-700">
                            Show on public status page
                        </label>
                    </div>
                </div>

                <div class="border-t border-slate-200"></div>

                <div class="p-6 flex flex-wrap items-center justify-end gap-2">
                    <x-ui.button variant="secondary" wire:click="closeModal">
                        Cancel
                    </x-ui.button>

                    <x-ui.button wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">
                            {{ $modalMode === 'create' ? 'Create' : 'Save changes' }}
                        </span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div
        x-cloak
        x-show="$wire.showDeleteModal"
        x-transition.opacity
        style="display: none;"
        class="fixed inset-0 z-50"
    >
        <div class="absolute inset-0 bg-slate-900/50" @click="$wire.cancelDelete()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white shadow-lg">
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Delete Monitor</h3>
                            <p class="text-sm text-slate-600">This action cannot be undone.</p>
                        </div>
                    </div>
                    <p class="text-sm text-slate-700 mb-6">
                        Are you sure you want to delete this monitor? All associated checks, incidents, and SLA data will be permanently deleted.
                    </p>
                    <div class="flex items-center justify-end gap-3">
                        <x-ui.button variant="secondary" wire:click="cancelDelete">
                            Cancel
                        </x-ui.button>
                        <button
                            type="button"
                            wire:click="delete"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                        >
                            <span wire:loading.remove wire:target="delete">Delete Monitor</span>
                            <span wire:loading wire:target="delete">Deleting…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
