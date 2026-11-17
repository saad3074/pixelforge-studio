<?php

namespace App\Services\Generations;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Credits\CreditLedger;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;

final class GenerationSynchronizer
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CreditLedger $ledger,
    ) {}

    public function apply(Generation $generation, ProviderGeneration $snapshot): Generation
    {
        return DB::transaction(function () use ($generation, $snapshot) {
            /** @var Generation $locked */
            $locked = Generation::query()->whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if ($locked->provider_id === null) {
                $locked->provider_id = $snapshot->id;
            }

            if ($locked->rates === null && $snapshot->rates !== null) {
                $locked->rates = $snapshot->rates;
            }

            if ($snapshot->createdAt !== null) {
                $locked->provider_created_at ??= $snapshot->createdAt;
            }
            if ($snapshot->startedAt !== null) {
                $locked->provider_started_at ??= $snapshot->startedAt;
            }

            $current = $locked->status;
            $next = $snapshot->status;

            if (! $current->canTransitionTo($next) && $current !== $next) {
                // Out-of-order: keep terminal state, but fill missing fields if useful.
                if ($current->isTerminal()) {
                    $locked->save();

                    return $locked->refresh();
                }
            }

            if ($current->isTerminal()) {
                $locked->save();

                return $locked->refresh();
            }

            if ($next->rank() >= $current->rank()) {
                $locked->status = $next;
            }

            if ($next === GenerationStatus::Succeeded) {
                $locked->usage = $snapshot->usage;
                $locked->image_url = $snapshot->imageUrl;
                $locked->image_url_expires_at = $snapshot->imageUrlExpiresAt;
                $locked->provider_completed_at = $snapshot->completedAt;
                $locked->error_code = null;
                $locked->error_message = null;

                $rates = $locked->rates ?? $snapshot->rates ?? [];
                $usage = $snapshot->usage ?? [];
                $providerCost = $this->pricing->actualProviderCost($usage, $rates);
                $userCost = $this->pricing->userPrice($providerCost);

                $locked->provider_cost = $providerCost;
                $locked->user_cost = $userCost;
                $locked->save();

                $this->ledger->charge($locked, $userCost);
            } elseif ($next === GenerationStatus::Failed) {
                $locked->error_code = $snapshot->error['code'] ?? 'generation_failed';
                $locked->error_message = $snapshot->error['message'] ?? 'Generation failed.';
                $locked->provider_completed_at = $snapshot->completedAt;
                $locked->provider_cost = 0;
                $locked->user_cost = 0;
                $locked->save();

                $this->ledger->charge($locked, 0);
            } else {
                $locked->save();
            }

            return $locked->refresh();
        });
    }

    public function failLocally(Generation $generation, string $code, string $message): Generation
    {
        return DB::transaction(function () use ($generation, $code, $message) {
            /** @var Generation $locked */
            $locked = Generation::query()->whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                return $locked;
            }

            // Only mark failed locally when Pixelforge never accepted it (still pending).
            if ($locked->provider_id !== null && $locked->status !== GenerationStatus::Pending) {
                return $locked;
            }

            $locked->status = GenerationStatus::Failed;
            $locked->error_code = $code;
            $locked->error_message = $message;
            $locked->provider_cost = 0;
            $locked->user_cost = 0;
            $locked->provider_completed_at = now();
            $locked->save();

            $this->ledger->charge($locked, 0);

            return $locked->refresh();
        });
    }
}
