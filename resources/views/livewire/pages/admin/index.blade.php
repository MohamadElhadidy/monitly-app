<?php

use App\Services\Admin\AdminMetrics;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Overview')]
class extends Component
{
    public bool $loadError = false;

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function with(AdminMetrics $metrics): array
    {
        $todayRevenue = $metrics->revenueTotals(now()->startOfDay(), now()->endOfDay());
        $mtdRevenue = $metrics->revenueTotals(now()->startOfMonth(), now()->endOfMonth());
        $refundsMtd = $metrics->refundTotals(now()->startOfMonth(), now()->endOfMonth());

        $planBreakdown = $metrics->activeSubscriptionsByPlan();
        $mrrArr = $metrics->mrrArrEstimate();
        $queueSummary = $metrics->queueBacklogSummary();

        return [
            'todayRevenue' => $todayRevenue,
            'mtdRevenue' => $mtdRevenue,
            'mrr' => $mrrArr['mrr'],
            'arr' => $mrrArr['arr'],
            'planBreakdown' => $planBreakdown,
            'newUsers' => $metrics->newUsersToday(),
            'paymentFailures' => app(AdminMetrics::class)->navBadges()['payment_failures'] ?? 0,
            'refundsCount' => $refundsMtd,
            'queueSummary' => $queueSummary,
            'oldestFailedJobAge' => $metrics->oldestFailedJobAgeMinutes(),
            'webhookFailures' => app(AdminMetrics::class)->navBadges()['webhook_failures'] ?? 0,
            'errorsLast24h' => $metrics->errorsLast24h(),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Overview</h1>
            <p class="text-sm text-slate-600">Revenue, subscription health, and system status.</p>
        </div>
    </div>

    @if($loadError)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Failed to load admin metrics.
            <button wire:click="refreshPage" class="ml-3 rounded border border-rose-300 px-2 py-1 text-xs font-semibold">Retry</button>
        </div>
    @endif

    <div wire:loading class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(range(1, 8) as $i)
            <div class="h-24 animate-pulse rounded-lg bg-slate-200"></div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" wire:loading.remove>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">Today Revenue</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">${{ number_format($todayRevenue, 2) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">MTD Revenue</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">${{ number_format($mtdRevenue, 2) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">MRR</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">${{ number_format($mrr, 2) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">ARR</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">${{ number_format($arr, 2) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
            <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-900">Active Subscriptions by Plan</div>
                <span class="text-xs text-slate-500">Users + Teams</span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach(['free','pro','team','business'] as $plan)
                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div class="text-xs uppercase text-slate-500">{{ ucfirst($plan) }}</div>
                        <div class="mt-1 text-lg font-semibold text-slate-900">
                            {{ ($planBreakdown['user'][$plan] ?? 0) + ($planBreakdown['team'][$plan] ?? 0) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-sm font-semibold text-slate-900">Daily Highlights</div>
            <dl class="mt-3 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">New users today</dt>
                    <dd class="font-semibold text-slate-900">{{ $newUsers }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Payment failures</dt>
                    <dd class="font-semibold text-rose-600">{{ $paymentFailures }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Refunds (MTD)</dt>
                    <dd class="font-semibold text-slate-900">${{ number_format($refundsCount, 2) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Webhook failures</dt>
                    <dd class="font-semibold text-amber-600">{{ $webhookFailures }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Errors last 24h</dt>
                    <dd class="font-semibold text-slate-900">{{ $errorsLast24h }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-sm font-semibold text-slate-900">Queue Backlog</div>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Waiting jobs</dt>
                    <dd class="font-semibold text-slate-900">{{ $queueSummary['waiting'] }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Failed jobs</dt>
                    <dd class="font-semibold text-rose-600">{{ $queueSummary['failed'] }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Oldest job age</dt>
                    <dd class="font-semibold text-slate-900">
                        {{ $queueSummary['oldest_job_age_minutes'] ? $queueSummary['oldest_job_age_minutes'] . ' min' : '—' }}
                    </dd>
                </div>
            </dl>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-sm font-semibold text-slate-900">Oldest Failed Job</div>
            <div class="mt-3 text-sm text-slate-600">Age since the earliest failed job.</div>
            <div class="mt-4 text-2xl font-semibold text-slate-900">
                {{ $oldestFailedJobAge ? $oldestFailedJobAge . ' min' : 'No failed jobs' }}
            </div>
        </div>
    </div>
</div>
