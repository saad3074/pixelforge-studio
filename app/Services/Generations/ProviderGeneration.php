<?php

namespace App\Services\Generations;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;

final readonly class ProviderGeneration
{
    /**
     * @param  array<string, string>|null  $rates
     * @param  array<string, int>|null  $usage
     * @param  array<string, string>|null  $error
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        public ?array $rates,
        public ?array $usage,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public ?array $error,
        public array $metadata,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            status: GenerationStatus::from((string) $data['status']),
            rates: $data['rates'] ?? null,
            usage: $data['usage'] ?? null,
            imageUrl: $data['image_url'] ?? null,
            imageUrlExpiresAt: isset($data['image_url_expires_at'])
                ? CarbonImmutable::parse($data['image_url_expires_at'])
                : null,
            error: $data['error'] ?? null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            createdAt: isset($data['created_at']) ? CarbonImmutable::parse($data['created_at']) : null,
            startedAt: isset($data['started_at']) ? CarbonImmutable::parse($data['started_at']) : null,
            completedAt: isset($data['completed_at']) ? CarbonImmutable::parse($data['completed_at']) : null,
        );
    }
}
