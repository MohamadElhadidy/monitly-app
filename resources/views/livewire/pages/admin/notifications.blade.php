<?php

use App\Services\Admin\AdminSettingsService;
use App\Services\Audit\Audit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Notifications Health')]
class extends Component
{
    public bool $loadError = false;
    public ?string $selectedAction = null;
    public string $reason = '';
    public string $integration = 'email';

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function confirmAction(string $action): void
    {
        $this->selectedAction = $action;
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

        if ($this->selectedAction === 'test_email') {
            $ownerEmail = config('admin.owner_email');
            Mail::raw('Monitly admin notification test.', function ($message) use ($ownerEmail) {
                $message->to($ownerEmail)->subject('Monitly Admin Test');
            });

            Audit::log('admin.notifications.test_email', null, null, ['reason' => $this->reason]);
        }

        if ($this->selectedAction === 'disable_integration') {
            Cache::put("admin.notifications.disabled.{$this->integration}", true, now()->addHours(6));
            Audit::log('admin.notifications.disabled', null, null, ['integration' => $this->integration, 'reason' => $this->reason]);
        }

        $this->reset(['selectedAction', 'reason']);
        session()->flash('status', 'Notification action completed.');
    }

    public function with(): array
    {
        $total = DB::table('notification_deliveries')->count();
        $success = DB::table('notification_deliveries')->whereNotNull('sent_at')->count();
        $failures = DB::table('notification_deliveries')->whereNull('sent_at')->count();

        $rate = $total > 0 ? round(($success / $total) * 100, 2) : 0;

        $failuresByChannel = DB::table('notification_deliveries')
            ->select('channel', DB::raw('count(*) as total'))
            ->whereNull('sent_at')
            ->groupBy('channel')
            ->orderByDesc('total')
            ->get();

        return [
            'rate' => $rate,
            'failures' => $failures,
            'failuresByChannel' => $failuresByChannel,
            'rateLimitErrors' => 0,
        ];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Notifications Health</h1>
        <p class="text-sm text-slate-600">Delivery success rate and channel failures.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">Delivery Success Rate</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $rate }}%</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">Failures</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ $failures }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">Rate limit errors</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $rateLimitErrors }}</div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="text-sm font-semibold text-slate-900">Failures by Channel</div>
        <div class="mt-3 space-y-2 text-sm">
            @forelse($failuresByChannel as $row)
                <div class="flex items-center justify-between">
                    <div class="text-slate-700">{{ $row->channel }}</div>
                    <div class="font-semibold text-rose-600">{{ $row->total }}</div>
                </div>
            @empty
                <div class="text-sm text-slate-500">No failures recorded.</div>
            @endforelse
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <button wire:click="confirmAction('test_email')" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Send test email to owner</button>
        <div class="flex items-center gap-2">
            <select wire:model="integration" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="email">Email</option>
                <option value="slack">Slack</option>
                <option value="webhooks">Webhooks</option>
            </select>
            <button wire:click="confirmAction('disable_integration')" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white">Disable integration</button>
        </div>
    </div>

    @if($selectedAction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900">Confirm action</h2>
                <p class="mt-2 text-sm text-slate-600">
                    @if($selectedAction === 'test_email')
                        Send a test email to the owner account?
                    @else
                        Temporarily disable the selected integration globally?
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
