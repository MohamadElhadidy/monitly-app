<?php

namespace App\Jobs;

use App\Jobs\Notifications\SendSlackAlertJob;
use App\Jobs\Notifications\SendWebhookAlertJob;
use App\Jobs\Sla\EvaluateMonitorSlaJob;
use App\Mail\MonitorDownMail;
use App\Mail\MonitorRecoveredMail;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\NotificationChannel;
use App\Models\WebhookEndpoint;
use App\Services\Monitoring\MonitorHttpChecker;
use App\Services\Monitoring\MonitorIntervalResolver;
use App\Services\Notifications\MonitorAlertRecipientResolver;
use App\Services\Security\SsrfBlockedException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CheckMonitorUrl implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(public int $monitorId)
    {
        $this->onQueue('checks');
    }

    public function uniqueId(): string
    {
        return 'monitor-check:' . $this->monitorId;
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(
        MonitorHttpChecker $checker,
        MonitorIntervalResolver $intervalResolver,
        MonitorAlertRecipientResolver $recipientResolver
    ): void {
        $lock = Cache::lock('lock:monitor:check:' . $this->monitorId, 55);

        if (! $lock->get()) {
            return;
        }

        try {
            $monitor = Monitor::query()
                ->with(['team', 'owner'])
                ->find($this->monitorId);

            if (! $monitor) return;
            if ($monitor->paused) return;

            $now = now();
            $interval = $intervalResolver->resolveMinutes($monitor);
            $prevStatus = (string) ($monitor->last_status ?? 'unknown');

            try {
                $result = $checker->check($monitor->url);
            } catch (SsrfBlockedException $e) {
                $result = new \App\Services\Monitoring\CheckResult(
                    ok: false,
                    statusCode: null,
                    responseTimeMs: null,
                    errorCode: 'SSRF_BLOCKED',
                    errorMessage: $this->truncate((string) $e->getMessage()),
                    resolvedIp: null,
                    resolvedHost: null,
                    finalUrl: $monitor->url,
                    meta: ['blocked' => true],
                );
            } catch (Throwable $e) {
                $result = new \App\Services\Monitoring\CheckResult(
                    ok: false,
                    statusCode: null,
                    responseTimeMs: null,
                    errorCode: 'EXCEPTION',
                    errorMessage: $this->truncate((string) $e->getMessage()),
                    resolvedIp: null,
                    resolvedHost: null,
                    finalUrl: $monitor->url,
                    meta: ['exception' => get_class($e)],
                );
            }

            $check = new MonitorCheck();
            $check->monitor_id = $monitor->id;
            $check->checked_at = $now;
            $check->ok = (bool) $result->ok;
            $check->status_code = $result->statusCode;
            $check->response_time_ms = $result->responseTimeMs;
            $check->error_code = $result->errorCode;
            $check->error_message = $result->errorMessage;
            $check->resolved_ip = $result->resolvedIp;
            $check->resolved_host = $result->resolvedHost;
            $check->raw_response_meta = $result->meta + ['final_url' => $result->finalUrl];
            $check->save();

            $emailsEnabled = (bool) $monitor->email_alerts_enabled;

            $eligibleEmails = $recipientResolver->resolveEmails($monitor);
            $hasEligibleRecipients = count($eligibleEmails) > 0;

            $teamCanNotify = false;
            $channel = null;

            if ($monitor->team_id && $monitor->team && strtolower((string) $monitor->team->billing_plan) === 'team') {
                $teamCanNotify = true;

                $channel = NotificationChannel::query()->firstOrCreate(
                    ['team_id' => $monitor->team->id],
                    ['email_enabled' => true, 'slack_enabled' => false, 'webhooks_enabled' => false]
                );
            }

            $transitioned = false;

            if ($result->ok) {
                $isRecovery = ($prevStatus === 'down');

                $closedIncident = null;

                if ($isRecovery) {
                    $open = Incident::query()
                        ->where('monitor_id', $monitor->id)
                        ->whereNull('recovered_at')
                        ->orderByDesc('started_at')
                        ->first();

                    if ($open) {
                        $open->recovered_at = $now;
                        $open->downtime_seconds = $open->started_at ? $open->started_at->diffInSeconds($now) : null;
                        $open->save();
                        $closedIncident = $open;
                    }
                }

                $monitor->last_status = 'up';
                $monitor->consecutive_failures = 0;

                if ($isRecovery && $emailsEnabled && $closedIncident) {
                    foreach ($eligibleEmails as $email) {
                        Mail::to($email)->queue(new MonitorRecoveredMail($monitor, $closedIncident));
                    }
                }

                if ($isRecovery && $teamCanNotify && $closedIncident && $hasEligibleRecipients) {
                    if ($channel && $channel->slack_enabled && $monitor->slack_alerts_enabled) {
                        SendSlackAlertJob::dispatch($monitor->team->id, $monitor->id, (int) $closedIncident->id, 'monitor.recovered')
                            ->onQueue('notifications');
                    }

                    if ($channel && $channel->webhooks_enabled && $monitor->webhook_alerts_enabled) {
                        $endpoints = WebhookEndpoint::query()
                            ->where('team_id', $monitor->team->id)
                            ->where('enabled', true)
                            ->get(['id']);

                        foreach ($endpoints as $ep) {
                            SendWebhookAlertJob::dispatch((int) $ep->id, $monitor->id, (int) $closedIncident->id, 'monitor.recovered')
                                ->onQueue('notifications');
                        }
                    }
                }

                if ($isRecovery) {
                    $transitioned = true;
                }
            } else {
                $monitor->consecutive_failures = min(255, ((int) $monitor->consecutive_failures) + 1);

                if ($monitor->consecutive_failures === 1 && $prevStatus !== 'down') {
                    $monitor->last_status = 'degraded';
                }

                $isDownTransition = ($monitor->consecutive_failures >= 2 && $prevStatus !== 'down');

                if ($monitor->consecutive_failures >= 2) {
                    $monitor->last_status = 'down';

                    if ($isDownTransition) {
                        $open = Incident::query()
                            ->where('monitor_id', $monitor->id)
                            ->whereNull('recovered_at')
                            ->first();

                        if (! $open) {
                            $incident = new Incident();
                            $incident->monitor_id = $monitor->id;
                            $incident->started_at = $now;
                            $incident->recovered_at = null;
                            $incident->downtime_seconds = null;

                            $cause = $result->errorCode ?: 'DOWN';
                            if ($result->statusCode) {
                                $cause = "HTTP {$result->statusCode}";
                            }

                            $incident->cause_summary = $this->truncateCause($cause);
                            $incident->created_by = 'system';
                            $incident->sla_counted = true;
                            $incident->save();

                            if ($emailsEnabled) {
                                foreach ($eligibleEmails as $email) {
                                    Mail::to($email)->queue(new MonitorDownMail($monitor, $incident));
                                }
                            }

                            if ($teamCanNotify && $hasEligibleRecipients) {
                                if ($channel && $channel->slack_enabled && $monitor->slack_alerts_enabled) {
                                    SendSlackAlertJob::dispatch($monitor->team->id, $monitor->id, (int) $incident->id, 'monitor.down')
                                        ->onQueue('notifications');
                                }

                                if ($channel && $channel->webhooks_enabled && $monitor->webhook_alerts_enabled) {
                                    $endpoints = WebhookEndpoint::query()
                                        ->where('team_id', $monitor->team->id)
                                        ->where('enabled', true)
                                        ->get(['id']);

                                    foreach ($endpoints as $ep) {
                                        SendWebhookAlertJob::dispatch((int) $ep->id, $monitor->id, (int) $incident->id, 'monitor.down')
                                            ->onQueue('notifications');
                                    }
                                }
                            }

                            $transitioned = true;
                        }
                    }
                }
            }

            $monitor->next_check_at = $now->copy()->addMinutes($interval);
            $monitor->save();

            // Evaluate SLA quickly after state transitions (DOWN/RECOVERED) so breach alerts are timely
            if ($transitioned) {
                EvaluateMonitorSlaJob::dispatch((int) $monitor->id)->onQueue('sla');
            }
        } finally {
            optional($lock)->release();
        }
    }

    private function truncate(string $msg): string
    {
        $max = (int) config('monitly.http.max_error_message_len', 500);
        $msg = trim($msg);
        if ($msg === '') return 'Request failed.';
        if (mb_strlen($msg) <= $max) return $msg;
        return mb_substr($msg, 0, $max);
    }

    private function truncateCause(string $msg): string
    {
        $max = (int) config('monitly.http.max_cause_len', 255);
        $msg = trim($msg);
        if ($msg === '') return 'Incident';
        if (mb_strlen($msg) <= $max) return $msg;
        return mb_substr($msg, 0, $max);
    }
}
