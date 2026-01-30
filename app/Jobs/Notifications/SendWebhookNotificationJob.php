<?php

namespace App\Jobs\Notifications;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\WebhookEndpoint;
use App\Services\Notifications\AlertPayloadBuilder;
use App\Jobs\Webhooks\SendOutboundWebhookJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWebhookNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 20;

    public function __construct(
        public int $deliveryId,
        public int $endpointId,
        public int $monitorId,
        public int $incidentId,
        public string $event
    ) {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [30, 60, 120, 240, 480];
    }

    public function handle(AlertPayloadBuilder $builder): void
    {
        $endpoint = WebhookEndpoint::query()->find($this->endpointId);
        if (! $endpoint || ! $endpoint->enabled) {
            return;
        }

        $monitor = Monitor::query()->find($this->monitorId);
        $incident = Incident::query()->find($this->incidentId);
        if (! $monitor || ! $incident) {
            return;
        }

        $payload = $builder->build($this->event, $monitor, $incident);

        SendOutboundWebhookJob::dispatch(
            deliveryId: $this->deliveryId,
            endpointId: $endpoint->id,
            payload: $payload
        )->onQueue('webhooks_out');
    }
}
