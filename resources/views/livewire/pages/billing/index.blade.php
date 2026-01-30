<?php

use App\Models\Monitor;
use App\Services\Billing\BillingOwnerResolver;
use App\Services\Billing\PaddleService;
use App\Services\Billing\PlanLimits;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public string $interval = 'monthly';
    public string $selectedPlan = 'free';
    public string $currentPlan = 'free';
    public string $billingStatus = 'free';

    public array $plansConfig = [];
    public bool $isTeamContext = false;
    public bool $canManage = true;
    public bool $checkoutLocked = false;
    public int $monitorCount = 0;
    public int $userCount = 1;
    public int $monitorLimit = 0;
    public int $userLimit = 1;
    public ?int $teamId = null;

    public function mount(BillingOwnerResolver $resolver): void
    {
        $this->plansConfig = config('billing.plans', []);

        $user = auth()->user();
        $context = $resolver->resolve($user);
        $billable = $context['billable'];

        $this->isTeamContext = $context['type'] === 'team';
        $this->canManage = $resolver->canManage($user, $context['team']);
        $this->teamId = $context['team']?->id;

        $this->currentPlan = strtolower((string) ($billable->billing_plan ?? 'free'));
        $this->billingStatus = strtolower((string) ($billable->billing_status ?? 'free'));
        $this->selectedPlan = $this->currentPlan;

        $this->checkoutLocked = (bool) ($billable->checkout_in_progress_until && $billable->checkout_in_progress_until->isFuture());

        $subscription = method_exists($billable, 'subscription') ? $billable->subscription('default') : null;
        if ($subscription && $subscription->items && $subscription->items->first()?->price?->billing_cycle?->interval === 'year') {
            $this->interval = 'yearly';
        }

        if ($this->isTeamContext && $context['team']) {
            $team = $context['team'];
            $this->monitorCount = Monitor::query()->where('team_id', $team->id)->count();
            $this->userCount = $team->allUsers()->count();
            $this->monitorLimit = PlanLimits::monitorLimitForTeam($team);
            $this->userLimit = PlanLimits::seatLimitForTeam($team);
        } else {
            $this->monitorCount = Monitor::query()
                ->where('user_id', $user->id)
                ->whereNull('team_id')
                ->count();
            $this->monitorLimit = PlanLimits::monitorLimitForUser($user);
            $this->userLimit = 1;
        }
    }

    public function proceed(BillingOwnerResolver $resolver, PaddleService $paddleService)
    {
        if (! $this->canManage) {
            session()->flash('billing_notice', 'Only the team owner can manage billing.');
            return;
        }

        if ($this->checkoutLocked) {
            session()->flash('billing_notice', 'Checkout already in progress. Please wait a few minutes.');
            return;
        }

        if ($this->selectedPlan === $this->currentPlan) {
            session()->flash('billing_notice', 'This is your current plan.');
            return;
        }

        if ($this->selectedPlan === 'free') {
            $user = auth()->user();
            $context = $resolver->resolve($user);
            $billable = $context['billable'];

            if (! $resolver->canManage($user, $context['team'])) {
                session()->flash('billing_notice', 'Only the team owner can manage billing.');
                return;
            }

            if (! $billable->paddle_subscription_id) {
                session()->flash('billing_notice', 'No active subscription to cancel.');
                return;
            }

            $paddleService->cancelSubscription($billable->paddle_subscription_id, false);
            $billable->billing_status = 'canceling';
            $billable->save();

            session()->flash('billing_notice', 'Your plan will downgrade at the end of the current billing period.');
            return;
        }

        return redirect()->route('billing.checkout', [
            'plan' => $this->selectedPlan,
            'interval' => $this->interval,
        ]);
    }

    public function canSelectPlan(string $plan, BillingOwnerResolver $resolver): bool
    {
        if ($this->checkoutLocked || ! $this->canManage) {
            return false;
        }

        if ($resolver->isTeamPlan($plan) && ! $this->isTeamContext) {
            return false;
        }

        return true;
    }
};
?>

