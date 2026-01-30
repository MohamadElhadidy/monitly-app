<?php

use App\Models\AppError;
use App\Services\Admin\AdminSettingsService;
use App\Services\Audit\Audit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Errors')]
class extends Component
{
    public bool $loadError = false;
    public ?int $selectedId = null;
    public ?string $selectedAction = null;
    public string $reason = '';

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function exportCsv()
    {
        $rows = AppError::query()->orderByDesc('last_seen_at')->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['fingerprint', 'message', 'count', 'last_seen_at', 'route_or_queue']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row->fingerprint, $row->message, $row->count, $row->last_seen_at, $row->route_or_queue]);
            }
            fclose($handle);
        }, 'errors.csv');
    }

    public function confirmAction(string $action, int $id): void
    {
        $this->selectedAction = $action;
        $this->selectedId = $id;
    }

    public function performAction(AdminSettingsService $settings): void
    {
        if ($settings->getSettings()->read_only_mode) {
            $this->addError('reason', 'Read-only mode is enabled.');
            return;
        }

        $this->validate([
            'reason' => 'required|string|min:3',
        ]);

        $error = AppError::query()->findOrFail($this->selectedId);

        if ($this->selectedAction === 'ack') {
            $error->update(['acknowledged_at' => now()]);
            Audit::log('admin.error.acknowledged', $error, null, ['reason' => $this->reason]);
        }

        if ($this->selectedAction === 'mute') {
            $error->update(['muted_until' => now()->addDay()]);
            Audit::log('admin.error.muted', $error, null, ['reason' => $this->reason]);
        }

        $this->reset(['selectedId', 'selectedAction', 'reason']);
        session()->flash('status', 'Error action applied.');
    }

    public function with(): array
    {
        $errors = AppError::query()->orderByDesc('last_seen_at')->limit(50)->get();
        return ['errors' => $errors];
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Errors</h1>
            <p class="text-sm text-slate-600">Grouped errors by fingerprint with controls.</p>
        </div>
        <button wire:click="exportCsv" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Export CSV</button>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Fingerprint</th>
                    <th class="px-4 py-3 text-left">Count</th>
                    <th class="px-4 py-3 text-left">Last Seen</th>
                    <th class="px-4 py-3 text-left">Route/Queue</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($errors as $error)
                    <tr>
                        <td class="px-4 py-3 text-xs">{{ $error->fingerprint }}</td>
                        <td class="px-4 py-3">{{ $error->count }}</td>
                        <td class="px-4 py-3">{{ $error->last_seen_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3">{{ $error->route_or_queue ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3 text-xs">
                                <button wire:click="confirmAction('ack', {{ $error->id }})" class="text-emerald-700 hover:underline">Acknowledge</button>
                                <button wire:click="confirmAction('mute', {{ $error->id }})" class="text-slate-700 hover:underline">Mute 24h</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No errors logged.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($selectedAction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900">Confirm action</h2>
                <p class="mt-2 text-sm text-slate-600">
                    @if($selectedAction === 'ack')
                        Acknowledge this error group?
                    @else
                        Mute this error group for 24 hours?
                    @endif
                </p>
                <div class="mt-4">
                    <label class="text-xs font-semibold uppercase text-slate-500">Reason</label>
                    <textarea wire:model.defer="reason" class="mt-2 w-full rounded-lg border border-slate-200 p-2 text-sm" rows="3" placeholder="Reason required"></textarea>
                    @error('reason') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('selectedAction', null)" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button wire:click="performAction" class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Confirm</button>
                </div>
            </div>
        </div>
    @endif
</div>
