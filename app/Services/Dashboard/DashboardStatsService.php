<?php

namespace App\Services\Dashboard;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use App\Services\Billing\PlanLimits;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    protected User $user;
    protected ?int $teamId = null;
    protected array $teamIds = [];

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->teamIds = $user->teams()->pluck('teams.id')->toArray();
        $this->teamId = $user->current_team_id;
    }

    /**
     * Get all dashboard stats in one call (cached for 60 seconds).
     */
    public function getAllStats(): array
    {
        $cacheKey = "dashboard_stats_{$this->user->id}";

        return Cache::remember($cacheKey, 60, function () {
            return [
                'monitors' => $this->getMonitorStats(),
                'incidents' => $this->getIncidentStats(),
                'performance' => $this->getPerformanceStats(),
                'checks' => $this->getCheckStats(),
                'plan' => $this->getPlanStats(),
                'recent_monitors' => $this->getRecentMonitors(),
                'recent_incidents' => $this->getRecentIncidents(),
            ];
        });
    }

    /**
     * Get monitor count statistics.
     */
    public function getMonitorStats(): array
    {
        $monitors = $this->getMonitorsQuery()->get();

        $total = $monitors->count();
        $up = $monitors->where('last_status', 'up')->count();
        $down = $monitors->where('last_status', 'down')->count();
        $degraded = $monitors->where('last_status', 'degraded')->count();
        $paused = $monitors->where('paused', true)->count();
        $pending = $monitors->where('last_status', 'pending')->count();

        return [
            'total' => $total,
            'up' => $up,
            'down' => $down,
            'degraded' => $degraded,
            'paused' => $paused,
            'pending' => $pending,
            'healthy_percent' => $total > 0 ? round(($up / $total) * 100, 1) : 100,
        ];
    }

    /**
     * Get incident statistics.
     */
    public function getIncidentStats(): array
    {
        $monitorIds = $this->getMonitorsQuery()->pluck('id');

        $openIncidents = Incident::whereIn('monitor_id', $monitorIds)
            ->whereNull('recovered_at')
            ->count();

        $last24h = Incident::whereIn('monitor_id', $monitorIds)
            ->where('started_at', '>=', now()->subDay())
            ->count();

        $last7d = Incident::whereIn('monitor_id', $monitorIds)
            ->where('started_at', '>=', now()->subDays(7))
            ->count();

        $avgResolutionMinutes = Incident::whereIn('monitor_id', $monitorIds)
            ->whereNotNull('recovered_at')
            ->where('started_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, recovered_at)) as avg_minutes')
            ->value('avg_minutes');

        return [
            'open' => $openIncidents,
            'last_24h' => $last24h,
            'last_7d' => $last7d,
            'avg_resolution_minutes' => $avgResolutionMinutes ? round($avgResolutionMinutes) : null,
        ];
    }

    /**
     * Get performance statistics (response times).
     */
    public function getPerformanceStats(): array
    {
        $monitorIds = $this->getMonitorsQuery()->pluck('id');

        // Average response time last 1 hour
        $avgResponse1h = MonitorCheck::whereIn('monitor_id', $monitorIds)
            ->where('checked_at', '>=', now()->subHour())
            ->where('response_time_ms', '>', 0)
            ->avg('response_time_ms');

        // Average response time last 24 hours
        $avgResponse24h = MonitorCheck::whereIn('monitor_id', $monitorIds)
            ->where('checked_at', '>=', now()->subDay())
            ->where('response_time_ms', '>', 0)
            ->avg('response_time_ms');

        return [
            'avg_response_1h_ms' => $avgResponse1h ? round($avgResponse1h) : null,
            'avg_response_24h_ms' => $avgResponse24h ? round($avgResponse24h) : null,
        ];
    }

    /**
     * Get check statistics.
     */
    public function getCheckStats(): array
    {
        $monitorIds = $this->getMonitorsQuery()->pluck('id');

        $checksLast24h = MonitorCheck::whereIn('monitor_id', $monitorIds)
            ->where('checked_at', '>=', now()->subDay())
            ->count();

        $successfulChecks = MonitorCheck::whereIn('monitor_id', $monitorIds)
            ->where('checked_at', '>=', now()->subDay())
            ->where('status', 'up')
            ->count();

        $uptime24h = $checksLast24h > 0 
            ? round(($successfulChecks / $checksLast24h) * 100, 2) 
            : 100;

        return [
            'total_24h' => $checksLast24h,
            'successful_24h' => $successfulChecks,
            'uptime_24h' => $uptime24h,
        ];
    }

    /**
     * Get plan and billing statistics.
     */
    public function getPlanStats(): array
    {
        $currentTeam = $this->user->currentTeam;
        $billable = $currentTeam && $currentTeam->paddle_subscription_id 
            ? $currentTeam 
            : $this->user;

        $plan = strtolower($billable->billing_plan ?? 'free');
        $status = strtolower($billable->billing_status ?? 'free');

        if ($currentTeam && $currentTeam->paddle_subscription_id) {
            $monitorLimit = PlanLimits::monitorLimitForTeam($currentTeam);
            $monitorCount = Monitor::where('team_id', $currentTeam->id)->count();
            $seatLimit = PlanLimits::seatLimitForTeam($currentTeam);
            $seatCount = $currentTeam->allUsers()->count();
        } else {
            $monitorLimit = PlanLimits::monitorLimitForUser($this->user);
            $monitorCount = Monitor::where('user_id', $this->user->id)
                ->whereNull('team_id')
                ->count();
            $seatLimit = 1;
            $seatCount = 1;
        }

        return [
            'name' => $plan,
            'status' => $status,
            'next_bill_at' => $billable->next_bill_at?->format('M d, Y'),
            'monitor_count' => $monitorCount,
            'monitor_limit' => $monitorLimit,
            'monitor_percent' => $monitorLimit > 0 ? round(($monitorCount / $monitorLimit) * 100) : 0,
            'seat_count' => $seatCount,
            'seat_limit' => $seatLimit,
            'is_subscribed' => in_array($status, ['active', 'past_due', 'canceling']),
            'is_team' => in_array($plan, ['team', 'business']),
        ];
    }

    /**
     * Get recent monitors with status.
     */
    public function getRecentMonitors(int $limit = 10): Collection
    {
        return $this->getMonitorsQuery()
            ->orderByRaw("FIELD(last_status, 'down', 'degraded', 'pending', 'up', 'paused') ASC")
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get(['id', 'name', 'url', 'last_status', 'last_check_at', 'last_response_time_ms', 'paused']);
    }

    /**
     * Get recent incidents.
     */
    public function getRecentIncidents(int $limit = 10): Collection
    {
        $monitorIds = $this->getMonitorsQuery()->pluck('id');

        return Incident::whereIn('monitor_id', $monitorIds)
            ->with('monitor:id,name,url')
            ->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get(['id', 'monitor_id', 'started_at', 'recovered_at', 'cause']);
    }

    /**
     * Get monitors query scoped to user/team.
     */
    protected function getMonitorsQuery()
    {
        return Monitor::query()
            ->where(function ($q) {
                $q->where('user_id', $this->user->id);
                if (!empty($this->teamIds)) {
                    $q->orWhereIn('team_id', $this->teamIds);
                }
            });
    }

    /**
     * Clear cached stats for user.
     */
    public function clearCache(): void
    {
        Cache::forget("dashboard_stats_{$this->user->id}");
    }
}
