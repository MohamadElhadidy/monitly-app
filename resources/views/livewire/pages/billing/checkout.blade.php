@extends('layouts.apps')

@section('content')
<div class="min-h-[calc(100vh-4rem)] bg-slate-50">
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Checkout</h1>
                    <p class="mt-2 text-sm text-slate-600">
                        Review your selection, then complete payment securely via Paddle.
                    </p>
                </div>

                <a href="{{ route('billing.index') }}"
                   class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    Edit selection
                </a>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="text-sm font-semibold text-slate-900">Order summary</div>

                    <div class="mt-4 space-y-3">
                        @foreach ($lineItems as $item)
                            <div class="flex items-center justify-between rounded-2xl bg-white p-4 border border-slate-200">
                                <div class="text-sm font-semibold text-slate-900">{{ $item['label'] }}</div>
                                <div class="text-xs font-semibold text-slate-700">× {{ (int)$item['qty'] }}</div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 text-xs text-slate-500">
                        Pricing and taxes are finalized on Paddle’s checkout screen.
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-5">
                    <div class="text-sm font-semibold text-slate-900">Pay with Paddle</div>

                    <div class="mt-4">
                        <x-paddle-button :checkout="$checkout"
                            class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300">
                            Pay & Activate
                        </x-paddle-button>

                        <div class="mt-4 text-xs text-slate-500">
                            By continuing, you agree to the refund policy (30 days from your first purchase).
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection