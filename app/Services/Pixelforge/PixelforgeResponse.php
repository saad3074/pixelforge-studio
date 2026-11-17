<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\Response;

final class PixelforgeResponse
{
    /**
     * @param  array<string, mixed>|null  $body
     */
    public function __construct(
        public readonly int $status,
        public readonly ?array $body,
        public readonly ?int $retryAfter = null,
        public readonly bool $connectionError = false,
        public readonly ?string $connectionMessage = null,
    ) {}

    public static function fromHttp(Response $response): self
    {
        $retryAfter = $response->header('Retry-After');

        return new self(
            status: $response->status(),
            body: $response->json(),
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }

    public static function connectionError(string $message): self
    {
        return new self(
            status: 0,
            body: null,
            connectionError: true,
            connectionMessage: $message,
        );
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function code(): ?string
    {
        return $this->body['error']['code'] ?? null;
    }

    public function message(): ?string
    {
        if ($this->connectionError) {
            return $this->connectionMessage;
        }

        return $this->body['error']['message'] ?? null;
    }

    public function isRetryable(): bool
    {
        if ($this->connectionError) {
            return true;
        }

        return in_array($this->status, [429, 503], true)
            || in_array($this->code(), [
                'rate_limit_exceeded',
                'concurrency_limit_exceeded',
                'overloaded',
            ], true);
    }

    public function isPermanentFailure(): bool
    {
        if (in_array($this->status, [400, 401, 403, 404, 422], true)) {
            return true;
        }

        return in_array($this->code(), [
            'content_policy_violation',
            'validation_failed',
            'daily_limit_exceeded',
            'invalid_api_key',
            'idempotency_key_reused',
            'authentication_failed',
            'unauthorized',
        ], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function generation(): ?array
    {
        if (! $this->successful()) {
            return null;
        }

        return $this->body;
    }
}
