<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class PixelforgeClient
{
    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.pixelforge.base_url'), '/'))
            ->withToken((string) config('services.pixelforge.api_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createGeneration(array $payload, string $idempotencyKey): PixelforgeResponse
    {
        try {
            $response = $this->http()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post('/generations', $payload);
        } catch (ConnectionException $e) {
            return PixelforgeResponse::connectionError($e->getMessage());
        }

        return PixelforgeResponse::fromHttp($response);
    }

    public function getGeneration(string $providerId): PixelforgeResponse
    {
        try {
            $response = $this->http()->get("/generations/{$providerId}");
        } catch (ConnectionException $e) {
            return PixelforgeResponse::connectionError($e->getMessage());
        }

        return PixelforgeResponse::fromHttp($response);
    }

    /**
     * @return array{data: list<array<string, mixed>>, total: array<string, mixed>}
     */
    public function usage(?string $startDate = null, ?string $endDate = null): array
    {
        $query = array_filter([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $response = $this->http()->get('/usage', $query)->throw();

        return [
            'data' => $response->json('data') ?? [],
            'total' => $response->json('total') ?? [],
        ];
    }
}
