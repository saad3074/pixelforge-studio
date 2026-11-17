<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Generations\GenerationSynchronizer;
use App\Services\Generations\ProviderGeneration;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SubmitGeneration implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 25;

    public function __construct(public int $generationId)
    {
        $this->onQueue('pixelforge');
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(15);
    }

    public function handle(PixelforgeClient $client, GenerationSynchronizer $synchronizer): void
    {
        $generation = Generation::query()->find($this->generationId);

        if ($generation === null) {
            return;
        }

        if ($generation->status !== GenerationStatus::Pending || $generation->provider_id !== null) {
            return;
        }

        $payload = [
            'prompt' => $generation->prompt,
            'size' => $generation->size,
            'quality' => $generation->quality,
            'metadata' => [
                'generation_uuid' => $generation->uuid,
            ],
        ];

        if (! empty($generation->reference_images)) {
            $payload['reference_images'] = $generation->reference_images;
        }

        $response = $client->createGeneration($payload, $generation->uuid);

        if ($response->successful() && $response->generation() !== null) {
            $snapshot = ProviderGeneration::fromArray($response->generation());
            $synchronizer->apply($generation, $snapshot);

            return;
        }

        if ($response->isPermanentFailure()) {
            $synchronizer->failLocally(
                $generation,
                $response->code() ?? 'provider_rejected',
                $response->message() ?? 'Pixelforge rejected the request.',
            );

            return;
        }

        if ($response->isRetryable()) {
            $delay = max(1, min($response->retryAfter ?? 5, 60));
            $this->release($delay);

            return;
        }

        Log::warning('Unexpected Pixelforge create response', [
            'generation_id' => $generation->id,
            'status' => $response->status,
            'body' => $response->body,
        ]);

        $this->release(5);
    }
}
