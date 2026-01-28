<?php

namespace App\Jobs\Notifications;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\WebhookEndpoint;
use App\Services\Notifications\AlertPayloadBuilder;
use App\Services\Security\SsrfGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendWebhookAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;
    public int $timeout = 20;

    public function __construct(
        public int $endpointId,
        public int $monitorId,
        public ?int $incidentId,
        public string $event, // monitor.down | monitor.recovered
    ) {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [30, 60, 120, 240, 480, 900, 1800, 3600];
    }

    public function handle(AlertPayloadBuilder $builder, SsrfGuard $ssrfGuard): void
    {
        $endpoint = WebhookEndpoint::query()->find($this->endpointId);
        if (! $endpoint) return;
        if (! $endpoint->enabled) return;

        $team = Team::query()->find($endpoint->team_id);
        if (! $team) return;

        // Hard plan enforcement: only Team plan may use Webhooks
        if (strtolower((string) $team->billing_plan) !== 'team') {
            return;
        }

        $url = (string) ($endpoint->url ?? '');
        if ($url === '') return;

        // Protect outbound calls from internal networks (SSRF prevention for webhooks too)
        $ssrfGuard->validateUrl($url);

        $monitor = Monitor::query()->find($this->monitorId);
        if (! $monitor) return;

        $incident = $this->incidentId ? Incident::query()->find($this->incidentId) : null;

        $payload = $builder->build($this->event, $monitor, $incident);

        $timestamp = (string) time();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if (! is_string($body)) {
            $this->rememberFailure($endpoint, 'Failed to encode JSON payload.');
            throw new \RuntimeException('Failed to encode JSON payload.');
        }

        $secret = (string) ($endpoint->secret ?? '');
        if ($secret === '') {
            $this->rememberFailure($endpoint, 'Webhook secret is missing.');
            throw new \RuntimeException('Webhook secret is missing.');
        }

        $signature = 'v1=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        try {
            $resp = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'MonitlyWebhook/1.0',
                    'Content-Type' => 'application/json',
                    'X-Monitly-Event' => $this->event,
                    'X-Monitly-Timestamp' => $timestamp,
                    'X-Monitly-Signature' => $signature,
                ])
                ->send('POST', $url, [
                    'body' => $body,
                ]);

            if (! $resp->successful()) {
                $this->rememberFailure($endpoint, "HTTP ".$resp->status().": ".$this->truncate($resp->body()), $resp->status());
                throw new \RuntimeException('Webhook request failed: HTTP '.$resp->status());
            }

            $endpoint->last_error = null;
            $endpoint->retry_meta = [
                'attempt' => (int) $this->attempts(),
                'last_success_at' => now()->toIso8601String(),
                'last_status_code' => (int) $resp->status(),
            ];
            $endpoint->save();
        } catch (Throwable $e) {
            $this->rememberFailure($endpoint, $this->truncate($e->getMessage()));
            throw $e;
        }
    }

    private function rememberFailure(WebhookEndpoint $endpoint, string $error, ?int $statusCode = null): void
    {
        $endpoint->last_error = $error;
        $endpoint->retry_meta = [
            'attempt' => (int) $this->attempts(),
            'last_failed_at' => now()->toIso8601String(),
            'last_status_code' => $statusCode,
        ];
        $endpoint->save();
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
