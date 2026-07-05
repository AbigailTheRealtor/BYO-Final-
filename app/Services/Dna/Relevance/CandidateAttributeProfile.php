<?php

namespace App\Services\Dna\Relevance;

/**
 * CandidateAttributeProfile — Matching V2 consumption slice 2B (Narrowing).
 *
 * A normalized, PROVIDER-NEUTRAL fact sheet for one (listing_type, listing_id).
 * Narrowers/gates read ONLY from this VO — never from a meta key, a provider
 * column, or a model — so the narrowing logic contains zero provider branching.
 * All provider-specific reads happen in a CandidateAttributeResolverInterface
 * implementation that produces these.
 *
 * `null` on a field means "unknown / not populated" — the gates treat unknown
 * per policy (fail-open by default), never as a hard value.
 *
 * @see docs/matching-v2-consumption-slice-2b-narrowing-compliance-scope.md §2.1
 */
final class CandidateAttributeProfile
{
    /**
     * @param string      $listingType
     * @param int         $listingId
     * @param string      $side              'property' | 'demand'
     * @param bool        $isEligibleListing  approved + active + a marketplace offer-listing
     * @param bool|null   $age55              normalized leasing_55_plus. On the property side this
     *                                        means "age-restricted community"; on the demand side
     *                                        it means "55+ eligible seeker". null = unknown.
     * @param string|null $propertyType       raw property_type (property side only)
     * @param float|null  $lat
     * @param float|null  $lng
     * @param string|null $city               property_location_dna.source_city
     * @param string|null $zip                property_location_dna.source_zip
     * @param string|null $county             property_location_dna.source_county
     */
    public function __construct(
        public readonly string $listingType,
        public readonly int $listingId,
        public readonly string $side,
        public readonly bool $isEligibleListing,
        public readonly ?bool $age55,
        public readonly ?string $propertyType,
        public readonly ?float $lat,
        public readonly ?float $lng,
        public readonly ?string $city,
        public readonly ?string $zip,
        public readonly ?string $county,
    ) {
    }

    public function hasGeoPoint(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /** True when no geographic signal at all is available (point or textual). */
    public function hasNoGeoSignal(): bool
    {
        return ! $this->hasGeoPoint()
            && ($this->city === null || $this->city === '')
            && ($this->zip === null || $this->zip === '')
            && ($this->county === null || $this->county === '');
    }

    public static function key(string $listingType, int $listingId): string
    {
        return $listingType . ':' . $listingId;
    }

    public function keyString(): string
    {
        return self::key($this->listingType, $this->listingId);
    }
}
