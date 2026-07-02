<?php

namespace App\Services\Dna\Scores;

use App\Models\DnaScore;
use App\Services\Canonical\CanonicalListingResolver;
use Illuminate\Support\Carbon;

/**
 * PetFriendlinessDnaGenerator — orchestration for the first Beyond-MLS slice.
 *
 * Resolves a listing to its canonical projection (§F1), computes the
 * appropriate side of the Pet-Friendliness score, and persists it to the
 * additive dna_scores table (§F2). This is the only class in the slice that
 * writes to the database.
 *
 * GOVERNANCE:
 *   - Writes ONLY to dna_scores (upsert on the unique key). Touches nothing
 *     that already exists (property_location_dna, listing_compatibility_scores,
 *     bid_score_snapshots, *_agent_auctions).
 *   - No AI, no external API calls.
 *
 * Side selection: property (policy) for landlord/seller listings; demand
 * (profile) for tenant/buyer listings.
 */
class PetFriendlinessDnaGenerator
{
    private const DEMAND_TYPES = ['tenant_agent', 'buyer_agent'];

    private CanonicalListingResolver $resolver;
    private PetFriendlinessScoreService $scorer;

    public function __construct(CanonicalListingResolver $resolver, PetFriendlinessScoreService $scorer)
    {
        $this->resolver = $resolver;
        $this->scorer   = $scorer;
    }

    /**
     * Compute + persist the Pet-Friendliness score for one listing.
     * Returns the persisted DnaScore, or null if the listing can't be resolved.
     */
    public function generateForListing(string $listingType, int $listingId): ?DnaScore
    {
        $canonical = $this->resolver->resolve($listingType, $listingId);
        if ($canonical === null) {
            return null;
        }

        $result = in_array($listingType, self::DEMAND_TYPES, true)
            ? $this->scorer->scoreDemand($canonical)
            : $this->scorer->scoreProperty($canonical);

        return DnaScore::updateOrCreate(
            [
                'listing_type' => $listingType,
                'listing_id'   => $listingId,
                'score_key'    => $result['score_key'],
                'side'         => $result['side'],
            ],
            [
                'value'             => $result['value'],
                'data_completeness' => $result['data_completeness'],
                'confidence'        => $result['confidence'],
                'explanation'       => $result['explanation'],
                'inputs_json'       => $result['inputs'],
                'version'           => $result['version'],
                'computed_at'       => Carbon::now(),
            ]
        );
    }
}
