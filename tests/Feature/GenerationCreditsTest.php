<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerationCreditsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_blocks_generation_when_balance_is_too_low(): void
    {
        $user = User::factory()->create(['credit_balance' => 1]);

        $this->actingAs($user)
            ->post('/generations', [
                'prompt' => '[succeed] expensive looking prompt',
                'size' => '1536x1024',
                'quality' => 'high',
            ])
            ->assertSessionHasErrors('prompt');

        $this->assertSame(0, Generation::query()->count());
    }

    #[Test]
    public function it_holds_max_estimate_and_queues_submission(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credit_balance' => Money::fromDecimalString('25.00')]);

        $this->actingAs($user)
            ->post('/generations', [
                'prompt' => '[succeed] a calm lake',
                'size' => '1024x1024',
                'quality' => 'medium',
            ])
            ->assertRedirect(route('generations.index'));

        $generation = Generation::query()->firstOrFail();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame(
            Money::fromDecimalString('25.00') - $generation->estimated_user_max,
            $user->fresh()->credit_balance,
        );

        Queue::assertPushed(SubmitGeneration::class);
    }

    #[Test]
    public function buying_credits_increases_balance(): void
    {
        $user = User::factory()->create(['credit_balance' => 0]);

        $this->actingAs($user)
            ->post('/credits/purchase', ['idempotency_key' => 'buy-1'])
            ->assertRedirect(route('credits.index'));

        $this->assertSame(10_000_000_000, $user->fresh()->credit_balance);

        $this->actingAs($user)
            ->post('/credits/purchase', ['idempotency_key' => 'buy-1'])
            ->assertRedirect(route('credits.index'));

        $this->assertSame(10_000_000_000, $user->fresh()->credit_balance);
    }

    #[Test]
    public function submit_job_marks_content_policy_as_failed_and_free(): void
    {
        Http::fake([
            '*/generations' => Http::response([
                'error' => [
                    'type' => 'invalid_request_error',
                    'code' => 'content_policy_violation',
                    'message' => 'Blocked',
                ],
            ], 400),
        ]);

        $user = User::factory()->create(['credit_balance' => Money::fromDecimalString('5.00')]);
        $generation = Generation::query()->create([
            'user_id' => $user->id,
            'uuid' => fake()->uuid(),
            'status' => GenerationStatus::Pending,
            'prompt' => '[blocked] nope',
            'size' => '1024x1024',
            'quality' => 'low',
            'reference_images' => [],
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'estimated_user_max' => Money::fromDecimalString('0.05'),
            'markup_percent' => 25,
        ]);

        app(\App\Services\Credits\CreditLedger::class)->hold($user, $generation, $generation->estimated_user_max);
        $before = $user->fresh()->credit_balance;

        (new SubmitGeneration($generation->id))->handle(
            app(\App\Services\Pixelforge\PixelforgeClient::class),
            app(\App\Services\Generations\GenerationSynchronizer::class),
        );

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame(0, $generation->user_cost);
        $this->assertSame($before + $generation->estimated_user_max, $user->fresh()->credit_balance);
    }
}
