<?php

namespace App\Services\Pricing;

final readonly class PriceEstimate
{
    public function __construct(
        public int $providerMin,
        public int $providerExpected,
        public int $providerMax,
        public int $userMin,
        public int $userExpected,
        public int $userMax,
        public int $textTokensExpected,
        public int $textTokensMin,
        public int $textTokensMax,
        public int $imageTokens,
        public int $outputTokens,
        public int $reasoningMin,
        public int $reasoningExpected,
        public int $reasoningMax,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_min' => $this->providerMin,
            'provider_expected' => $this->providerExpected,
            'provider_max' => $this->providerMax,
            'user_min' => $this->userMin,
            'user_expected' => $this->userExpected,
            'user_max' => $this->userMax,
            'tokens' => [
                'text_expected' => $this->textTokensExpected,
                'text_min' => $this->textTokensMin,
                'text_max' => $this->textTokensMax,
                'image' => $this->imageTokens,
                'output' => $this->outputTokens,
                'reasoning_min' => $this->reasoningMin,
                'reasoning_expected' => $this->reasoningExpected,
                'reasoning_max' => $this->reasoningMax,
            ],
        ];
    }
}
