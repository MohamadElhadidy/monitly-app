<?php

namespace App\Jobs\Notifications;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendSlackSlaBreachJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;
    public int $timeout = 20;

    /**
     * @param array $stats Output of SlaCalculator::forMonitor()
     */
    public function __construct(
        public int $teamId,
        public int $monitorId,
        public array $stats,
        public float $targetPct,
    ) {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [30, 60, 120, 240, 480, 900, 1800, 3600];
    }

    public function handle(): void
    {
        $team = Team::query()->find($this->teamId);
        if (! $team) return;

        if (strtolower((string) $team->billing_plan) !== 'team') return;

        $channel = NotificationChannel::query()->firstOrCreate(
            ['team_id' => $team->id],
            ['email_enabled' => true, 'slack_enabled' => false, 'webhooks_enabled' => false]
        );

        if (! $channel->slack_enabled) return;

        $webhookUrl = (string) ($channel->slack_webhook_url ?? '');
        if ($webhookUrl === '') return;

        $monitor = Monitor::query()->find($this->monitorId);
        if (! $monitor) return;

        $uptime = number_format((float) ($this->stats['uptime_pct'] ?? 0), 4);
        $downtime = $this->humanDuration((int) ($this->stats['downtime_seconds'] ?? 0));
        $incidents = (int) ($this->stats['incident_count'] ?? 0);
        $mttr = $this->stats['mttr_seconds'] ? $this->humanDuration((int) $this->stats['mttr_seconds']) : '—';

        $title = '⚠️ SLA Breach Detected';
        $text = "*{$monitor->name}* is below SLA target\n".
            "{$monitor->url}\n".
            "Uptime (30d): *{$uptime}%* (Target: *".number_format($this->targetPct, 1)."%*)\n".
            "Downtime: {$downtime} • Incidents: {$incidents} • MTTR: {$mttr}\n";

        $monitorUrl = rtrim((string) config('app.url'), '/') . '/app/monitors/' . $monitor->id;

        try {
            $resp = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'MonitlySlack/1.0'])
                ->post($webhookUrl, [
                    'text' => $text,
                    'blocks' => [
                        [
                            'type' => 'header',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => $title,
                                'emoji' => true,
                            ],
                        ],
                        [
                            'type' => 'section',
                            'text' => [
                                'type' => 'mrkdwn',
                                'text' => $text,
                            ],
                        ],
                        [
                            'type' => 'actions',
                            'elements' => [
                                [
                                    'type' => 'button',
                                    'text' => [
                                        'type' => 'plain_text',
                                        'text' => 'View monitor',
                                        'emoji' => true,
                                    ],
                                    'url' => $monitorUrl,
                                ],
                            ],
                        ],
                        [
                            'type' => 'context',
                            'elements' => [
                                [
                                    'type' => 'mrkdwn',
                                    'text' => 'Event: `monitor.sla_breached` • Team: `'.$team->name.'` • Monitor ID: `'.$monitor->id.'`',
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $resp->successful()) {
                $channel->slack_last_error = "SLA breach Slack HTTP ".$resp->status().": ".$this->truncate($resp->body());
                $channel->slack_retry_meta = [
                    'attempt' => (int) $this->attempts(),
                    'last_failed_at' => now()->toIso8601String(),
                ];
                $channel->save();
                throw new \RuntimeException('Slack webhook request failed: HTTP '.$resp->status());
            }

            $channel->slack_last_error = null;
            $channel->slack_retry_meta = [
                'attempt' => (int) $this->attempts(),
                'last_success_at' => now()->toIso8601String(),
            ];
            $channel->save();
        } catch (Throwable $e) {
            $channel->slack_last_error = $this->truncate($e->getMessage());
            $channel->slack_retry_meta = [
                'attempt' => (int) $this->attempts(),
                'last_failed_at' => now()->toIso8601String(),
            ];
            $channel->save();
            throw $e;
        }
    }

    private function humanDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds === 0) return '0s';

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        $parts = [];
        if ($h > 0) $parts[] = "{$h}h";
        if ($m > 0) $parts[] = "{$m}m";
        if ($s > 0) $parts[] = "{$s}s";

        return implode(' ', $parts);
    }

    private function truncate(string $msg): string
    {
        $msg = trim($msg);
        if ($msg === '') return 'Request failed.';
        if (mb_strlen($msg) <= 500) return $msg;
        return mb_substr($msg, 0, 500);
    }
}
