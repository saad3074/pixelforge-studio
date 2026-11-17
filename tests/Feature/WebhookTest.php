<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Credits\CreditLedger;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.pixelforge.webhook_secret' => $this->secret,
            'services.pixelforge.markup_percent' => 25,
        ]);
    }

    #[Test]
    public function it_rejects_invalid_signatures(): void
    {
        $this->postJson('/webhooks/pixelforge', ['id' => 'evt_1'])
            ->assertStatus(400);
    }

    #[Test]
    public function duplicate_events_are_not_processed_twice(): void
    {
        $user = User::factory()->create(['credit_balance' => Money::fromDecimalString('10.00')]);
        $generation = $this->pendingGeneration($user);

        $payload = $this->succeededPayload($generation);
        $response = $this->signedPost($payload);
        $response->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $firstCost = $generation->user_cost;
        $balanceAfterFirst = $user->fresh()->credit_balance;

        $this->signedPost($payload)->assertNoContent();

        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame($firstCost, $generation->fresh()->user_cost);
        $this->assertSame($balanceAfterFirst, $user->fresh()->credit_balance);
    }

    #[Test]
    public function out_of_order_processing_after_succeeded_is_ignored(): void
    {
        $user = User::factory()->create(['credit_balance' => Money::fromDecimalString('10.00')]);
        $generation = $this->pendingGeneration($user);

        $this->signedPost($this->succeededPayload($generation))->assertNoContent();
        $this->signedPost($this->processingPayload($generation, 'evt_late_processing'))->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertNotNull($generation->user_cost);
    }

    #[Test]
    public function failed_generations_cost_nothing(): void
    {
        $user = User::factory()->create(['credit_balance' => Money::fromDecimalString('10.00')]);
        $generation = $this->pendingGeneration($user);
        $balanceBefore = $user->fresh()->credit_balance;

        $this->signedPost($this->failedPayload($generation))->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame(0, $generation->user_cost);
        $this->assertSame($balanceBefore + $generation->estimated_user_max, $user->fresh()->credit_balance);
    }

    private function pendingGeneration(User $user): Generation
    {
        $estimateMax = Money::fromDecimalString('0.100000000');
        $generation = Generation::query()->create([
            'user_id' => $user->id,
            'uuid' => fake()->uuid(),
            'status' => GenerationStatus::Queued,
            'provider_id' => 'gen_test_'.fake()->unique()->numerify('########'),
            'prompt' => '[succeed] lighthouse',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'estimated_user_max' => $estimateMax,
            'estimated_user_expected' => $estimateMax,
            'estimated_user_min' => $estimateMax,
            'markup_percent' => 25,
        ]);

        app(CreditLedger::class)->hold($user, $generation, $estimateMax);

        return $generation;
    }

    /**
     * @return array<string, mixed>
     */
    private function succeededPayload(Generation $generation): array
    {
        return [
            'id' => 'evt_success_'.$generation->uuid,
            'object' => 'event',
            'type' => 'generation.succeeded',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'object' => [
                    'id' => $generation->provider_id,
                    'object' => 'generation',
                    'status' => 'succeeded',
                    'prompt' => $generation->prompt,
                    'size' => $generation->size,
                    'quality' => $generation->quality,
                    'reference_images' => [],
                    'metadata' => ['generation_uuid' => $generation->uuid],
                    'image_url' => 'https://example.com/image.png',
                    'image_url_expires_at' => now()->addDay()->toIso8601String(),
                    'usage' => [
                        'input_text_tokens' => 10,
                        'input_image_tokens' => 0,
                        'output_tokens' => 1100,
                        'reasoning_tokens' => 500,
                        'total_tokens' => 1610,
                    ],
                    'rates' => $generation->rates,
                    'error' => null,
                    'created_at' => now()->subMinute()->toIso8601String(),
                    'started_at' => now()->subSeconds(30)->toIso8601String(),
                    'completed_at' => now()->toIso8601String(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function processingPayload(Generation $generation, string $eventId): array
    {
        return [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'generation.processing',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'object' => [
                    'id' => $generation->provider_id,
                    'object' => 'generation',
                    'status' => 'processing',
                    'prompt' => $generation->prompt,
                    'size' => $generation->size,
                    'quality' => $generation->quality,
                    'reference_images' => [],
                    'metadata' => ['generation_uuid' => $generation->uuid],
                    'image_url' => null,
                    'image_url_expires_at' => null,
                    'usage' => null,
                    'rates' => $generation->rates,
                    'error' => null,
                    'created_at' => now()->subMinute()->toIso8601String(),
                    'started_at' => now()->toIso8601String(),
                    'completed_at' => null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failedPayload(Generation $generation): array
    {
        return [
            'id' => 'evt_fail_'.$generation->uuid,
            'object' => 'event',
            'type' => 'generation.failed',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'object' => [
                    'id' => $generation->provider_id,
                    'object' => 'generation',
                    'status' => 'failed',
                    'prompt' => $generation->prompt,
                    'size' => $generation->size,
                    'quality' => $generation->quality,
                    'reference_images' => [],
                    'metadata' => ['generation_uuid' => $generation->uuid],
                    'image_url' => null,
                    'image_url_expires_at' => null,
                    'usage' => null,
                    'rates' => $generation->rates,
                    'error' => ['code' => 'generation_failed', 'message' => 'forced fail'],
                    'created_at' => now()->subMinute()->toIso8601String(),
                    'started_at' => now()->subSeconds(20)->toIso8601String(),
                    'completed_at' => now()->toIso8601String(),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signedPost(array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $this->secret);

        return $this->call(
            'POST',
            '/webhooks/pixelforge',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $body,
        );
    }
}
