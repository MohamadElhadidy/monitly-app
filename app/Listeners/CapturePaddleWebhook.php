<?php

namespace App\Listeners;

use Laravel\Paddle\Events\WebhookHandled;
use App\Models\BillingWebhookEvent;
use App\Jobs\Billing\ProcessPaddleWebhookJob;

class CapturePaddleWebhook
{
    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;

        $eventId = (string) ($payload['event_id'] ?? $payload['id'] ?? '');
        if ($eventId === '') {
            $eventId = hash('sha256', json_encode($payload));
        }

        $record = BillingWebhookEvent::firstOrCreate(
            [
                'provider' => 'paddle',
                'event_id' => $eventId,
            ],
            [
                'event_type' => $payload['event_type'] ?? 'unknown',
                'payload' => $payload,
                'signature_valid' => true,
            ]
        );

        if ($record->wasRecentlyCreated) {
            ProcessPaddleWebhookJob::dispatch($record->id);
        }
    }
}
