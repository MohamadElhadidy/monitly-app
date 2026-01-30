<?php

namespace App\Services\Admin;

use App\Models\BillingWebhookEvent;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminMetrics
{
    public function navBadges(): array
    {
        $failedJobs = DB::table('failed_jobs')->count();
        $webhookFailures = BillingWebhookEvent::query()->whereNotNull('processing_error')->count();
        $paymentFailures = DB::table('transactions')->whereIn('status', ['failed', 'past_due'])->count();

        return [
            'failed_jobs' => $failedJobs,
            'webhook_failures' => $webhookFailures,
            'payment_failures' => $paymentFailures,
        ];
    }

    public function revenueTotals(?CarbonInterface $start = null, ?CarbonInterface $end = null): float
    {
        $query = DB::table('transactions')->whereIn('status', ['paid', 'completed']);

        if ($start) {
            $query->where('billed_at', '>=', $start);
        }

        if ($end) {
            $query->where('billed_at', '<=', $end);
        }

        return (float) $query->sum(DB::raw('CAST(total as DECIMAL(10,2))'));
    }

    public function refundTotals(?CarbonInterface $start = null, ?CarbonInterface $end = null): float
    {
        if (! Schema::hasTable('billing_refunds')) {
            return 0.0;
        }

        $query = DB::table('billing_refunds');

        if ($start) {
            $query->where('refunded_at', '>=', $start);
        }

        if ($end) {
            $query->where('refunded_at', '<=', $end);
        }

        return (float) $query->sum(DB::raw('CAST(amount as DECIMAL(10,2))'));
    }

    public function activeSubscriptionsByPlan(): array
    {
        $userPlans = User::query()
            ->select('billing_plan', DB::raw('count(*) as total'))
            ->whereIn('billing_status', ['active', 'past_due', 'canceling'])
            ->groupBy('billing_plan')
            ->pluck('total', 'billing_plan')
            ->all();

        $teamPlans = Team::query()
            ->select('billing_plan', DB::raw('count(*) as total'))
            ->whereIn('billing_status', ['active', 'past_due', 'canceling'])
            ->groupBy('billing_plan')
            ->pluck('total', 'billing_plan')
            ->all();

        return [
            'user' => $userPlans,
            'team' => $teamPlans,
        ];
    }

    public function mrrArrEstimate(): array
    {
        $plans = config('billing.plans');
        $userCounts = User::query()->whereIn('billing_status', ['active', 'past_due', 'canceling'])->get()->groupBy('billing_plan');
        $teamCounts = Team::query()->whereIn('billing_status', ['active', 'past_due', 'canceling'])->get()->groupBy('billing_plan');

        $mrr = 0.0;

        foreach (['free', 'pro', 'team', 'business'] as $plan) {
            $monthly = (float) ($plans[$plan]['price_monthly'] ?? 0);
            $mrr += $monthly * ($userCounts[$plan]?->count() ?? 0);
            $mrr += $monthly * ($teamCounts[$plan]?->count() ?? 0);
        }

        return [
            'mrr' => round($mrr, 2),
            'arr' => round($mrr * 12, 2),
        ];
    }

    public function queueBacklogSummary(): array
    {
        $waiting = DB::table('jobs')->whereNull('reserved_at')->count();
        $failed = DB::table('failed_jobs')->count();
        $oldestJob = DB::table('jobs')->orderBy('available_at')->value('available_at');

        return [
            'waiting' => $waiting,
            'failed' => $failed,
            'oldest_job_age_minutes' => $oldestJob ? now()->diffInMinutes(Carbon::createFromTimestamp($oldestJob)) : null,
        ];
    }

    public function oldestFailedJobAgeMinutes(): ?int
    {
        $failedAt = DB::table('failed_jobs')->orderBy('failed_at')->value('failed_at');

        if (! $failedAt) {
            return null;
        }

        return now()->diffInMinutes(Carbon::parse($failedAt));
    }

    public function errorsLast24h(): int
    {
        return DB::table('app_errors')->where('last_seen_at', '>=', now()->subDay())->sum('count');
    }

    public function newUsersToday(): int
    {
        return User::query()->whereDate('created_at', now())->count();
    }

    public function monitorUsage(): array
    {
        return [
            'total' => Monitor::query()->count(),
        ];
    }
}
