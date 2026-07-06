<?php

namespace App\Services\Dna\Relevance;

/**
 * MatchingV2Service — Matching V2 C6: the read-only orchestration facade.
 *
 * Composes the already-built, individually-tested pieces into ONE call:
 *   discover (Stage A) → narrow + compliance (Stage B) → rank/tier (§F6)
 * and returns an OrchestratedMatchResult that preserves both listing_type and
 * listing_id for every ranked match.
 *
 * GOVERNANCE: pure read-only. It writes nothing, persists no results, exposes
 * nothing to consumers, and is inert (empty result, no DB reads) when Matching V2
 * is disabled. Direction is inferred from the subject's listing_type — the subject
 * sits on exactly one side of the shared dna_scores axis.
 *
 * @see docs/matching-v2-c6-orchestration-facade-scope.md
 */
class MatchingV2Service
{
    public function __construct(
        private readonly CandidateDiscoveryService $discovery,
        private readonly DnaMatchService $matcher,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('matching.v2_enabled', false);
    }

    /**
     * Discover, narrow, and rank matches for one subject.
     *
     * @param int|null $cap discovery/candidate-pool cap; defaults to config.
     * @return OrchestratedMatchResult inert empty when disabled or the type is unsupported.
     */
    public function matchForSubject(string $listingType, int $listingId, ?int $cap = null): OrchestratedMatchResult
    {
        $direction = $this->directionFor($listingType);

        // Inert: flag off, or a listing_type that has no side on the dna_scores axis.
        if (! $this->isEnabled() || $direction === null) {
            return OrchestratedMatchResult::empty($listingType, $listingId, $direction);
        }

        $candidates = $this->discovery->discover($listingType, $listingId, $direction, $cap);

        // No eligible/compliant candidates → do not call the matcher at all.
        if ($candidates->isEmpty()) {
            return new OrchestratedMatchResult(
                $listingType,
                $listingId,
                $direction,
                new RankedMatchSet([], 0),
                0,
                $candidates->wasTruncated(),
            );
        }

        $ranked = $direction === MatchDirection::DemandToListings
            ? $this->matcher->matchDemandAgainstListings($listingType, $listingId, $candidates->toArray())
            : $this->matcher->matchListingAgainstDemands($listingType, $listingId, $candidates->toArray());

        return new OrchestratedMatchResult(
            $listingType,
            $listingId,
            $direction,
            $ranked,
            $candidates->total(),
            $candidates->wasTruncated(),
        );
    }

    /**
     * The subject's match direction, inferred from its side of the dna_scores axis.
     * Property subjects match against demands; demand subjects against listings.
     * Returns null for an unsupported listing_type.
     */
    private function directionFor(string $listingType): ?MatchDirection
    {
        return match ($listingType) {
            'seller_agent', 'landlord_agent' => MatchDirection::ListingToDemands,
            'buyer_agent', 'tenant_agent'    => MatchDirection::DemandToListings,
            default                          => null,
        };
    }
}
