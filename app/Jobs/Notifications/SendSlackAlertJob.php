<?php

namespace App\Jobs\Notifications;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\Team;
use App\Services\Notifications\AlertPayloadBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendSlackAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;
    public int $timeout = 20;

    public function __construct(
        public int $teamId,
        public int $monitorId,
        public ?int $incidentId,
        public string $event, // monitor.down | monitor.recovered
    ) {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        // Exponential-ish backoff
        return [30, 60, 120, 240, 480, 900, 1800, 3600];
    }

    public function handle(AlertPayloadBuilder $builder): void
    {
        $team = Team::query()->find($this->teamId);
        if (! $team) return;

        // Hard plan enforcement: only Team plan may use Slack
        if (strtolower((string) $team->billing_plan) !== 'team') {
            return;
        }

        $channel = NotificationChannel::query()->firstOrCreate(
            ['team_id' => $team->id],
            [
                'email_enabled' => true,
                'slack_enabled' => false,
                'webhooks_enabled' => false,
            ]
        );

        if (! $channel->slack_enabled) return;

        $webhookUrl = (string) ($channel->slack_webhook_url ?? '');
        if ($webhookUrl === '') return;

        $monitor = Monitor::query()->find($this->monitorId);
        if (! $monitor) return;

        $incident = $this->incidentId ? Incident::query()->find($this->incidentId) : null;

        $payload = $builder->build($this->event, $monitor, $incident);

        $title = $this->event === 'monitor.down' ? '🔴 Monitor Down' : '✅ Monitor Recovered';
        $dur = $builder->humanDuration($payload['incident']['downtime_seconds'] ?? 0);

        $text = $this->event === 'monitor.down'
            ? "*{$monitor->name}* is DOWN\n{$monitor->url}\nDetected: " . ($payload['incident']['started_at'] ?? '—')
            : "*{$monitor->name}* recovered\n{$monitor->url}\nDowntime: {$dur}";

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
                            'type' => 'context',
                            'elements' => [
                                [
                                    'type' => 'mrkdwn',
                                    'text' => 'Event: `'.$this->event.'` • Team: `'.$team->name.'` • Monitor ID: `'.$monitor->id.'`',
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $resp->successful()) {
                $this->rememberFailure($channel, "Slack HTTP ".$resp->status().": ".$this->truncate($resp->body()));
                throw new \RuntimeException('Slack webhook request failed: HTTP '.$resp->status());
            }

            $channel->slack_last_error = null;
            $channel->slack_retry_meta = [
                'attempt' => (int) $this->attempts(),
                'last_success_at' => now()->toIso8601String(),
            ];
            $channel->save();
        } catch (Throwable $e) {
            $this->rememberFailure($channel, $this->truncate($e->getMessage()));
            throw $e;
        }
    }

    private function rememberFailure(NotificationChannel $channel, string $error): void
    {
        $channel->slack_last_error = $error;
        $channel->slack_retry_meta = [
            'attempt' => (int) $this->attempts(),
            'last_failed_at' => now()->toIso8601String(),
        ];
        $channel->save();
    }

    private function truncate(string $msg): string
    {
        $max = (int) config('monitly.http.max_error_message_len', 500);
        $msg = trim($msg);
        if ($msg === '') return 'Request failed.';
        if (mb_strlen($msg) <= $max) return $msg;
        return mb_substr($msg, 0, $max);
    }
}
