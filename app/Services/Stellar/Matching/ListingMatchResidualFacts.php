<?php

namespace App\Services\Stellar\Matching;

/**
 * The listing facts the match engine reads that a CanonicalListing does not carry.
 *
 * SCORER-OWNED, AND ONLY THE REMAINDER
 * ------------------------------------
 * {@see CanonicalListingMatchFactsBuilder} fills {@see ListingMatchFacts} from a
 * canonical listing first. These twenty facts have no canonical source today, so a
 * source hands them over here, explicitly, instead of the builder reaching back
 * into that source. Each property has the same name and type as the
 * ListingMatchFacts property it fills, and none of them duplicates a canonical one
 * (a test pins both).
 *
 * Three are matching-owned explanation context (`daysOnMarket`,
 * `floodZoneStated`, `schoolsListed`). The other seventeen are listing facts that
 * are not canonical yet — some are candidates for the canonical vocabulary, some
 * carry a feed's own vocabulary and need a governed decision first. When one
 * becomes canonical it moves out of this object.
 *
 * Values are carried as the source presented them, exactly as ListingMatchFacts
 * carries them; nothing here interprets them.
 *
 * Ephemeral: built per listing, never persisted, never rendered. Pure.
 */
final class ListingMatchResidualFacts
{
    /**
     * @param int|null    $lotSizeSqft                   lot size stated in square feet
     * @param float|null  $buildingAreaTotal             set when the source states a building area
     * @param mixed       $propertySubType               as stated
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
     */
    public function __construct(
        // Size
        public readonly ?int $lotSizeSqft,
        public readonly ?float $buildingAreaTotal,

        // Property type
        public readonly mixed $propertySubType,

        // Amenities
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
    ) {}

    /**
     * A source that supplies none of these facts. Every value is unknown; the two
     * derived flags are false, which is what they already mean when a source
     * states nothing ("no flood-zone designation stated", "no school named").
     *
     * Written out so an empty residual is always a decision someone made, never
     * an argument someone forgot.
     */
    public static function unknown(): self
    {
        return new self(
            lotSizeSqft: null,
            buildingAreaTotal: null,
            propertySubType: null,
            view: null,
            waterView: null,
            associationFee: null,
            associationFeeFrequency: null,
            association: null,
            taxAnnualAmount: null,
            cdd: null,
            newConstruction: null,
            petsAllowed: null,
            communityFeatures: null,
            associationAmenities: null,
            greenEnergyEfficient: null,
            greenBuildingVerificationType: null,
            leaseTerm: null,
            daysOnMarket: null,
            floodZoneStated: false,
            schoolsListed: false,
        );
    }
}
