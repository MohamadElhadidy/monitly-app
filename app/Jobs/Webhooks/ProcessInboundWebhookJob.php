<?php

namespace App\Jobs\Webhooks;

use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInboundWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 20;

    public function __construct(
        public string $provider,
        public string $eventId,
        public array $payload
    ) {
        $this->onQueue('webhooks_in');
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 240];
    }

    public function handle(): void
    {
        $event = WebhookEvent::query()->firstOrCreate([
            'provider' => $this->provider,
            'event_id' => $this->eventId,
        ], [
            'payload' => $this->payload,
            'received_at' => now(),
        ]);

        if (! $event->processed_at) {
            $event->processed_at = now();
            $event->save();
        }
    }
}
