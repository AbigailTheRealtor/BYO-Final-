<?php

namespace App\Services\Stellar\Matching;

/**
 * The listing facts the match engine scores and explains — and nothing else.
 *
 * SCORER-OWNED INPUT, NOT A PROPERTY MODEL
 * ----------------------------------------
 * This is the parameter list of {@see BuyerMatchScorer::scoreFacts()} and
 * {@see BuyerMatchResultBuilder}, written down as one object. It exists so the
 * scoring and explanation rules never name a provider: no model, no raw feed
 * record, no provider field name. A provider-side builder fills it; the Bridge
 * one is {@see \App\Services\Bridge\BridgeListingMatchFactsBuilder}.
 *
 * It is not persisted, not a model, not `CanonicalListing`, not
 * `PropertyCandidate`, not an API response. It holds exactly the facts the
 * current rules read — including ones that are not canonical today (sub-type,
 * CDD, view, lease term, community and green features, building area) —
 * because the rules read them.
 *
 * VALUES ARE CARRIED AS THE SOURCE PRESENTED THEM
 * -----------------------------------------------
 * Every value is the one the rules read before this object existed: a decimal
 * column arrives as its decimal string, a count or name column as stored, a
 * boolean as `true` / `false` / `null`. Nothing is re-typed or normalised here,
 * because the explanations interpolate some of these values into text and a
 * silent cast would change what a seeker reads. Feature lists and the lease
 * term are likewise carried as stated, and the rules interpret them only when a
 * seeker asked about them, exactly as before. Normalisation belongs to the
 * phase that introduces a canonical source, where each change can be shown.
 *
 * Pure: no container, no query, no I/O.
 */
final class ListingMatchFacts
{
    /**
     * @param string      $listingKey                    identity carried onto the result
     * @param string|null $latitude                      decimal string
     * @param string|null $longitude                     decimal string
     * @param string|null $listPrice                     decimal string; on a lease, the periodic rent
     * @param string|null $leaseFrequency                the period a lease price covers, as stated
     * @param mixed       $livingArea                    as stored
     * @param float|null  $buildingAreaTotal             set when the source states a building area
     * @param string|null $associationFee                decimal string
     * @param string|null $associationFeeFrequency       the period the fee covers, as stated
     * @param string|null $taxAnnualAmount               decimal string
     * @param mixed       $petsAllowed                   the stated pet policy, as stored
     * @param mixed       $communityFeatures             a feature list (or one feature), as stated
     * @param mixed       $associationAmenities          a feature list (or one feature), as stated
     * @param mixed       $greenEnergyEfficient          a feature list (or one feature), as stated
     * @param mixed       $greenBuildingVerificationType a feature list (or one feature), as stated
     * @param mixed       $leaseTerm                     the stated lease term, as stated
     * @param mixed       $daysOnMarket                  as stated
     * @param bool        $floodZoneStated               the source carries a flood-zone designation
     * @param bool        $schoolsListed                 the source names an elementary or high school
     * @param ListingSmartTagFacts|null $smartTags        resolved Smart Tags; null = not supplied, unknown
     */
    public function __construct(
        public readonly string $listingKey,

        // Location
        public readonly ?string $latitude,
        public readonly ?string $longitude,
        public readonly mixed $city,
        public readonly mixed $stateOrProvince,
        public readonly mixed $postalCode,
        public readonly mixed $countyOrParish,

        // Price
        public readonly ?string $listPrice,
        public readonly ?string $leaseFrequency,

        // Size
        public readonly mixed $livingArea,
        public readonly ?int $lotSizeSqft,
        public readonly ?int $yearBuilt,
        public readonly ?float $buildingAreaTotal,

        // Property type
        public readonly mixed $propertyType,
        public readonly mixed $propertySubType,

        // Amenities
        public readonly ?bool $poolPrivate,
        public readonly ?bool $garage,
        public readonly ?bool $waterfront,
        public readonly ?bool $view,
        public readonly ?bool $waterView,

        // Financial
        public readonly ?string $associationFee,
        public readonly ?string $associationFeeFrequency,
        public readonly ?bool $association,
        public readonly ?string $taxAnnualAmount,
        public readonly ?bool $cdd,

        // Lifestyle
        public readonly ?bool $newConstruction,
        public readonly mixed $petsAllowed,
        public readonly mixed $communityFeatures,
        public readonly mixed $associationAmenities,
        public readonly mixed $greenEnergyEfficient,
        public readonly mixed $greenBuildingVerificationType,
        public readonly mixed $leaseTerm,

        // Explanation-only context
        public readonly mixed $daysOnMarket,
        public readonly bool $floodZoneStated,
        public readonly bool $schoolsListed,

        // Resolved Smart Tags — supplied beside the row, not read from it (see withSmartTags())
        public readonly ?ListingSmartTagFacts $smartTags = null,
    ) {}

    /**
     * The same facts with the listing's resolved Smart Tags attached.
     *
     * A provider-side builder reads a row; a listing's Smart Tags are not in the row, so they
     * are read beside it, before scoring, and attached here. Null leaves them unknown.
     */
    public function withSmartTags(?ListingSmartTagFacts $smartTags): self
    {
        return new self(...array_merge(get_object_vars($this), ['smartTags' => $smartTags]));
    }
}
