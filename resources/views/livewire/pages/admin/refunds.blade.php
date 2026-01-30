<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Refunds')]
class extends Component
{
    public bool $loadError = false;

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function with(): array
    {
        $refunds = DB::table('billing_refunds')
            ->orderByDesc('refunded_at')
            ->limit(50)
            ->get();

        return ['refunds' => $refunds];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Refunds</h1>
        <p class="text-sm text-slate-600">Recent refunds processed in Paddle.</p>
    </div>

    @if($loadError)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Unable to load refunds.
            <button wire:click="refreshPage" class="ml-3 rounded border border-rose-300 px-2 py-1 text-xs font-semibold">Retry</button>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Amount</th>
                    <th class="px-4 py-3 text-left">Reason</th>
                    <th class="px-4 py-3 text-left">Linked Payment</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($refunds as $refund)
                    <tr>
                        <td class="px-4 py-3">{{ $refund->refunded_at ? \Carbon\Carbon::parse($refund->refunded_at)->toDateString() : '—' }}</td>
                        <td class="px-4 py-3">{{ $refund->currency }} {{ $refund->amount }}</td>
                        <td class="px-4 py-3">{{ $refund->reason ?? '—' }}</td>
                        <td class="px-4 py-3">#{{ $refund->transaction_id ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No refunds yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
