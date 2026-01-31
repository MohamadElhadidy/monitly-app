<?php

use App\Services\Billing\BillingOwnerResolver;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public bool $polling = true;
    public bool $synced = false;
    public int $pollingEndsAt = 0;
    public int $pollCount = 0;
    public string $billingStatus = 'free';
    public string $billingPlan = 'free';
    public ?string $nextBillingDate = null;

    public function mount(BillingOwnerResolver $resolver): void
    {
        $context = $resolver->resolve(auth()->user());
        $billable = $context['billable'];

        $this->billingStatus = strtolower((string) ($billable->billing_status ?? 'free'));
        $this->billingPlan = strtolower((string) ($billable->billing_plan ?? 'free'));
        $this->nextBillingDate = $billable->next_bill_at?->format('M d, Y');
        $this->pollingEndsAt = now()->addSeconds(60)->timestamp;

        // Check if already synced (active subscription)
        if (in_array($this->billingStatus, ['active', 'canceled'])) {
            $this->synced = true;
            $this->polling = false;
        }
    }

    public function refreshStatus(BillingOwnerResolver $resolver): void
    {
        $this->pollCount++;

        $context = $resolver->resolve(auth()->user());
        $billable = $context['billable'];

        $this->billingStatus = strtolower((string) ($billable->billing_status ?? 'free'));
        $this->billingPlan = strtolower((string) ($billable->billing_plan ?? 'free'));
        $this->nextBillingDate = $billable->next_bill_at?->format('M d, Y');

        // Check if synced (webhook has updated the status)
        if (in_array($this->billingStatus, ['active', 'canceled'])) {
            $this->synced = true;
            $this->polling = false;
            return;
        }

        // Stop polling after 60 seconds (12 polls at 5s intervals)
        if (now()->timestamp >= $this->pollingEndsAt || $this->pollCount >= 12) {
            $this->polling = false;
        }
    }

    public function manualRefresh(BillingOwnerResolver $resolver): void
    {
        $this->pollCount = 0;
        $this->pollingEndsAt = now()->addSeconds(60)->timestamp;
        $this->polling = true;
        $this->refreshStatus($resolver);
    }
};
?>

<div class="min-h-[calc(100vh-4rem)] bg-slate-50">
    <x-slot name="breadcrumbs">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2">
                <li><a href="{{ route('billing.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Billing</a></li>
                <li class="flex items-center">
                    <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                    <span class="ml-2 text-sm font-medium text-gray-900">Checkout Complete</span>
                </li>
            </ol>
        </nav>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
            <div class="flex flex-col items-center text-center">

                @if ($synced)
                    {{-- SUCCESS STATE --}}
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 shadow-sm">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>

                    <h1 class="mt-5 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                        Plan updated successfully
                    </h1>
                    <p class="mt-2 max-w-xl text-sm text-slate-600">
                        Your subscription is now active. Thank you for choosing Monitly!
                    </p>

                    <div class="mt-6 w-full rounded-2xl bg-emerald-50 border border-emerald-200 p-5">
                        <div class="flex items-center justify-between">
                            <div class="text-left">
                                <div class="text-sm font-semibold text-emerald-900">Current Plan</div>
                                <div class="mt-1 text-lg font-bold text-emerald-700 capitalize">{{ $billingPlan }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-semibold text-emerald-900">Status</div>
                                <span class="mt-1 inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700 capitalize">
                                    {{ str_replace('_', ' ', $billingStatus) }}
                                </span>
                            </div>
                        </div>
                        @if ($nextBillingDate)
                            <div class="mt-4 pt-4 border-t border-emerald-200 text-left">
                                <div class="text-xs text-emerald-700">Next billing date: <span class="font-semibold">{{ $nextBillingDate }}</span></div>
                            </div>
                        @endif
                    </div>

                @elseif ($polling)
                    {{-- SYNCING STATE --}}
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-100 text-blue-600 shadow-sm">
                        <svg class="h-8 w-8 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <h1 class="mt-5 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                        Syncing your billing…
                    </h1>
                    <p class="mt-2 max-w-xl text-sm text-slate-600">
                        This can take a moment. Don't refresh or close this page.
                    </p>

                    <div class="mt-6 w-full rounded-2xl bg-slate-50 p-5 text-left" wire:poll.5s="refreshStatus">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">Current status</div>
                                <div class="mt-1 text-sm text-slate-600 capitalize">{{ str_replace('_', ' ', $billingStatus) }}</div>
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                <span class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>
                                Syncing
                            </span>
                        </div>
                        <div class="mt-4">
                            <div class="h-1.5 w-full bg-slate-200 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500 rounded-full transition-all duration-500" 
                                     style="width: {{ min(100, ($pollCount / 12) * 100) }}%"></div>
                            </div>
                            <div class="mt-2 text-xs text-slate-500">Waiting for payment confirmation…</div>
                        </div>
                    </div>

                @else
                    {{-- TIMEOUT STATE --}}
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 shadow-sm">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>

                    <h1 class="mt-5 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                        Still syncing…
                    </h1>
                    <p class="mt-2 max-w-xl text-sm text-slate-600">
                        We're still waiting for confirmation from our payment provider. This can sometimes take a minute.
                    </p>

                    <div class="mt-6 w-full rounded-2xl border border-amber-200 bg-amber-50 p-5 text-left">
                        <div class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                            <div>
                                <div class="text-sm font-semibold text-amber-900">What to do next</div>
                                <ul class="mt-2 text-sm text-amber-800 space-y-1">
                                    <li>• Try refreshing the status below</li>
                                    <li>• Check your email for a payment receipt</li>
                                    <li>• Return to billing in a minute to verify</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mt-8 flex w-full flex-col gap-3 sm:flex-row">
                    @if ($synced)
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300">
                            Go to Dashboard
                        </a>
                        <a href="{{ route('billing.index') }}"
                           class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                            View Billing
                        </a>
                    @elseif (!$polling)
                        <button wire:click="manualRefresh"
                                wire:loading.attr="disabled"
                                class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:opacity-50">
                            <span wire:loading.remove>Refresh Status</span>
                            <span wire:loading>Checking…</span>
                        </button>
                        <a href="{{ route('billing.index') }}"
                           class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                            Back to Billing
                        </a>
                    @else
                        <a href="{{ route('billing.index') }}"
                           class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                            Back to Billing
                        </a>
                    @endif
                </div>

                @if (!$synced)
                    <div class="mt-6 text-xs text-slate-500">
                        Your plan and invoices will appear once billing sync completes.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
