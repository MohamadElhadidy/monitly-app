<?php

namespace App\Jobs\Webhooks;

use App\Models\NotificationDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendOutboundWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;
    public int $timeout = 20;

    public function __construct(
        public int $deliveryId,
        public int $endpointId,
        public array $payload
    ) {
        $this->tries = (int) config('monitly.webhooks.max_attempts', 8);
        $this->onQueue('webhooks_out');
    }

    public function backoff(): array
    {
        $base = (int) config('monitly.webhooks.base_backoff_seconds', 10);
        return [$base, $base * 2, $base * 4, $base * 8, $base * 16, $base * 32, $base * 64, $base * 128];
    }

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);
        if (! $delivery || $delivery->sent_at) {
            return;
        }

        $endpoint = WebhookEndpoint::query()->find($this->endpointId);
        if (! $endpoint || ! $endpoint->enabled) {
            return;
        }

        try {
            $resp = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'MonitlyWebhook/1.0',
                    'X-Monitly-Event' => (string) ($this->payload['event'] ?? ''),
                    'X-Monitly-Delivery' => (string) $delivery->id,
                ])
                ->post($endpoint->url, $this->payload);

            if ($resp->status() >= 400 && $resp->status() < 500) {
                $endpoint->last_error = "Webhook HTTP ".$resp->status().": ".$this->truncate($resp->body());
                $endpoint->retry_meta = [
                    'attempt' => (int) $this->attempts(),
                    'last_failed_at' => now()->toIso8601String(),
                ];
                $endpoint->save();
                return;
            }

            if (! $resp->successful()) {
                $this->rememberFailure($endpoint, "Webhook HTTP ".$resp->status().": ".$this->truncate($resp->body()));
                throw new \RuntimeException('Webhook request failed: HTTP '.$resp->status());
            }

            $endpoint->last_error = null;
            $endpoint->retry_meta = [
                'attempt' => (int) $this->attempts(),
                'last_success_at' => now()->toIso8601String(),
            ];
            $endpoint->save();
        } catch (Throwable $e) {
            $this->rememberFailure($endpoint, $this->truncate($e->getMessage()));
            throw $e;
        }

        $delivery->sent_at = now();
        $delivery->save();
    }

    private function rememberFailure(WebhookEndpoint $endpoint, string $error): void
    {
        $endpoint->last_error = $error;
        $endpoint->retry_meta = [
            'attempt' => (int) $this->attempts(),
            'last_failed_at' => now()->toIso8601String(),
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
