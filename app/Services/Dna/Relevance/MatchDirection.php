<?php

namespace App\Services\Dna\Relevance;

/**
 * MatchDirection — Matching V2 consumption slice 2 (Candidate Discovery).
 *
 * Encapsulates which side of the shared dna_scores axis a subject sits on, and
 * therefore which `side` its candidate counterparts must carry. Making this an
 * enum (rather than a bare string) means the supply/demand direction cannot be
 * accidentally inverted at a call site.
 *
 *   - ListingToDemands: the subject is a listing (property side); its candidates
 *     are demand profiles (buyers/tenants) → counterpart side = 'demand'.
 *   - DemandToListings: the subject is a demand profile (demand side); its
 *     candidates are listings → counterpart side = 'property'.
 */
enum MatchDirection
{
    case ListingToDemands;
    case DemandToListings;

    /**
     * The dna_scores `side` value the candidate counterparts must carry.
     */
    public function counterpartSide(): string
    {
        return match ($this) {
            self::ListingToDemands => 'demand',
            self::DemandToListings => 'property',
        };
    }
}
