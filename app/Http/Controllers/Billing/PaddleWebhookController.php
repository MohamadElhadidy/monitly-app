<?php

namespace App\Http\Controllers\Billing;

use App\Jobs\Billing\ProcessPaddleWebhookJob;
use App\Models\BillingWebhookEvent;
use App\Services\Billing\PaddleWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaddleWebhookController
{
    public function handle(Request $request)
    {
        $payload = $request->all();
        $rawPayload = $request->getContent();

        $eventId = (string) ($payload['event_id'] ?? $payload['id'] ?? '');
        if ($eventId === '') {
            $eventId = hash('sha256', $rawPayload ?: json_encode($payload));
        }

        $eventType = (string) ($payload['event_type'] ?? $payload['type'] ?? 'unknown');

        $signatureHeader = (string) $request->header('Paddle-Signature', '');
        $secret = (string) config('billing.paddle_webhook_secret');
        $signatureValid = $secret !== ''
            ? PaddleWebhookSignature::verify($rawPayload, $signatureHeader, $secret)
            : false;

        $record = BillingWebhookEvent::firstOrCreate(
            [
                'provider' => 'paddle',
                'event_id' => $eventId,
            ],
            [
                'event_type' => $eventType,
                'payload' => $payload,
                'signature_valid' => $signatureValid,
            ]
        );

        if ($record->wasRecentlyCreated) {
            ProcessPaddleWebhookJob::dispatch($record->id);
        }

        Log::channel('paddle')->info('Webhook captured', [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'signature_valid' => $signatureValid,
        ]);

        return response()->json(['ok' => true]);
    }
}
