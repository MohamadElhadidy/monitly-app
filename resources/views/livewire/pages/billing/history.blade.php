<?php

use App\Services\Billing\BillingOwnerResolver;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public $invoices;
    public $payments;
    public $refunds;

    public function mount(BillingOwnerResolver $resolver): void
    {
        $context = $resolver->resolve(auth()->user());
        $billable = $context['billable'];

        $transactions = $billable->transactions()->latest()->get();

        $this->invoices = $transactions;
        $this->payments = $transactions->filter(fn ($tx) => in_array($tx->status, ['paid', 'completed'], true));
        $this->refunds = $transactions->filter(fn ($tx) => in_array($tx->status, ['refunded', 'refund'], true));
    }
};
?>

<div class="min-h-[calc(100vh-4rem)] bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Billing history</h1>
                <p class="text-sm text-slate-600">Invoices, payments, and refunds for your subscription.</p>
            </div>

            <div class="mt-8 space-y-8">
                <section>
                    <div class="text-sm font-semibold text-slate-900">Invoices</div>
                    <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                <tr>
                                    <th class="px-4 py-3">Invoice</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Total</th>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3 text-right">Download</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse ($invoices as $invoice)
                                    <tr>
                                        <td class="px-4 py-3 text-slate-900">{{ $invoice->invoice_number ?? '—' }}</td>
                                        <td class="px-4 py-3 capitalize text-slate-600">{{ $invoice->status }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $invoice->total }} {{ $invoice->currency }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $invoice->billed_at?->format('M d, Y') }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('billing.invoices.download', $invoice->id) }}"
                                               class="text-emerald-700 hover:text-emerald-800 text-sm font-semibold">
                                                Download
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-4 text-sm text-slate-500" colspan="5">No invoices yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <div class="text-sm font-semibold text-slate-900">Payments</div>
                    <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                <tr>
                                    <th class="px-4 py-3">Transaction</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Total</th>
                                    <th class="px-4 py-3">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse ($payments as $payment)
                                    <tr>
                                        <td class="px-4 py-3 text-slate-900">{{ $payment->paddle_id }}</td>
                                        <td class="px-4 py-3 capitalize text-slate-600">{{ $payment->status }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $payment->total }} {{ $payment->currency }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $payment->billed_at?->format('M d, Y') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-4 text-sm text-slate-500" colspan="4">No payments yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <div class="text-sm font-semibold text-slate-900">Refunds</div>
                    <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                <tr>
                                    <th class="px-4 py-3">Transaction</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Total</th>
                                    <th class="px-4 py-3">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse ($refunds as $refund)
                                    <tr>
                                        <td class="px-4 py-3 text-slate-900">{{ $refund->paddle_id }}</td>
                                        <td class="px-4 py-3 capitalize text-slate-600">{{ $refund->status }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $refund->total }} {{ $refund->currency }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $refund->billed_at?->format('M d, Y') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-4 text-sm text-slate-500" colspan="4">No refunds yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
