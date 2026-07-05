<?php

namespace App\Services\Dna\Relevance;

use App\Models\DnaScore;

/**
 * DnaScoreRepository — the read-only loader that feeds the §F6 relevance kernels
 * from the dna_scores production artifact (Matching V2, consumption slice 1).
 *
 * GOVERNANCE (core rule): Matching V2 is a PURE READ-ONLY CONSUMER of dna_scores.
 * This repository ONLY reads — no writes, no updateOrCreate, no caching, and it
 * never touches any generation table. Generation and matching stay fully
 * separated.
 *
 * Returns plain arrays (not lazy generators) so the batch matcher can re-read a
 * subject's scores across every counterpart pairing.
 */
class DnaScoreRepository
{
    /**
     * The property-side (supply) scores for a listing.
     *
     * @return array<int,DnaScore>
     */
    public function propertyScores(string $listingType, int $listingId): array
    {
        return $this->scores($listingType, $listingId, 'property');
    }

    /**
     * The demand-side (searcher preference) scores for a listing.
     *
     * @return array<int,DnaScore>
     */
    public function demandScores(string $listingType, int $listingId): array
    {
        return $this->scores($listingType, $listingId, 'demand');
    }

    /**
     * @return array<int,DnaScore>
     */
    private function scores(string $listingType, int $listingId, string $side): array
    {
        return DnaScore::query()
            ->where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->where('side', $side)
            ->get()
            ->all();
    }
}
