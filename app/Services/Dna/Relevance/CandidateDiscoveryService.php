<?php

namespace App\Services\Dna\Relevance;

/**
 * CandidateDiscoveryService — Matching V2 consumption slice 2 (Candidate Discovery).
 *
 * Turns a subject (listing_type, listing_id) + a MatchDirection into the bounded,
 * deterministic candidate list that DnaMatchService (slice 1) consumes. It performs
 * NO scoring — the §F6 kernels own that. It performs NO writes — it is a pure
 * read-only consumer of dna_scores, exactly like the rest of Matching V2.
 *
 * FEATURE FLAG: gated by the SAME master flag as all of Matching V2
 * (config matching.v2_enabled / env MATCHING_V2_ENABLED, default off). Candidate
 * Discovery is a sub-capability of Matching V2 and must never be reachable when V2
 * is off — when disabled, discover() returns an empty CandidateSet WITHOUT reading
 * anything (mirrors DnaMatchService::inert()).
 *
 * PROVIDER-AGNOSTIC: the shipped source resolves candidates from the unified
 * dna_scores layer with no per-provider branching. Using dna_scores as the
 * candidate universe is a slice-2 implementation decision; the long-term target is
 * discovery across ALL DNA-enabled sources. See the scope doc §0.2.
 *
 * @see docs/matching-v2-consumption-slice-2-candidate-discovery-scope.md
 */
class CandidateDiscoveryService
{
    public function __construct(
        private readonly CandidateSourceInterface $source,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('matching.v2_enabled', false);
    }

    /**
     * Discover the bounded candidate set for a subject in the given direction.
     *
     * @param string        $listingType The subject's listing_type.
     * @param int           $listingId   The subject's listing_id.
     * @param MatchDirection $direction   Which side the counterparts sit on.
     * @param int|null      $cap         Optional cap override; defaults to config.
     *
     * @return CandidateSet Empty (no DB reads) when Matching V2 is disabled.
     */
    public function discover(
        string $listingType,
        int $listingId,
        MatchDirection $direction,
        ?int $cap = null,
    ): CandidateSet {
        if (! $this->isEnabled()) {
            return CandidateSet::empty();
        }

        $side = $direction->counterpartSide();

        // EMPTY allowlist = all listing types (provider-agnostic default, §0.2).
        // `side` alone already separates supply from demand.
        $allowedTypes = (array) config(
            "matching.candidate_discovery.allowed_listing_types.$side",
            [],
        );

        $effectiveCap = $cap ?? (int) config('matching.candidate_discovery.cap', 200);

        $query = new CandidateQuery(
            counterpartSide: $side,
            allowedListingTypes: array_values($allowedTypes),
            excludeListingType: $listingType,
            excludeListingId: $listingId,
            cap: $effectiveCap,
        );

        return $this->source->resolve($query);
    }
}
