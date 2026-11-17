<?php

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Services\Generations\GenerationSynchronizer;
use App\Services\Generations\ProviderGeneration;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Console\Command;

class ReconcileGenerationsCommand extends Command
{
    protected $signature = 'generations:reconcile {--limit=50}';

    protected $description = 'Poll in-flight Pixelforge generations and redispatch stuck pending jobs';

    public function handle(PixelforgeClient $client, GenerationSynchronizer $synchronizer): int
    {
        $limit = (int) $this->option('limit');

        Generation::query()
            ->where('status', GenerationStatus::Pending)
            ->where('created_at', '<', now()->subSeconds(45))
            ->whereNull('provider_id')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(fn (Generation $generation) => SubmitGeneration::dispatch($generation->id));

        $inflight = Generation::query()
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->whereNotNull('provider_id')
            ->where('updated_at', '<', now()->subSeconds(45))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($inflight as $generation) {
            $response = $client->getGeneration((string) $generation->provider_id);

            if (! $response->successful() || $response->generation() === null) {
                continue;
            }

            $synchronizer->apply($generation, ProviderGeneration::fromArray($response->generation()));
        }

        $this->info("Reconciled {$inflight->count()} in-flight generation(s).");

        return self::SUCCESS;
    }
}
