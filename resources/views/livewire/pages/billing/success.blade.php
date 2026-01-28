<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
    // UX-only
};
?>

<div x-data="{ showConfetti: true }" class="min-h-[calc(100vh-4rem)] bg-slate-50">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
            <div class="flex flex-col items-center text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm">
                    ✓
                </div>

                <h1 class="mt-5 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                    Payment successful 🎉
                </h1>
                <p class="mt-2 max-w-xl text-sm text-slate-600">
                    Your plan is active. You can manage everything from the billing portal anytime.
                </p>

                <div class="mt-6 w-full rounded-2xl bg-slate-50 p-5 text-left">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">What’s next</div>
                            <div class="mt-1 text-sm text-slate-600">
                                Set up monitors and enable alerts for the endpoints that matter.
                            </div>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            Activated
                        </span>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-white p-4 border border-slate-200">
                            <div class="text-sm font-semibold text-slate-900">1) Add your first monitor</div>
                            <div class="mt-1 text-sm text-slate-600">Start with your main domain and critical API endpoints.</div>
                        </div>
                        <div class="rounded-2xl bg-white p-4 border border-slate-200">
                            <div class="text-sm font-semibold text-slate-900">2) Configure alerts</div>
                            <div class="mt-1 text-sm text-slate-600">Email is a good baseline. Add more channels later.</div>
                        </div>
                        <div class="rounded-2xl bg-white p-4 border border-slate-200">
                            <div class="text-sm font-semibold text-slate-900">3) Review status page</div>
                            <div class="mt-1 text-sm text-slate-600">Keep it private or make it public when ready.</div>
                        </div>
                        <div class="rounded-2xl bg-white p-4 border border-slate-200">
                            <div class="text-sm font-semibold text-slate-900">4) Invite team (Team plan)</div>
                            <div class="mt-1 text-sm text-slate-600">Give stakeholders visibility without full access.</div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex w-full flex-col gap-3 sm:flex-row">
                    <a href="/dashboard"
                       class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300">
                        Go to dashboard
                    </a>
                    <a href="/billing"
                       class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                        Manage billing
                    </a>
                </div>

                <div class="mt-6 w-full rounded-2xl border border-slate-200 p-4 text-left">
                    <div class="text-sm font-semibold text-slate-900">Need help?</div>
                    <div class="mt-1 text-sm text-slate-600">
                        If something looks wrong, contact support and we’ll fix it fast.
                    </div>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-xs text-slate-500">Refund policy: 30 days from your first purchase.</div>
                        <a href="/support" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">Contact support</a>
                    </div>
                </div>

                <div class="mt-6 text-xs text-slate-500">
                    Receipt and invoices are available in your billing portal.
                </div>
            </div>
        </div>
    </div>
</div>