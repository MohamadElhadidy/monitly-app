<?php

namespace Tests\Feature;

use App\Services\Billing\PaddleWebhookSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaddleWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_signature_verification(): void
    {
        $secret = 'test-secret';
        $body = '{"event_id":"evt_123","event_type":"subscription.updated","data":{"id":"sub_1"}}';

        $ts = '1700000000';
        $h1 = hash_hmac('sha256', $ts . ':' . $body, $secret);

        $header = "ts={$ts},h1={$h1}";

        $this->assertTrue(PaddleWebhookSignature::verify($body, $header, $secret));
        $this->assertFalse(PaddleWebhookSignature::verify($body, "ts={$ts},h1=bad", $secret));
        $this->assertFalse(PaddleWebhookSignature::verify($body, null, $secret));
    }
}