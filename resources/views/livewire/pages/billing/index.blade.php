<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
   use App\Support\BillingPlanResolver;


new #[Layout('layouts.app')] class extends Component {
    public string $interval = 'monthly'; // monthly|yearly
    public string $plan = 'pro';         // free|pro|team
    public array $addons = [];           // key => qty

    public array $plansConfig = [];
    public array $addonsConfig = [];


public function mount(): void
{
    $this->plansConfig = config('billing.plans', []);
    $this->addonsConfig = config('billing.addons', []);

    $user = auth()->user();

    // ---- PLAN DEFAULT ----
    if ($user->subscribed('default')) {
        $subscription = $user->subscription('default');

        $priceId = $subscription->items->first()->price_id ?? null;

        $this->plan = BillingPlanResolver::planFromPriceId($priceId);

        // interval
        $this->interval = $subscription->items->first()->price->billing_cycle->interval === 'year'
            ? 'yearly'
            : 'monthly';
    } else {
        $this->plan = 'free';
        $this->interval = 'monthly';
    }

    // ---- ADDONS DEFAULT ----
    foreach ($this->addonsConfig as $key => $cfg) {
        $this->addons[$key] = 0;
    }
}

    public function proceed()
    {
        $this->interval = in_array($this->interval, ['monthly', 'yearly'], true) ? $this->interval : 'monthly';
        $this->plan = in_array($this->plan, ['free', 'pro', 'team'], true) ? $this->plan : 'pro';

        $cleanAddons = [];
        foreach ($this->addonsConfig as $key => $cfg) {
            $qty = (int) ($this->addons[$key] ?? 0);
            $min = (int) ($cfg['min'] ?? 0);
            $max = (int) ($cfg['max'] ?? 999);
            $qty = max($min, min($max, $qty));
            if ($qty > 0) $cleanAddons[$key] = $qty;
        }

        // ✅ If Free + no addons → no checkout needed
        if ($this->plan === 'free' && empty($cleanAddons)) {
            session()->flash('billing_notice', 'You are on Free. Add an add-on to checkout.');
            return;
        }

        return redirect()->route('billing.checkout', [
            'plan' => $this->plan,
            'interval' => $this->interval,
            'addons' => $cleanAddons,
        ]);
    }
    
    public function isAddonAllowed(string $key): bool
{
    $cfg = $this->addonsConfig[$key] ?? null;
    if (!is_array($cfg)) return false;

    $allowed = $cfg['allowed_plans'] ?? ['free','pro','team'];
    if (!is_array($allowed)) return true;

    return in_array($this->plan, $allowed, true);
}

public function addonMaxQty(string $key): int
{
    // Faster checks is a toggle (0/1)
    if ($key === 'faster_checks_5min') return 1;

    // default packs can be multiple
    return 999;
}

public function incAddon(string $key): void
{
    if (!$this->isAddonAllowed($key)) return;

    $max = $this->addonMaxQty($key);
    $current = (int)($this->addons[$key] ?? 0);

    $this->addons[$key] = min($max, $current + 1);
}

public function decAddon(string $key): void
{
    if (!$this->isAddonAllowed($key)) return;

    $current = (int)($this->addons[$key] ?? 0);
    $this->addons[$key] = max(0, $current - 1);
}
};
?>

