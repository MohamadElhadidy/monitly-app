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

        $record = BillingWebhookEvent::create([
            'event_type' => $payload['event_type'] ?? 'unknown',
            'payload'    => $payload,
            'processed'  => false,
            'signature_valid' => true, // ← ADD THIS LINE! If WebhookHandled fired, signature is valid
        ]);

        ProcessPaddleWebhookJob::dispatch($record->id);
    }
}