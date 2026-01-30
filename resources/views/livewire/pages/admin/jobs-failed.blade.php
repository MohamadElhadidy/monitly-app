<?php

use App\Models\FailedJobNote;
use App\Services\Admin\AdminSettingsService;
use App\Services\Audit\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Failed Jobs')]
class extends Component
{
    public bool $loadError = false;
    public ?int $selectedId = null;
    public ?string $selectedAction = null;
    public string $reason = '';
    public array $selected = [];

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function confirmAction(string $action, int $id = null): void
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

        if ($this->selectedAction === 'retry') {
            Artisan::call('queue:retry', ['id' => $this->selectedId]);
            Audit::log('admin.failed_job.retry', null, null, ['failed_job_id' => $this->selectedId, 'reason' => $this->reason]);
        }

        if ($this->selectedAction === 'retry_selected') {
            foreach ($this->selected as $id) {
                Artisan::call('queue:retry', ['id' => $id]);
            }
            Audit::log('admin.failed_job.retry_selected', null, null, ['failed_job_ids' => $this->selected, 'reason' => $this->reason]);
            $this->selected = [];
        }

        if ($this->selectedAction === 'ignore') {
            FailedJobNote::query()->updateOrCreate(
                ['failed_job_id' => $this->selectedId],
                ['ignored_at' => now(), 'ignored_reason' => $this->reason]
            );
            Audit::log('admin.failed_job.ignored', null, null, ['failed_job_id' => $this->selectedId, 'reason' => $this->reason]);
        }

        $this->reset(['selectedId', 'selectedAction', 'reason']);
        session()->flash('status', 'Action completed.');
    }

    public function with(): array
    {
        $jobs = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(50)->get();
        $notes = FailedJobNote::query()->whereIn('failed_job_id', $jobs->pluck('id'))->get()->keyBy('failed_job_id');

        return [
            'jobs' => $jobs,
            'notes' => $notes,
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Failed Jobs</h1>
            <p class="text-sm text-slate-600">Retry or mark ignored with audit trail.</p>
        </div>
        <button wire:click="confirmAction('retry_selected')" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Retry selected</button>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if($loadError)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Unable to load failed jobs.
            <button wire:click="refreshPage" class="ml-3 rounded border border-rose-300 px-2 py-1 text-xs font-semibold">Retry</button>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left"></th>
                    <th class="px-4 py-3 text-left">Queue</th>
                    <th class="px-4 py-3 text-left">Job Class</th>
                    <th class="px-4 py-3 text-left">Exception</th>
                    <th class="px-4 py-3 text-left">Attempts</th>
                    <th class="px-4 py-3 text-left">Failed At</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($jobs as $job)
                    <tr>
                        <td class="px-4 py-3">
                            <input type="checkbox" wire:model="selected" value="{{ $job->id }}" class="rounded border-slate-300" />
                        </td>
                        <td class="px-4 py-3">{{ $job->queue }}</td>
                        <td class="px-4 py-3">{{ $job->payload ? json_decode($job->payload, true)['displayName'] ?? '—' : '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($job->exception, 80) }}</td>
                        <td class="px-4 py-3">{{ $job->attempts }}</td>
                        <td class="px-4 py-3">{{ \Carbon\Carbon::parse($job->failed_at)->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3 text-xs">
                                <button wire:click="confirmAction('retry', {{ $job->id }})" class="text-emerald-700 hover:underline">Retry</button>
                                <button wire:click="confirmAction('ignore', {{ $job->id }})" class="text-slate-700 hover:underline">Mark ignored</button>
                            </div>
                            @if($notes[$job->id] ?? null)
                                <div class="mt-1 text-xs text-slate-400">Ignored: {{ $notes[$job->id]->ignored_reason }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No failed jobs.</td>
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
                    @if($selectedAction === 'ignore')
                        Mark ignored? This keeps the job failed but removes it from attention.
                    @else
                        Retry job now? This may re-run the job.
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
