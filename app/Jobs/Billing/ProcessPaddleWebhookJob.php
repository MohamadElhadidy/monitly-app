<?php

namespace App\Jobs\Billing;

use App\Models\BillingWebhookEvent;
use App\Services\Billing\PaddleWebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPaddleWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $eventId) {}

    public function handle(PaddleWebhookProcessor $processor): void
    {
        $event = BillingWebhookEvent::query()->find($this->eventId);

        if (! $event) return;

        // If signature is invalid, mark processed with error (do not apply billing changes)
        if (! $event->signature_valid) {
            $event->processed_at = now();
            $event->processing_error = 'Invalid signature.';
            $event->save();
            return;
        }

        try {
            $processor->process($event);
        } catch (\Throwable $e) {
            $event->processed_at = now();
            $event->processing_error = get_class($e) . ': ' . $e->getMessage();
            $event->save();

            throw $e;
        }
    }
}