<?php

namespace Tests\Unit;

use App\Services\Pricing\PricingService;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    #[Test]
    public function estimate_matches_docs_maximum_for_high_quality(): void
    {
        Cache::put('pixelforge.pricing', [
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
                    'high' => [
                        '1536x1024' => 6450,
                    ],
                ],
                'reasoning' => [
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
        ], 60);

        $prompt = str_repeat('a', 400);
        $estimate = app(PricingService::class)->estimate(
            $prompt,
            '1536x1024',
            'high',
            ['https://example.com/1.png', 'https://example.com/2.png'],
        );

        $this->assertSame(Money::fromDecimalString('0.373875000'), $estimate->providerMax);
        $this->assertSame(Money::withMarkup($estimate->providerMax, 25), $estimate->userMax);
    }

    #[Test]
    public function actual_cost_uses_frozen_rates(): void
    {
        $cost = app(PricingService::class)->actualProviderCost(
            [
                'input_text_tokens' => 12,
                'input_image_tokens' => 765,
                'output_tokens' => 6450,
                'reasoning_tokens' => 3184,
            ],
            [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
        );

        $expected = Money::costForTokens(12, '5.00')
            + Money::costForTokens(765, '10.00')
            + Money::costForTokens(6450, '40.00')
            + Money::costForTokens(3184, '12.50');

        $this->assertSame($expected, $cost);
    }
}