<div class="min-h-[calc(100vh-4rem)] bg-slate-50">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Billing</h1>
                <p class="text-sm text-slate-600">Manage your plan and usage for Monitly.</p>
            </div>

            @if (session('billing_notice'))
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    {{ session('billing_notice') }}
                </div>
            @endif

            @if (! $canManage)
                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    Billing changes are available to the team owner only. Members can view billing status and usage.
                </div>
            @endif

            @if ($checkoutLocked)
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    Checkout is already in progress. Please wait a few minutes before starting another checkout.
                </div>
            @endif

            <div class="mt-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-sm font-semibold text-slate-900">Current plan</div>
                    <div class="mt-1 text-sm text-slate-600">{{ ucfirst($currentPlan) }}</div>
                </div>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold capitalize
                    {{ $billingStatus === 'active' ? 'bg-emerald-50 text-emerald-700' : '' }}
                    {{ $billingStatus === 'past_due' ? 'bg-amber-50 text-amber-700' : '' }}
                    {{ $billingStatus === 'canceling' ? 'bg-blue-50 text-blue-700' : '' }}
                    {{ $billingStatus === 'canceled' ? 'bg-slate-100 text-slate-700' : '' }}
                    {{ $billingStatus === 'free' ? 'bg-slate-100 text-slate-700' : '' }}
                ">
                    {{ str_replace('_', ' ', $billingStatus) }}
                </span>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-semibold text-slate-900">Monitors</div>
                    <div class="mt-1 text-sm text-slate-600">{{ $monitorCount }} of {{ $monitorLimit }} used</div>
                </div>
                @if ($isTeamContext)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-sm font-semibold text-slate-900">Users</div>
                        <div class="mt-1 text-sm text-slate-600">{{ $userCount }} of {{ $userLimit }} used</div>
                    </div>
                @endif
            </div>

            <div class="mt-8">
                <div class="text-sm font-semibold text-slate-900">Billing interval</div>
                <div class="mt-3 inline-flex rounded-2xl border border-slate-200 bg-slate-50 p-1">
                    <button wire:click="$set('interval','monthly')"
                            wire:loading.attr="disabled"
                            class="rounded-2xl px-4 py-2 text-sm font-semibold transition
                                   {{ $interval==='monthly' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-600 hover:text-slate-900' }}">
                        Monthly
                    </button>
                    <button wire:click="$set('interval','yearly')"
                            wire:loading.attr="disabled"
                            class="rounded-2xl px-4 py-2 text-sm font-semibold transition
                                   {{ $interval==='yearly' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-600 hover:text-slate-900' }}">
                        Yearly
                    </button>
                </div>
            </div>

            <div class="mt-8">
                <div class="text-sm font-semibold text-slate-900">Choose your plan</div>

                <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-4">
                    @foreach (['free', 'pro', 'team', 'business'] as $key)
                        @php $p = $plansConfig[$key] ?? null; @endphp
                        <button wire:click="$set('selectedPlan','{{ $key }}')"
                                wire:loading.attr="disabled"
                                @disabled($key === $currentPlan || ! $this->canSelectPlan($key, app(\App\Services\Billing\BillingOwnerResolver::class)))
                                class="rounded-3xl border p-5 text-left transition
                                       {{ $selectedPlan===$key ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-white hover:bg-slate-50' }}
                                       {{ $selectedPlan===$key && $currentPlan===$key ? 'opacity-70' : '' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $p['name'] ?? strtoupper($key) }}</div>
                                    <div class="mt-1 text-xs text-slate-600">{{ $p['description'] ?? '' }}</div>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-800 border border-slate-200">
                                    {{ ($interval === 'yearly') ? '$'.($p['price_yearly'] ?? 0).'/yr' : '$'.($p['price_monthly'] ?? 0).'/mo' }}
                                </span>
                            </div>

                            @if (in_array($key, ['team', 'business'], true) && ! $isTeamContext)
                                <div class="mt-4 text-xs text-slate-500">Team features are available once you are part of a team.</div>
                            @else
                                <ul class="mt-4 space-y-2 text-xs text-slate-700">
                                    @foreach (($p['feature_list'] ?? []) as $line)
                                        <li class="flex items-start gap-2">
                                            <span class="mt-1 inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            <span>{{ $line }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if (!empty($p['popular']))
                                <div class="mt-4 text-xs font-semibold text-emerald-700">Most popular</div>
                            @endif
                            @if (!empty($p['best_value']))
                                <div class="mt-4 text-xs font-semibold text-emerald-700">Best value</div>
                            @endif
                            @if (in_array($key, ['team', 'business'], true) && ! $isTeamContext)
                                <div class="mt-4 text-xs font-semibold text-slate-500">Team plans require a team.</div>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="mt-10">
                <button wire:click="proceed"
                        wire:loading.attr="disabled"
                        @disabled($selectedPlan === $currentPlan || ! $canManage || $checkoutLocked)
                        class="inline-flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:opacity-60">
                    {{ $selectedPlan === $currentPlan ? 'Current plan' : ($selectedPlan === 'free' ? 'Downgrade to Free' : 'Continue to checkout') }}
                </button>

                <div class="mt-4 text-xs text-slate-500">
                    Upgrades take effect immediately. Downgrades apply at the end of your billing period.
                </div>
            </div>
        </div>
    </div>
</div>
