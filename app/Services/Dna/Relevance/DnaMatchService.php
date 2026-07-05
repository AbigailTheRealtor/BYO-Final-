<?php

namespace App\Services\Dna\Relevance;

/**
 * DnaMatchService — Matching V2 consumption slice 1: the read-only entrypoint
 * that turns the pure §F6 kernels into a callable match over the dna_scores
 * production artifact.
 *
 * It loads a subject's scores and each caller-supplied candidate's scores
 * (via DnaScoreRepository), shapes them for BatchRelevanceMatcher, and returns
 * the resulting RankedMatchSet. Both directions are supported:
 *   - matchListingAgainstDemands: subject = a listing (property side) vs demand candidates
 *   - matchDemandAgainstListings: subject = a demand profile vs listing candidates
 *
 * GOVERNANCE (core rule): PURE READ-ONLY CONSUMER of dna_scores. It never
 * regenerates, modifies, or writes back into any generation table, and persists
 * no match results. When MATCHING_V2 is disabled it returns an inert empty set
 * without reading anything.
 *
 * DELIBERATELY OUT OF SCOPE (this slice): candidate discovery, marketplace-scale
 * querying, pagination, hard-gate/deal-breaker layering, persistence, and
 * consumer-facing exposure. The CALLER supplies the explicit candidate list.
 *
 * A candidate is: ['listing_type' => string, 'listing_id' => int]. The
 * counterpart id carried through into RankedMatch is the candidate's listing_id.
 */
class DnaMatchService
{
    public function __construct(
        private readonly BatchRelevanceMatcher $matcher,
        private readonly DnaScoreRepository $repository,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('matching.v2_enabled', false);
    }

    /**
     * Match one listing (its property-side scores) against explicit demand candidates.
     *
     * @param array<int,array{listing_type:string,listing_id:int}> $candidates
     */
    public function matchListingAgainstDemands(string $listingType, int $listingId, array $candidates): RankedMatchSet
    {
        if (! $this->isEnabled()) {
            return $this->inert();
        }

        $subjectScores = $this->repository->propertyScores($listingType, $listingId);

        $counterparts = $this->counterparts(
            $candidates,
            fn (string $t, int $id) => $this->repository->demandScores($t, $id),
        );

        return $this->matcher->matchListingAgainstDemands($subjectScores, $counterparts);
    }

    /**
     * Match one demand profile (its demand-side scores) against explicit listing candidates.
     *
     * @param array<int,array{listing_type:string,listing_id:int}> $candidates
     */
    public function matchDemandAgainstListings(string $listingType, int $listingId, array $candidates): RankedMatchSet
    {
        if (! $this->isEnabled()) {
            return $this->inert();
        }

        $subjectScores = $this->repository->demandScores($listingType, $listingId);

        $counterparts = $this->counterparts(
            $candidates,
            fn (string $t, int $id) => $this->repository->propertyScores($t, $id),
        );

        return $this->matcher->matchDemandAgainstListings($subjectScores, $counterparts);
    }

    /**
     * Resolve each candidate into the matcher's counterpart shape, loading its
     * scores via $loader. The counterpart id is the candidate's listing_id.
     *
     * @param array<int,array{listing_type:string,listing_id:int}> $candidates
     * @param callable(string,int):array $loader
     * @return array<int,array{id:int,scores:array}>
     */
    private function counterparts(array $candidates, callable $loader): array
    {
        $counterparts = [];

        foreach ($candidates as $candidate) {
            $type = $candidate['listing_type'];
            $id   = (int) $candidate['listing_id'];

            $counterparts[] = [
                'id'     => $id,
                'scores' => $loader($type, $id),
            ];
        }

        return $counterparts;
    }

    /** The inert result returned when Matching V2 is disabled — no reads, no work. */
    private function inert(): RankedMatchSet
    {
        return new RankedMatchSet([], 0);
    }
}
