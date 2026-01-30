<?php

use App\Models\Team;
use App\Models\User;
use App\Services\Admin\AdminBillingService;
use App\Services\Admin\AdminSettingsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;

new
#[Layout('layouts.admin')]
#[Title('Admin • Subscriptions')]
class extends Component
{
    public bool $loadError = false;
    public ?int $selectedId = null;
    public ?string $selectedType = null;
    public string $reason = '';

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function confirmResync(string $type, int $id): void
    {
        $this->selectedType = $type;
        $this->selectedId = $id;
    }

    public function performResync(AdminBillingService $service, AdminSettingsService $settings): void
    {
        if ($settings->getSettings()->read_only_mode) {
            $this->addError('reason', 'Read-only mode is enabled.');
            return;
        }

        $this->validate([
            'reason' => 'required|string|min:3',
        ]);

        $billable = $this->selectedType === 'team'
            ? Team::query()->findOrFail($this->selectedId)
            : User::query()->findOrFail($this->selectedId);

        $service->requestResync($billable, $this->reason);

        $this->reset(['selectedType', 'selectedId', 'reason']);
        session()->flash('status', 'Billing resync queued.');
    }

    public function with(): array
    {
        $subscriptions = DB::table('subscriptions')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $items = DB::table('subscription_items')
            ->whereIn('subscription_id', $subscriptions->pluck('id'))
            ->get()
            ->groupBy('subscription_id');

        $priceMap = [];
        foreach (config('billing.plans') as $planKey => $plan) {
            foreach (($plan['price_ids'] ?? []) as $cycle => $priceId) {
                if ($priceId) {
                    $priceMap[$priceId] = ['cycle' => $cycle, 'plan' => $planKey];
                }
            }
        }

        $rows = $subscriptions->map(function ($subscription) use ($items, $priceMap) {
            $item = $items[$subscription->id][0] ?? null;
            $cycle = $item && isset($priceMap[$item->price_id]) ? $priceMap[$item->price_id]['cycle'] : 'monthly';
            $billableType = $subscription->billable_type === Team::class ? 'team' : 'user';
            $billable = $subscription->billable_type === Team::class
                ? Team::query()->find($subscription->billable_id)
                : User::query()->find($subscription->billable_id);

            return [
                'id' => $subscription->id,
                'entity_type' => $billableType === 'team' ? 'Team' : 'Normal user',
                'owner_email' => $billableType === 'team' ? optional($billable?->owner)->email : $billable?->email,
                'plan' => $billable?->billing_plan ?? 'free',
                'status' => $subscription->status,
                'billing_cycle' => $cycle,
                'next_bill_at' => $billable?->next_bill_at,
                'paddle_subscription_id' => $subscription->paddle_id,
                'billable_type' => $billableType,
                'billable_id' => $billable?->id,
            ];
        });

        return ['rows' => $rows];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Subscriptions</h1>
        <p class="text-sm text-slate-600">Paddle subscription health across users and teams.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if($loadError)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Unable to load subscriptions.
            <button wire:click="refreshPage" class="ml-3 rounded border border-rose-300 px-2 py-1 text-xs font-semibold">Retry</button>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Entity</th>
                    <th class="px-4 py-3 text-left">Owner Email</th>
                    <th class="px-4 py-3 text-left">Plan</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Billing Cycle</th>
                    <th class="px-4 py-3 text-left">Next Charge</th>
                    <th class="px-4 py-3 text-left">Paddle Subscription</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($rows as $row)
                    <tr>
                        <td class="px-4 py-3">{{ $row['entity_type'] }}</td>
                        <td class="px-4 py-3">{{ $row['owner_email'] ?? '—' }}</td>
                        <td class="px-4 py-3">{{ ucfirst($row['plan']) }}</td>
                        <td class="px-4 py-3">{{ $row['status'] }}</td>
                        <td class="px-4 py-3">{{ ucfirst($row['billing_cycle']) }}</td>
                        <td class="px-4 py-3">{{ $row['next_bill_at'] ? \Carbon\Carbon::parse($row['next_bill_at'])->toDateString() : '—' }}</td>
                        <td class="px-4 py-3 text-xs">{{ $row['paddle_subscription_id'] }}</td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="confirmResync('{{ $row['billable_type'] }}', {{ $row['billable_id'] }})" class="text-xs font-semibold text-slate-700 hover:underline">Resync billing</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">No subscriptions found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($selectedId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900">Resync billing from Paddle?</h2>
                <p class="mt-2 text-sm text-slate-600">Resync billing from Paddle? This may take a moment.</p>
                <div class="mt-4">
                    <label class="text-xs font-semibold uppercase text-slate-500">Reason</label>
                    <textarea wire:model.defer="reason" class="mt-2 w-full rounded-lg border border-slate-200 p-2 text-sm" rows="3" placeholder="Reason required"></textarea>
                    @error('reason') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('selectedId', null)" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button wire:click="performResync" class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Confirm</button>
                </div>
            </div>
        </div>
    @endif
</div>
