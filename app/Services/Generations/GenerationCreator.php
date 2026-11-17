<?php

namespace App\Services\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GenerationCreator
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CreditLedger $ledger,
    ) {}

    /**
     * @param  array{prompt: string, size: string, quality: string, reference_images?: array<int, string>}  $input
     */
    public function create(User $user, array $input): Generation
    {
        $referenceImages = array_values(array_filter($input['reference_images'] ?? []));
        $estimate = $this->pricing->estimate(
            $input['prompt'],
            $input['size'],
            $input['quality'],
            $referenceImages,
        );

        if ($user->credit_balance < $estimate->userMax) {
            throw new InsufficientCreditsException(
                'Not enough credits for the maximum estimated price of this generation.'
            );
        }

        $generation = DB::transaction(function () use ($user, $input, $referenceImages, $estimate) {
            $generation = Generation::query()->create([
                'user_id' => $user->id,
                'uuid' => (string) Str::uuid(),
                'status' => GenerationStatus::Pending,
                'prompt' => $input['prompt'],
                'size' => $input['size'],
                'quality' => $input['quality'],
                'reference_images' => $referenceImages,
                'rates' => $this->pricing->catalog()['rates'],
                'estimated_provider_min' => $estimate->providerMin,
                'estimated_provider_expected' => $estimate->providerExpected,
                'estimated_provider_max' => $estimate->providerMax,
                'estimated_user_min' => $estimate->userMin,
                'estimated_user_expected' => $estimate->userExpected,
                'estimated_user_max' => $estimate->userMax,
                'markup_percent' => (int) config('services.pixelforge.markup_percent', 25),
            ]);

            $this->ledger->hold($user, $generation, $estimate->userMax);

            return $generation;
        });

        SubmitGeneration::dispatch($generation->id);

        return $generation;
    }
}
