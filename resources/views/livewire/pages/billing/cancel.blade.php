<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    // Simple cancel page
};
?>

<div class="min-h-[calc(100vh-4rem)] bg-slate-50">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 shadow-sm mx-auto">
                ×
            </div>
            <h1 class="mt-5 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Checkout canceled</h1>
            <p class="mt-2 text-sm text-slate-600">No changes were made.</p>
            <div class="mt-6 flex w-full flex-col gap-3 sm:flex-row justify-center">
                <a href="{{ route('billing.index') }}"
                   class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700">
                    Back to billing
                </a>
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    Go to dashboard
                </a>
            </div>
        </div>
    </div>
</div>
