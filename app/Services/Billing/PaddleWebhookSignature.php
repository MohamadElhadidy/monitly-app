<?php

namespace App\Services\Billing;

class PaddleWebhookSignature
{
    public static function verify(string $payload, string $header, string $secret): bool
    {
        if (! $header || ! str_contains($header, 'h1=')) {
            return false;
        }

        $parts = collect(explode(';', $header))
            ->mapWithKeys(function ($item) {
                [$k, $v] = array_map('trim', explode('=', $item, 2));
                return [$k => $v];
            });

        $timestamp = $parts->get('ts');
        $signature = $parts->get('h1');

        if (! $timestamp || ! $signature) {
            return false;
        }

        $signedPayload = $timestamp . ':' . $payload;
        $computed = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($computed, $signature);
    }
}