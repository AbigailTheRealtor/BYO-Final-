<?php

namespace App\Jobs;

use App\Services\Dna\Scores\DnaScoreGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ComputeDnaScores — queued generation of dna_scores for one listing
 * (§ Phase 13). Mirrors ComputeLocationDna: off-request, retried, and a no-op
 * when the master gate (config dna_scores.generation_enabled) is off — the
 * service enforces that, so a queued job that fires after the flag is disabled
 * simply does nothing.
 *
 * The job carries only addressing + origin; ALL logic lives in
 * DnaScoreGenerationService so every other trigger reuses it unchanged.
 */
class ComputeDnaScores implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public string $listingType;
    public int $listingId;
    /** system | ai | user | imported */
    public string $generatedBy;

    public function __construct(string $listingType, int $listingId, string $generatedBy = 'system')
    {
        $this->listingType = $listingType;
        $this->listingId   = $listingId;
        $this->generatedBy = $generatedBy;

        // Queueable owns $queue; redeclaring it as a property with a default fatals on PHP 8.2.
        $this->queue = 'dna';
    }

    public function handle(DnaScoreGenerationService $service): void
    {
        $service->generateForListing($this->listingType, $this->listingId, [
            'generated_by' => $this->generatedBy,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ComputeDnaScores job permanently failed', [
            'job'          => self::class,
            'listing_type' => $this->listingType,
            'listing_id'   => $this->listingId,
            'error'        => $exception->getMessage(),
            'exception'    => get_class($exception),
        ]);
    }
}
