<?php

namespace App\Services\Dna\Scores;

use App\Models\DnaScore;
use App\Services\Canonical\CanonicalListingResolver;
use Illuminate\Support\Facades\Log;

/**
 * DnaScoreGenerationService — the single, trigger-agnostic entrypoint that turns
 * a (listing_type, listing_id) into persisted dna_scores by running every
 * generator registered in config('dna_scores.generators') (§ Phase 13).
 *
 * ADDITION 3 (event-driven readiness): this service is COMPLETELY independent of
 * how it is invoked. Observers dispatch a job that calls it; but provider
 * refreshes, MLS imports, manual rebuild commands, AI enrichment, photo
 * analysis, bulk rescoring and any future event-bus trigger call the SAME
 * service with no modification. Callers pass their origin via
 * options['generated_by'] (system|ai|user|imported) so provenance is accurate.
 *
 * Idempotent (generators updateOrCreate) and version-aware (only_stale skips
 * scores already at the current version). Additive and default-off: does
 * nothing unless config('dna_scores.generation_enabled') is true.
 *
 * GOVERNANCE: no compatibility-engine interaction, no Matching V2, no external
 * calls of its own — it only sequences the registered generators.
 */
class DnaScoreGenerationService
{
    private DnaScoreGeneratorRegistry $registry;
    private CanonicalListingResolver $resolver;

    public function __construct(DnaScoreGeneratorRegistry $registry, CanonicalListingResolver $resolver)
    {
        $this->registry = $registry;
        $this->resolver = $resolver;
    }

    /** Master gate — the single source of truth for whether generation runs. */
    public function isEnabled(): bool
    {
        return (bool) config('dna_scores.generation_enabled', false);
    }

    /**
     * Generate all registered DNA scores for one listing.
     *
     * @param array{generated_by?:string,only_stale?:bool} $options
     * @return array<int,DnaScore> every row written this call
     */
    public function generateForListing(string $listingType, int $listingId, array $options = []): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        // Guard: the scalar path requires a resolvable canonical listing. The
        // supported set is the four *_agent types (Family A). Unsupported types
        // (e.g. the consumer 'seller'/'buyer' family) are skipped rather than
        // half-generating location-only scores under an unrecognised address.
        if (! $this->resolver->supports($listingType)) {
            return [];
        }

        $options['generated_by'] = $options['generated_by'] ?? 'system';
        $rows = [];

        foreach ($this->registry->enabled() as $generator) {
            try {
                $rows = array_merge($rows, $generator->generate($listingType, $listingId, $options));
            } catch (\Throwable $e) {
                // One generator failing must not abort the others.
                Log::warning('DnaScoreGenerationService: generator failed', [
                    'generator'    => $generator->key(),
                    'listing_type' => $listingType,
                    'listing_id'   => $listingId,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return $rows;
    }
}
