<?php

namespace App\Services\Bridge;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\ListingMatchResidualFacts;
use App\Services\Stellar\Matching\ListingPeriodFacts;

/**
 * Bridge's side of the canonical match input: the facts a CanonicalListing does
 * not carry, read off one `bridge_properties` row.
 *
 * Every value is read with the SAME expression {@see BridgeListingMatchFactsBuilder}
 * uses for that fact, so the canonical path and the settled Bridge path hand the
 * scorer identical residual values — a test asserts it on every committed fixture.
 * No normalisation is added here: a residual fact reaches the scorer exactly as it
 * does today, and the rules that interpret it are unchanged.
 *
 * Pure: a model already in memory in, a value object out. No query, no network,
 * and the row is never written.
 */
final class BridgeResidualMatchFactsReader
{
    public static function read(BridgeProperty $listing): ListingMatchResidualFacts
    {
        $raw = $listing->raw_json ? json_decode($listing->raw_json, true) : [];
        if (!is_array($raw)) {
            $raw = [];
        }

        return new ListingMatchResidualFacts(
            lotSizeSqft:       $listing->lot_size_sqft,
            buildingAreaTotal: isset($raw['BuildingAreaTotal']) && $raw['BuildingAreaTotal'] !== null
                ? (float) $raw['BuildingAreaTotal']
                : null,

            propertySubType: $listing->property_sub_type,

            view:      $listing->view_yn,
            waterView: $listing->water_view_yn,

            associationFee:          $listing->association_fee,
            associationFeeFrequency: ListingPeriodFacts::associationFeeFrequency($raw),
            association:             $listing->association_yn,
            taxAnnualAmount:         $listing->tax_annual_amount,
            cdd:                     $listing->cdd_yn,

            newConstruction:               $listing->new_construction_yn,
            petsAllowed:                   $listing->pets_allowed,
            communityFeatures:             $raw['CommunityFeatures'] ?? null,
            associationAmenities:          $raw['AssociationAmenities'] ?? null,
            greenEnergyEfficient:          $raw['GreenEnergyEfficient'] ?? null,
            greenBuildingVerificationType: $raw['GreenBuildingVerificationType'] ?? null,
            leaseTerm:                     $raw['LeaseTerm'] ?? null,

            daysOnMarket:    $raw['DaysOnMarket'] ?? null,
            floodZoneStated: isset($raw['STELLAR_FloodZoneCode']),
            schoolsListed:   isset($raw['ElementarySchool']) || isset($raw['HighSchool']),
        );
    }
}
