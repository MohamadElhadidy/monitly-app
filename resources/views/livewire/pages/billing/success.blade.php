<?php

use App\Services\Billing\BillingOwnerResolver;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public bool $polling = true;
    public int $pollingEndsAt = 0;
    public string $billingStatus = 'free';
    public string $billingPlan = 'free';

    public function mount(BillingOwnerResolver $resolver): void
    {
        $context = $resolver->resolve(auth()->user());
        $billable = $context['billable'];

        $this->billingStatus = strtolower((string) ($billable->billing_status ?? 'free'));
        $this->billingPlan = strtolower((string) ($billable->billing_plan ?? 'free'));
        $this->pollingEndsAt = now()->addSeconds(60)->timestamp;
    }

    public function refreshStatus(BillingOwnerResolver $resolver): void
    {
        $context = $resolver->resolve(auth()->user());
        $billable = $context['billable'];

        $this->billingStatus = strtolower((string) ($billable->billing_status ?? 'free'));
        $this->billingPlan = strtolower((string) ($billable->billing_plan ?? 'free'));

        if (now()->timestamp >= $this->pollingEndsAt) {
            $this->polling = false;
        }
    }
};
?>

<div class="min-h-[calc(100vh-4rem)] bg-slate-50">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
            <div class="flex flex-col items-center text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm">
                    ⟳
                </div>

                <h1 class="mt-5 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                    Syncing your billing
                </h1>
                <p class="mt-2 max-w-xl text-sm text-slate-600">
                    We are syncing your subscription status with Paddle. This usually takes a few seconds.
                </p>

                @if ($polling)
                    <div class="mt-6 w-full rounded-2xl bg-slate-50 p-5 text-left" wire:poll.5s="refreshStatus">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">Current status</div>
                                <div class="mt-1 text-sm text-slate-600 capitalize">{{ str_replace('_', ' ', $billingStatus) }}</div>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                Syncing
                            </span>
                        </div>
                    </div>
                @else
                    <div class="mt-6 w-full rounded-2xl border border-amber-200 bg-amber-50 p-5 text-left text-sm text-amber-800">
                        Syncing is taking longer than usual. You can continue using Monitly and check billing again in a moment.
                    </div>
                @endif

                <div class="mt-6 flex w-full flex-col gap-3 sm:flex-row">
                    <a href="{{ route('dashboard') }}"
                       class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300">
                        Go to dashboard
                    </a>
                    <a href="{{ route('billing.index') }}"
                       class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                        Manage billing
                    </a>
                </div>

                <div class="mt-6 text-xs text-slate-500">
                    Your plan and invoices will appear once billing sync completes.
                </div>
            </div>
        </div>
    </div>
</div>
