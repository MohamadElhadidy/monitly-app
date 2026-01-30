<?php

use App\Jobs\Billing\ProcessPaddleWebhookJob;
use App\Models\BillingWebhookEvent;
use App\Services\Admin\AdminSettingsService;
use App\Services\Audit\Audit;
use Illuminate\Support\Arr;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Paddle Webhooks')]
class extends Component
{
    public bool $loadError = false;
    public ?int $selectedId = null;
    public ?string $selectedAction = null;
    public string $reason = '';
    public ?string $payloadPreview = null;

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function viewPayload(int $id): void
    {
        $event = BillingWebhookEvent::query()->findOrFail($id);
        $this->payloadPreview = json_encode($this->redactPayload($event->payload ?? []), JSON_PRETTY_PRINT);
    }

    public function confirmReplay(int $id): void
    {
        $this->selectedId = $id;
        $this->selectedAction = 'replay';
    }

    public function performReplay(AdminSettingsService $settings): void
    {
        if ($settings->getSettings()->read_only_mode) {
            $this->addError('reason', 'Read-only mode is enabled.');
            return;
        }

        $this->validate([
            'reason' => 'required|string|min:3',
        ]);

        ProcessPaddleWebhookJob::dispatch($this->selectedId);
        Audit::log('admin.webhook.replay', null, null, ['event_id' => $this->selectedId, 'reason' => $this->reason]);

        $this->reset(['selectedId', 'selectedAction', 'reason']);
        session()->flash('status', 'Webhook replay queued.');
    }

    private function redactPayload(array $payload): array
    {
        $sensitiveKeys = ['signature', 'token', 'secret', 'card', 'email'];
        $redacted = $payload;

        foreach (Arr::dot($payload) as $key => $value) {
            foreach ($sensitiveKeys as $needle) {
                if (str_contains(strtolower($key), $needle)) {
                    Arr::set($redacted, $key, '[redacted]');
                }
            }
        }

        return $redacted;
    }

    public function with(): array
    {
        $events = BillingWebhookEvent::query()->orderByDesc('created_at')->limit(50)->get();

        return ['events' => $events];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Webhooks (Paddle)</h1>
        <p class="text-sm text-slate-600">Inbound webhook processing status with redacted payloads.</p>
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
                    <th class="px-4 py-3 text-left">Event ID</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Signature Valid</th>
                    <th class="px-4 py-3 text-left">Processed At</th>
                    <th class="px-4 py-3 text-left">Error</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($events as $event)
                    <tr>
                        <td class="px-4 py-3 text-xs">{{ $event->event_id }}</td>
                        <td class="px-4 py-3">{{ $event->event_type }}</td>
                        <td class="px-4 py-3">{{ $event->signature_valid ? 'true' : 'false' }}</td>
                        <td class="px-4 py-3">{{ $event->processed_at ? $event->processed_at->toDateTimeString() : '—' }}</td>
                        <td class="px-4 py-3 text-xs text-rose-600">{{ $event->processing_error ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3 text-xs">
                                <button wire:click="viewPayload({{ $event->id }})" class="text-slate-700 hover:underline">View payload</button>
                                <button wire:click="confirmReplay({{ $event->id }})" class="text-amber-700 hover:underline">Replay</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No webhook events logged.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($payloadPreview)
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-900">Redacted Payload</div>
                <button wire:click="$set('payloadPreview', null)" class="text-xs text-slate-500">Close</button>
            </div>
            <pre class="mt-3 max-h-96 overflow-auto rounded bg-slate-900 p-3 text-xs text-emerald-200">{{ $payloadPreview }}</pre>
        </div>
    @endif

    @if($selectedAction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900">Replay webhook?</h2>
                <p class="mt-2 text-sm text-slate-600">Replay event (idempotent). This must be safe to run multiple times.</p>
                <div class="mt-4">
                    <label class="text-xs font-semibold uppercase text-slate-500">Reason</label>
                    <textarea wire:model.defer="reason" class="mt-2 w-full rounded-lg border border-slate-200 p-2 text-sm" rows="3" placeholder="Reason required"></textarea>
                    @error('reason') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('selectedAction', null)" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button wire:click="performReplay" class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Confirm</button>
                </div>
            </div>
        </div>
    @endif
</div>
