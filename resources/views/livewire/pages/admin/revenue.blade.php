<?php

use App\Services\Admin\AdminMetrics;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Revenue')]
class extends Component
{
    public bool $loadError = false;

    public function exportCsv(AdminMetrics $metrics)
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $rows = DB::table('transactions')
            ->whereBetween('billed_at', [$start, $end])
            ->orderByDesc('billed_at')
            ->get(['billed_at', 'total', 'currency', 'status', 'paddle_subscription_id']);

        $headers = ['billed_at', 'total', 'currency', 'status', 'paddle_subscription_id'];

        return response()->streamDownload(function () use ($rows, $headers) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->billed_at,
                    $row->total,
                    $row->currency,
                    $row->status,
                    $row->paddle_subscription_id,
                ]);
            }
            fclose($handle);
        }, 'revenue-mtd.csv');
    }

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function with(AdminMetrics $metrics): array
    {
        $mrrArr = $metrics->mrrArrEstimate();
        $refunds = $metrics->refundTotals(now()->startOfMonth(), now()->endOfMonth());

        $chartData = DB::table('transactions')
            ->selectRaw('DATE(billed_at) as day, SUM(CAST(total as DECIMAL(10,2))) as total')
            ->whereIn('status', ['paid', 'completed'])
            ->where('billed_at', '>=', now()->subDays(14))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => ['day' => $row->day, 'total' => (float) $row->total])
            ->all();

        $activeSubscriptions = DB::table('subscriptions')->whereIn('status', ['active', 'past_due', 'canceling'])->count();
        $canceledSubscriptions = DB::table('subscriptions')->where('status', 'canceled')->where('updated_at', '>=', now()->subDays(30))->count();
        $churn = $activeSubscriptions > 0 ? round(($canceledSubscriptions / $activeSubscriptions) * 100, 2) : 0;

        $planBreakdown = $metrics->activeSubscriptionsByPlan();

        return [
            'mrr' => $mrrArr['mrr'],
            'arr' => $mrrArr['arr'],
            'refunds' => $refunds,
            'chartData' => $chartData,
            'churn' => $churn,
            'planBreakdown' => $planBreakdown,
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Revenue</h1>
            <p class="text-sm text-slate-600">MRR, ARR, churn, and plan breakdown.</p>
        </div>
        <button wire:click="exportCsv" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Export CSV</button>
    </div>

    @if($loadError)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Unable to load revenue metrics.
            <button wire:click="refreshPage" class="ml-3 rounded border border-rose-300 px-2 py-1 text-xs font-semibold">Retry</button>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">MRR</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">${{ number_format($mrr, 2) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">ARR</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">${{ number_format($arr, 2) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">Churn (30d)</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $churn }}%</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">Refunds (MTD)</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">${{ number_format($refunds, 2) }}</div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between">
            <div class="text-sm font-semibold text-slate-900">Revenue (Last 14 Days)</div>
            <span class="text-xs text-slate-500">Completed transactions</span>
        </div>
        <div class="mt-4 grid gap-3">
            @forelse($chartData as $row)
                <div>
                    <div class="flex items-center justify-between text-xs text-slate-500">
                        <span>{{ $row['day'] }}</span>
                        <span>${{ number_format($row['total'], 2) }}</span>
                    </div>
                    <div class="mt-1 h-2 rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-slate-900" style="width: {{ min(100, $row['total'] > 0 ? $row['total'] * 5 : 2) }}%"></div>
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-500">No revenue data yet.</div>
            @endforelse
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="text-sm font-semibold text-slate-900">Plan Breakdown</div>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(['free','pro','team','business'] as $plan)
                <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                    <div class="text-xs uppercase text-slate-500">{{ ucfirst($plan) }}</div>
                    <div class="mt-1 text-lg font-semibold text-slate-900">
                        {{ ($planBreakdown['user'][$plan] ?? 0) + ($planBreakdown['team'][$plan] ?? 0) }} active
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
