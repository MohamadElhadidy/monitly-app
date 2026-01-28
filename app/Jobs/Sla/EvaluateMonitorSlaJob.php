<?php

namespace App\Jobs\Sla;

use App\Jobs\Notifications\SendSlackSlaBreachJob;
use App\Mail\MonitorSlaBreachMail;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Services\Notifications\MonitorAlertRecipientResolver;
use App\Services\Sla\SlaCalculator;
use App\Services\Sla\SlaTargetResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class EvaluateMonitorSlaJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public int $monitorId)
    {
        $this->onQueue('sla');
    }

    public function uniqueId(): string
    {
        return 'sla-eval:' . $this->monitorId;
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(
        SlaCalculator $calculator,
        SlaTargetResolver $targets,
        MonitorAlertRecipientResolver $recipientResolver
    ): void {
        $lock = Cache::lock('lock:monitor:sla:' . $this->monitorId, 55);
        if (! $lock->get()) return;

        try {
            $monitor = Monitor::query()
                ->with(['team', 'owner'])
                ->find($this->monitorId);

            if (! $monitor) return;

            $now = now();
            $stats = $calculator->forMonitor($monitor, $now, 30);

            $target = $targets->targetPctForMonitor($monitor);
            $breached = ((float) $stats['uptime_pct']) < $target;

            // Persist latest computed stats (used for admin dashboards / quick display)
            $monitor->sla_uptime_pct_30d = $stats['uptime_pct'];
            $monitor->sla_downtime_seconds_30d = $stats['downtime_seconds'];
            $monitor->sla_incident_count_30d = $stats['incident_count'];
            $monitor->sla_mttr_seconds_30d = $stats['mttr_seconds'];
            $monitor->sla_last_calculated_at = $now;

            $wasBreached = (bool) $monitor->sla_breached;
            $monitor->sla_breached = $breached;

            $monitor->save();

            // Only alert on breach transition (dedupe)
            if (! $breached || $wasBreached) {
                return;
            }

            // Do not alert for paused monitors (avoid surprises)
            if ($monitor->paused) {
                return;
            }

            // Respect monitor-level email alerts toggle for breach notifications
            if (! (bool) $monitor->email_alerts_enabled) {
                return;
            }

            $emails = $recipientResolver->resolveEmails($monitor);
            if (count($emails) === 0) {
                return;
            }

            foreach ($emails as $email) {
                Mail::to($email)->queue(new MonitorSlaBreachMail($monitor, $stats, $target));
            }

            $monitor->sla_last_breach_alert_at = $now;
            $monitor->save();

            // Slack breach alert: Team plan only + channel enabled + per-monitor slack toggle
            if ($monitor->team_id && $monitor->team && strtolower((string) $monitor->team->billing_plan) === 'team') {
                if (! (bool) $monitor->slack_alerts_enabled) return;

                $channel = NotificationChannel::query()->firstOrCreate(
                    ['team_id' => $monitor->team_id],
                    ['email_enabled' => true, 'slack_enabled' => false, 'webhooks_enabled' => false]
                );

                if (! $channel->slack_enabled) return;
                if (! $channel->slack_webhook_url) return;

                SendSlackSlaBreachJob::dispatch($monitor->team_id, $monitor->id, $stats, $target)->onQueue('notifications');
            }
        } finally {
            optional($lock)->release();
        }
    }
}
