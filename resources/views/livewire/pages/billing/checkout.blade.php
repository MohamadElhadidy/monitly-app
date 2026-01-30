@extends('layouts.app')

@section('content')
<div class="min-h-[calc(100vh-4rem)] bg-slate-50">
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Confirm plan change</h1>
                    <p class="mt-2 text-sm text-slate-600">
                        Review your selection and confirm your billing change.
                    </p>
                </div>

                <a href="{{ route('billing.index') }}"
                   class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    Edit selection
                </a>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="text-sm font-semibold text-slate-900">Selected plan</div>

                    <div class="mt-4 rounded-2xl bg-white p-4 border border-slate-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">{{ $planConfig['name'] ?? ucfirst($plan) }}</div>
                                <div class="mt-1 text-xs text-slate-600">{{ ucfirst($interval) }} billing</div>
                            </div>
                            <div class="text-sm font-semibold text-slate-900">
                                {{ $interval === 'yearly' ? '$'.($planConfig['price_yearly'] ?? 0).'/yr' : '$'.($planConfig['price_monthly'] ?? 0).'/mo' }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 text-xs text-slate-500">
                        Taxes and final totals are calculated in Paddle.
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-5" x-data="{ processing: false }">
                    <div class="text-sm font-semibold text-slate-900">What happens next</div>

                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="font-semibold text-slate-900">Now</div>
                            <div class="mt-1">Upgrades take effect immediately. Your usage limits update as soon as billing syncs.</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="font-semibold text-slate-900">Later</div>
                            <div class="mt-1">Downgrades apply at the end of the current billing period.</div>
                        </div>
                    </div>

                    <div class="mt-6">
                        @if ($requiresCheckout)
                            <x-paddle-button :checkout="$checkout"
                                x-on:click="processing = true"
                                x-bind:disabled="processing"
                                class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:opacity-60">
                                Continue to Paddle checkout
                            </x-paddle-button>
                        @else
                            <form method="POST" action="{{ route('billing.checkout.change') }}">
                                @csrf
                                <input type="hidden" name="plan" value="{{ $plan }}">
                                <input type="hidden" name="interval" value="{{ $interval }}">
                                <button type="submit"
                                        x-on:click="processing = true"
                                        x-bind:disabled="processing"
                                        class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:opacity-60">
                                    Confirm plan change
                                </button>
                            </form>
                        @endif

                        <div class="mt-4 text-xs text-slate-500">
                            Billing is managed through Paddle subscriptions only.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
