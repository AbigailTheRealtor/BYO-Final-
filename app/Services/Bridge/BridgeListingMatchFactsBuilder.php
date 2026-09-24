<?php

namespace App\Services\Bridge;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\ListingMatchFacts;
use App\Services\Stellar\Matching\ListingPeriodFacts;

/**
 * Bridge's side of the match engine's input: one `bridge_properties` row in,
 * one {@see ListingMatchFacts} out.
 *
 * THE PROVIDER BOUNDARY FOR SCORING
 * ---------------------------------
 * Everything the match engine used to read straight off a BridgeProperty — its
 * native columns and the RESO / `STELLAR_*` keys left behind in `raw_json` — is
 * read here, once, and nowhere else. `raw_json` is decoded once per listing
 * rather than once in the scorer and again in each explanation block.
 *
 * NO VALUE CHANGES ON THE WAY THROUGH
 * -----------------------------------
 * Each fact is read with the same expression the scorer or the result builder
 * used, so the model's casts decide every type exactly as before (decimals as
 * strings, the boolean columns as `true` / `false` / `null`). Feature lists and
 * the lease term are handed over as stated; the rules interpret them only when
 * a seeker asked about them. Changing any of that is a behaviour change, and
 * belongs to a phase that can show it — not to this seam.
 *
 * Pure: a model already in memory in, a value object out. No query, no network.
 */
final class BridgeListingMatchFactsBuilder
{
    public static function build(BridgeProperty $listing): ListingMatchFacts
    {
        $raw = $listing->raw_json ? json_decode($listing->raw_json, true) : [];
        if (!is_array($raw)) {
            $raw = [];
        }

        return new ListingMatchFacts(
            listingKey: $listing->listing_key ?? (string) $listing->id,

            latitude:        $listing->latitude,
            longitude:       $listing->longitude,
            city:            $listing->city,
            stateOrProvince: $listing->state_or_province,
            postalCode:      $listing->postal_code,
            countyOrParish:  $listing->county_or_parish,

            listPrice:      $listing->list_price,
            leaseFrequency: ListingPeriodFacts::leaseFrequency($raw),

            livingArea:        $listing->living_area,
            lotSizeSqft:       $listing->lot_size_sqft,
            yearBuilt:         $listing->year_built,
            buildingAreaTotal: isset($raw['BuildingAreaTotal']) && $raw['BuildingAreaTotal'] !== null
                ? (float) $raw['BuildingAreaTotal']
                : null,

            propertyType:    $listing->property_type,
            propertySubType: $listing->property_sub_type,

            poolPrivate: $listing->pool_private_yn,
            garage:      $listing->garage_yn,
            waterfront:  $listing->waterfront_yn,
            view:        $listing->view_yn,
            waterView:   $listing->water_view_yn,

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
