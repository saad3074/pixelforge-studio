<?php

namespace App\Services\Pixelforge;

final class WebhookSignatureVerifier
{
    public function verify(string $payload, ?string $signatureHeader, ?string $secret = null, ?int $tolerance = null): bool
    {
        $secret ??= (string) config('services.pixelforge.webhook_secret');
        $tolerance ??= (int) config('services.pixelforge.webhook_tolerance', 300);

        if ($secret === '' || $signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = (int) ($parts['t'] ?? 0);
        $signature = $parts['v1'] ?? '';

        if ($timestamp <= 0 || $signature === '') {
            return false;
        }

        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return hash_equals($expected, $signature);
    }
}
