<?php

namespace App\Services\Pricing;

use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PricingService
{
    /**
     * @return array{rates: array<string, string>, tokens: array<string, mixed>}
     */
    public function catalog(): array
    {
        return Cache::remember('pixelforge.pricing', now()->addMinutes(10), function () {
            $base = rtrim((string) config('services.pixelforge.base_url'), '/');
            $root = preg_replace('#/v1$#', '', $base) ?: 'https://pixelforge.promptedit.dev';

            try {
                $response = Http::timeout(10)->acceptJson()->get("{$root}/v1/pricing");

                if ($response->successful()) {
                    return [
                        'rates' => $response->json('rates'),
                        'tokens' => $response->json('tokens'),
                    ];
                }
            } catch (\Throwable) {
                // Fall through to defaults (tests / offline).
            }

            return $this->defaultCatalog();
        });
    }

    /**
     * @param  array<int, string>  $referenceImages
     * @param  array<string, string>|null  $rates
     */
    public function estimate(string $prompt, string $size, string $quality, array $referenceImages = [], ?array $rates = null): PriceEstimate
    {
        $catalog = $this->catalog();
        $rates ??= $catalog['rates'];
        $tokens = $catalog['tokens'];
        $markup = (int) config('services.pixelforge.markup_percent', 25);

        $chars = mb_strlen($prompt);
        $perChar = (int) ($tokens['characters_per_text_token'] ?? 4);
        $variance = (float) ($tokens['text_token_variance'] ?? 0.15);

        $textExpected = (int) ceil($chars / max(1, $perChar));
        $textMin = (int) floor($textExpected * (1 - $variance));
        $textMax = (int) ceil($textExpected * (1 + $variance));

        $perImage = (int) ($tokens['per_reference_image'] ?? 765);
        $imageTokens = count($referenceImages) * $perImage;

        $outputTokens = (int) ($tokens['output'][$quality][$size]
            ?? throw new RuntimeException("Unknown size/quality for output tokens: {$quality}/{$size}"));

        $reasoning = $tokens['reasoning'][$quality]
            ?? throw new RuntimeException("Unknown quality for reasoning tokens: {$quality}");

        $reasoningMin = (int) $reasoning['min'];
        $reasoningMax = (int) $reasoning['max'];
        $reasoningExpected = (int) round(($reasoningMin + $reasoningMax) / 2);

        $providerMin = $this->providerCost($textMin, $imageTokens, $outputTokens, $reasoningMin, $rates);
        $providerExpected = $this->providerCost($textExpected, $imageTokens, $outputTokens, $reasoningExpected, $rates);
        $providerMax = $this->providerCost($textMax, $imageTokens, $outputTokens, $reasoningMax, $rates);

        return new PriceEstimate(
            providerMin: $providerMin,
            providerExpected: $providerExpected,
            providerMax: $providerMax,
            userMin: Money::withMarkup($providerMin, $markup),
            userExpected: Money::withMarkup($providerExpected, $markup),
            userMax: Money::withMarkup($providerMax, $markup),
            textTokensExpected: $textExpected,
            textTokensMin: $textMin,
            textTokensMax: $textMax,
            imageTokens: $imageTokens,
            outputTokens: $outputTokens,
            reasoningMin: $reasoningMin,
            reasoningExpected: $reasoningExpected,
            reasoningMax: $reasoningMax,
        );
    }

    /**
     * @param  array<string, int>  $usage
     * @param  array<string, string>  $rates
     */
    public function actualProviderCost(array $usage, array $rates): int
    {
        return $this->providerCost(
            (int) ($usage['input_text_tokens'] ?? 0),
            (int) ($usage['input_image_tokens'] ?? 0),
            (int) ($usage['output_tokens'] ?? 0),
            (int) ($usage['reasoning_tokens'] ?? 0),
            $rates,
        );
    }

    public function userPrice(int $providerCostNanos): int
    {
        return Money::withMarkup($providerCostNanos, (int) config('services.pixelforge.markup_percent', 25));
    }

    /**
     * @param  array<string, string>  $rates
     */
    private function providerCost(int $text, int $image, int $output, int $reasoning, array $rates): int
    {
        return Money::costForTokens($text, $rates['input_text_tokens'])
            + Money::costForTokens($image, $rates['input_image_tokens'])
            + Money::costForTokens($output, $rates['output_tokens'])
            + Money::costForTokens($reasoning, $rates['reasoning_tokens']);
    }

    /**
     * @return array{rates: array<string, string>, tokens: array<string, mixed>}
     */
    private function defaultCatalog(): array
    {
        return [
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'tokens' => [
                'characters_per_text_token' => 4,
                'text_token_variance' => 0.15,
                'per_reference_image' => 765,
                'output' => [
                    'low' => [
                        '1024x1024' => 280,
                        '1536x1024' => 420,
                        '1024x1536' => 420,
                    ],
                    'medium' => [
                        '1024x1024' => 1100,
                        '1536x1024' => 1650,
                        '1024x1536' => 1650,
                    ],
                    'high' => [
                        '1024x1024' => 4300,
                        '1536x1024' => 6450,
                        '1024x1536' => 6450,
                    ],
                ],
                'reasoning' => [
                    'low' => ['min' => 0, 'max' => 500],
                    'medium' => ['min' => 200, 'max' => 2000],
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
        ];
    }
}
