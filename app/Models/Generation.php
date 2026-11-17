<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $uuid
 * @property string|null $provider_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property string $size
 * @property string $quality
 * @property array<int, string>|null $reference_images
 * @property array<string, string>|null $rates
 * @property array<string, int>|null $usage
 * @property int|null $estimated_provider_min
 * @property int|null $estimated_provider_expected
 * @property int|null $estimated_provider_max
 * @property int|null $estimated_user_min
 * @property int|null $estimated_user_expected
 * @property int|null $estimated_user_max
 * @property int|null $provider_cost
 * @property int|null $user_cost
 * @property int $markup_percent
 * @property string|null $image_url
 * @property Carbon|null $image_url_expires_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $provider_created_at
 * @property Carbon|null $provider_started_at
 * @property Carbon|null $provider_completed_at
 */
class Generation extends Model
{
    protected $fillable = [
        'user_id',
        'uuid',
        'provider_id',
        'status',
        'prompt',
        'size',
        'quality',
        'reference_images',
        'rates',
        'usage',
        'estimated_provider_min',
        'estimated_provider_expected',
        'estimated_provider_max',
        'estimated_user_min',
        'estimated_user_expected',
        'estimated_user_max',
        'provider_cost',
        'user_cost',
        'markup_percent',
        'image_url',
        'image_url_expires_at',
        'error_code',
        'error_message',
        'provider_created_at',
        'provider_started_at',
        'provider_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'usage' => 'array',
            'estimated_provider_min' => 'integer',
            'estimated_provider_expected' => 'integer',
            'estimated_provider_max' => 'integer',
            'estimated_user_min' => 'integer',
            'estimated_user_expected' => 'integer',
            'estimated_user_max' => 'integer',
            'provider_cost' => 'integer',
            'user_cost' => 'integer',
            'markup_percent' => 'integer',
            'image_url_expires_at' => 'datetime',
            'provider_created_at' => 'datetime',
            'provider_started_at' => 'datetime',
            'provider_completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function isInFlight(): bool
    {
        return in_array($this->status, [
            GenerationStatus::Pending,
            GenerationStatus::Queued,
            GenerationStatus::Processing,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toHistoryArray(): array
    {
        $imageExpired = $this->image_url_expires_at !== null
            && $this->image_url_expires_at->isPast();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'provider_id' => $this->provider_id,
            'status' => $this->status->value,
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'reference_images' => $this->reference_images ?? [],
            'estimated_user_min' => $this->estimated_user_min,
            'estimated_user_expected' => $this->estimated_user_expected,
            'estimated_user_max' => $this->estimated_user_max,
            'provider_cost' => $this->provider_cost,
            'user_cost' => $this->user_cost,
            'image_url' => $imageExpired ? null : $this->image_url,
            'image_url_expires_at' => $this->image_url_expires_at?->toIso8601String(),
            'image_expired' => $imageExpired,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->provider_completed_at?->toIso8601String(),
        ];
    }
}
