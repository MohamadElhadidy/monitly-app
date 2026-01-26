<?php

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Admin • Audit Logs')]
class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $action = 'all';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('access-admin'), 403);
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingAction(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'action']);
        $this->resetPage();
    }

    public function with(): array
    {
        $q = AuditLog::query()
            ->when($this->action !== 'all', fn ($qq) => $qq->where('action', $this->action))
            ->when($this->search, function ($qq) {
                $s = trim($this->search);
                $qq->where(function ($w) use ($s) {
                    $w->where('action', 'like', "%{$s}%")
                      ->orWhere('actor_type', 'like', "%{$s}%")
                      ->orWhere('subject_type', 'like', "%{$s}%")
                      ->orWhere('ip', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('created_at');

        $logs = $q->paginate(30);

        $actions = AuditLog::query()
            ->select('action')
            ->groupBy('action')
            ->orderBy('action')
            ->pluck('action')
            ->all();

        return compact('logs', 'actions');
    }
};
?>

<div class="space-y-6">
    <div class="sticky top-0 z-20 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-4 bg-white/80 backdrop-blur border-b border-slate-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xl font-semibold text-slate-900">Audit Logs</div>
                <div class="mt-1 text-sm text-slate-600">High-signal audit trail for security + billing ops.</div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Overview</a>
                <a href="{{ route('admin.system') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">System</a>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
        <div class="flex items-center justify-between gap-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">
                <div>
                    <label class="block text-xs font-semibold text-slate-600">Search</label>
                    <input wire:model.live="search" class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900" placeholder="action, actor, subject, ip">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600">Action</label>
                    <select wire:model.live="action" class="mt-1 w-full rounded-lg border-slate-200 focus:border-slate-900 focus:ring-slate-900">
                        <option value="all">All</option>
                        @foreach ($actions as $a)
                            <option value="{{ $a }}">{{ $a }}</option>
                        @endforeach
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
            @if ($logs->isEmpty())
                <div class="p-10">
                    <div class="mx-auto max-w-md text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                                <path d="M8 13h8"/><path d="M8 17h8"/>
                            </svg>
                        </div>
                        <div class="mt-4 text-sm font-semibold text-slate-900">No audit logs found</div>
                        <div class="mt-1 text-sm text-slate-600">Try clearing filters or adjusting your search.</div>
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
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Actor</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600">Meta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($logs as $l)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $l->created_at?->format('Y-m-d H:i:s') }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ $l->action }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $l->actor_type }}{{ $l->actor_id ? ' #'.$l->actor_id : '' }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        {{ $l->subject_type ? class_basename($l->subject_type).' #'.$l->subject_id : '—' }}
                                        @if ($l->team_id)
                                            <div class="text-xs text-slate-500">team #{{ $l->team_id }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-xs text-slate-500">
                                        @if ($l->meta)
                                            <pre class="whitespace-pre-wrap break-words">{{ json_encode($l->meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 bg-white">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>