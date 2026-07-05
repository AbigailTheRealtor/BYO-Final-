<?php

namespace App\Jobs;

use App\Services\Canonical\CanonicalListingResolver;
use App\Services\LocationDna\LocationDnaPipelineRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ComputeLocationDna implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public string $listingType;
    public int $listingId;

    public function __construct(string $listingType, int $listingId)
    {
        $this->listingType = $listingType;
        $this->listingId   = $listingId;
    }

    public function handle(LocationDnaPipelineRunner $runner, CanonicalListingResolver $resolver): void
    {
        $result = $runner->run($this->listingType, $this->listingId);

        if ($result['status'] !== 'success') {
            Log::info('ComputeLocationDna completed with non-success status', [
                'listing_type' => $this->listingType,
                'listing_id'   => $this->listingId,
                'status'       => $result['status'],
            ]);

            return;
        }

        // Phase 13 — chain dna_scores generation so the location-lifestyle
        // bridge picks up the freshly computed Location DNA (and, on a provider
        // refresh, the new provider data). Gated by the default-off master flag
        // and restricted to supported *_agent types; the dispatched job itself
        // re-checks the flag, so this is inert until the owner enables it.
        if (config('dna_scores.generation_enabled', false) && $resolver->supports($this->listingType)) {
            ComputeDnaScores::dispatch($this->listingType, $this->listingId);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ComputeLocationDna job permanently failed', [
            'job'          => self::class,
            'listing_type' => $this->listingType,
            'listing_id'   => $this->listingId,
            'error'        => $exception->getMessage(),
            'exception'    => get_class($exception),
        ]);
    }
}
