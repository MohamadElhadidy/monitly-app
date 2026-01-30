<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Payments')]
class extends Component
{
    public bool $loadError = false;

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function with(): array
    {
        $payments = DB::table('transactions')
            ->orderByDesc('billed_at')
            ->limit(50)
            ->get();

        return ['payments' => $payments];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Payments</h1>
        <p class="text-sm text-slate-600">All Paddle transactions with failure details.</p>
    </div>

    @if($loadError)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Unable to load payment data.
            <button wire:click="refreshPage" class="ml-3 rounded border border-rose-300 px-2 py-1 text-xs font-semibold">Retry</button>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Amount</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Reason</th>
                    <th class="px-4 py-3 text-left">Subscription</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($payments as $payment)
                    <tr>
                        <td class="px-4 py-3">{{ \Carbon\Carbon::parse($payment->billed_at)->toDateString() }}</td>
                        <td class="px-4 py-3">{{ $payment->currency }} {{ $payment->total }}</td>
                        <td class="px-4 py-3">{{ $payment->status }}</td>
                        <td class="px-4 py-3">{{ $payment->status === 'failed' ? 'Failed charge' : '—' }}</td>
                        <td class="px-4 py-3 text-xs">{{ $payment->paddle_subscription_id ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No payments recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
