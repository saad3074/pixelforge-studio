<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Generations\GenerationSynchronizer;
use App\Services\Generations\ProviderGeneration;
use App\Services\Pixelforge\WebhookSignatureVerifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        WebhookSignatureVerifier $verifier,
        GenerationSynchronizer $synchronizer,
    ): Response {
        $payload = $request->getContent();

        if (! $verifier->verify($payload, $request->header('Pixelforge-Signature'))) {
            abort(400, 'Invalid Pixelforge signature.');
        }

        /** @var array<string, mixed> $event */
        $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $eventId = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        if ($eventId === '') {
            abort(400, 'Missing event id.');
        }

        if ($type === 'ping') {
            try {
                WebhookEvent::query()->create([
                    'event_id' => $eventId,
                    'type' => $type,
                    'provider_generation_id' => null,
                    'payload' => $event,
                    'processed_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                // already recorded
            }

            return response()->noContent();
        }

        $generationData = $event['data']['object'] ?? null;
        if (! is_array($generationData)) {
            abort(400, 'Missing generation payload.');
        }

        try {
            DB::transaction(function () use ($event, $eventId, $type, $generationData, $synchronizer) {
                try {
                    WebhookEvent::query()->create([
                        'event_id' => $eventId,
                        'type' => $type,
                        'provider_generation_id' => $generationData['id'] ?? null,
                        'payload' => $event,
                        'processed_at' => now(),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    return;
                }

                $snapshot = ProviderGeneration::fromArray($generationData);
                $generation = $this->findGeneration($snapshot);

                if ($generation === null) {
                    return;
                }

                $synchronizer->apply($generation, $snapshot);
            });
        } catch (\JsonException) {
            abort(400, 'Invalid JSON.');
        }

        return response()->noContent();
    }

    private function findGeneration(ProviderGeneration $snapshot): ?Generation
    {
        $uuid = $snapshot->metadata['generation_uuid'] ?? null;

        if (is_string($uuid) && $uuid !== '') {
            $byUuid = Generation::query()->where('uuid', $uuid)->first();
            if ($byUuid !== null) {
                return $byUuid;
            }
        }

        return Generation::query()->where('provider_id', $snapshot->id)->first();
    }
}