<div class="min-h-[calc(100vh-4rem)] bg-slate-50">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">

        <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Billing</h1>
                <p class="text-sm text-slate-600">Pick Pro/Team or stay Free. Add-ons work on all plans.</p>
            </div>

            @if (session('billing_notice'))
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    {{ session('billing_notice') }}
                </div>
            @endif

            {{-- Interval --}}
            <div class="mt-8">
                <div class="text-sm font-semibold text-slate-900">Billing interval</div>
                <div class="mt-3 inline-flex rounded-2xl border border-slate-200 bg-slate-50 p-1">
                    <button wire:click="$set('interval','monthly')"
                            class="rounded-2xl px-4 py-2 text-sm font-semibold transition
                                   {{ $interval==='monthly' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-600 hover:text-slate-900' }}">
                        Monthly
                    </button>
                    <button wire:click="$set('interval','yearly')"
                            class="rounded-2xl px-4 py-2 text-sm font-semibold transition
                                   {{ $interval==='yearly' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-600 hover:text-slate-900' }}">
                        Yearly
                    </button>
                </div>
            </div>

            {{-- Plans (from your config) --}}
            <div class="mt-8">
                <div class="text-sm font-semibold text-slate-900">Choose your plan</div>

                <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                    @foreach (['free','pro','team'] as $key)
                        @php $p = $plansConfig[$key] ?? null; @endphp
                        <button wire:click="$set('plan','{{ $key }}')"
                                class="rounded-3xl border p-5 text-left transition
                                       {{ $plan===$key ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-white hover:bg-slate-50' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $p['name'] ?? strtoupper($key) }}</div>
                                    <div class="mt-1 text-xs text-slate-600">{{ $p['description'] ?? '' }}</div>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-800 border border-slate-200">
                                    {{ ($p['price'] ?? 0) == 0 ? '$0' : '$'.($p['price'] ?? 0) }}
                                </span>
                            </div>

                            <ul class="mt-4 space-y-2 text-xs text-slate-700">
                                @foreach (($p['feature_list'] ?? []) as $line)
                                    <li class="flex items-start gap-2">
                                        <span class="mt-1 inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        <span>{{ $line }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            @if (!empty($p['popular']))
                                <div class="mt-4 text-xs font-semibold text-emerald-700">Most popular</div>
                            @endif
                            @if (!empty($p['best_value']))
                                <div class="mt-4 text-xs font-semibold text-emerald-700">Best value</div>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

           {{-- Addons (allowed based on allowed_plans) --}}
<div class="mt-10">
    <div class="flex items-end justify-between gap-3">
        <div>
            <div class="text-sm font-semibold text-slate-900">Add-ons</div>
            <div class="mt-1 text-xs text-slate-600">Upgrade capacity or speed. Add-ons work on Free too.</div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
        @foreach ($addonsConfig as $key => $cfg)
            @php
                $allowed = $this->isAddonAllowed($key);
                $qty = (int)($addons[$key] ?? 0);
            @endphp

            <div class="rounded-3xl border p-5 transition
                        {{ $allowed ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-50 opacity-70' }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">
                            {{ $cfg['name'] ?? $key }}
                            @if (!$allowed)
                                <span class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                    Requires {{ implode(' / ', $cfg['allowed_plans'] ?? []) ?: 'upgrade' }}
                                </span>
                            @endif
                        </div>
                        <div class="mt-1 text-xs text-slate-600">
                            {{ $cfg['description'] ?? '' }}
                            @if ($key === 'faster_checks_5min')
    <div class="mt-2 text-xs font-semibold text-slate-700">One-time upgrade (0/1)</div>
@endif
                        </div>
                        <div class="mt-2 text-xs font-semibold text-slate-700">
                            +${{ (int)($cfg['price'] ?? 0) }} / {{ $interval === 'yearly' ? 'year' : 'month' }}
                        </div>
                    </div>

                    <div class="inline-flex items-center rounded-2xl border border-slate-200 bg-slate-50 p-1">
                        <button type="button"
                                wire:click="decAddon('{{ $key }}')"
                                @disabled(! $allowed)
                                class="h-9 w-9 rounded-xl bg-white text-slate-800 shadow-sm hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            −
                        </button>

                        <div class="w-12 text-center text-sm font-semibold text-slate-900">
                            {{ $qty }}
                        </div>

                        <button type="button"
                                wire:click="incAddon('{{ $key }}')"
                                @disabled(! $allowed)
                                class="h-9 w-9 rounded-xl bg-white text-slate-800 shadow-sm hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">
                            +
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

            {{-- Continue --}}
            <div class="mt-10">
                <button wire:click="proceed"
                        class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300">
                    Continue to checkout
                </button>

                <div class="mt-4 text-xs text-slate-500">
                    Refund policy: 30 days from your first purchase.
                </div>
            </div>
        </div>
    </div>
</div>