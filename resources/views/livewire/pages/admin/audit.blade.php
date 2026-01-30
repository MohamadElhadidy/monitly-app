<?php

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Audit Log')]
class extends Component
{
    public string $actionFilter = '';
    public string $targetFilter = '';
    public ?string $startDate = null;
    public ?string $endDate = null;

    public function with(): array
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        if ($this->actionFilter) {
            $query->where('action', $this->actionFilter);
        }

        if ($this->targetFilter) {
            $query->where('subject_id', $this->targetFilter);
        }

        if ($this->startDate) {
            $query->where('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->where('created_at', '<=', $this->endDate);
        }

        $logs = $query->limit(100)->get();
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return ['logs' => $logs, 'actions' => $actions];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Audit Log</h1>
        <p class="text-sm text-slate-600">Owner-only audit trail for admin actions.</p>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
        <div>
            <label class="text-xs font-semibold uppercase text-slate-500">Action</label>
            <select wire:model="actionFilter" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach($actions as $action)
                    <option value="{{ $action }}">{{ $action }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs font-semibold uppercase text-slate-500">Target ID</label>
            <input wire:model="targetFilter" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Target id" />
        </div>
        <div>
            <label class="text-xs font-semibold uppercase text-slate-500">Start date</label>
            <input type="date" wire:model="startDate" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
        </div>
        <div>
            <label class="text-xs font-semibold uppercase text-slate-500">End date</label>
            <input type="date" wire:model="endDate" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Actor</th>
                    <th class="px-4 py-3 text-left">Action</th>
                    <th class="px-4 py-3 text-left">Target</th>
                    <th class="px-4 py-3 text-left">Reason</th>
                    <th class="px-4 py-3 text-left">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($logs as $log)
                    <tr>
                        <td class="px-4 py-3">{{ $log->actor_type }} #{{ $log->actor_id }}</td>
                        <td class="px-4 py-3">{{ $log->action }}</td>
                        <td class="px-4 py-3">{{ $log->subject_type }} #{{ $log->subject_id }}</td>
                        <td class="px-4 py-3 text-xs">{{ $log->meta['reason'] ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $log->created_at?->toDateTimeString() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No audit logs found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
