<?php

namespace App\Support\AskAi;

use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\ListingImport\MlsFieldMap;
use App\Services\ListingImport\MlsListingPrefillService;
use App\Support\AskAi\AskAiPropertyTypeResolver as PT;

/**
 * AskAiFieldApplicability — which property types' Offer Listing wizard COLLECTS each public
 * Ask AI field.
 *
 * WHY THIS EXISTS
 * ---------------
 * A catalog entry's `property_types` says which listings may be asked a question. Until this
 * table existed those declarations were written by hand per entry, and nothing checked them
 * against the form. They were wrong in both directions: Seller Commercial listings collect
 * bathrooms, appliances, HOA, CDD and home-warranty answers that no commercial question could
 * reach, while a question admitted for a type whose form never asks the field can only ever
 * answer from stale or imported data the owner cannot see or correct.
 *
 * THE SOURCE OF TRUTH IS THE BLADE, NOT THIS FILE
 * -----------------------------------------------
 * Every row records the server-side `@if ($property_type ...)` that gates the input, in
 * resources/views/livewire/offer-listing/offer-{seller,landlord}-tabs/commission-based/
 * (Create and Edit include the same partials). "every type" means the input sits under no
 * property-type conditional. A field whose parent is conditional on a VALUE rather than a
 * type (HOA children under has_hoa = Yes) is applicable to every type the parent is.
 *
 * LEGACY_NO_INPUT is not "every type": the current form renders no input for the key at all,
 * so a stored value is legacy or imported and the form cannot say which types it belongs to.
 * The contract leaves questions over those fields to their own declaration.
 *
 * Keyed by CANONICAL context key (AskAiContextBuilderService::CANONICAL_SOURCE_MAP), with
 * PT tokens. Seller types: residential, income, commercial, business, vacant_land. Landlord
 * types: residential, commercial (config/ai_faq_landlord.php gating; the resolver maps
 * 'Residential Property' / 'Commercial Property' to them).
 *
 * MLS QUICK IMPORT DOES NOT WIDEN THIS, DELIBERATELY
 * --------------------------------------------------
 * Import can write facts the form does not render for a type (zoning or bedrooms where the
 * form has no such input), because MlsFactProjection's applicability gate covers only pool,
 * garage and carport. CLAUDE.md names that state "invisible data with the authority of an
 * import behind it": the owner can neither see nor correct it. Widening the gate to follow it
 * would also bring back the defect this class exists to prevent — a Vacant Land listing with a
 * stray bedrooms row being asked "How many bedrooms are there?". So the gate is the FORM.
 * importTypes() reports, and never gates, what import can write, so the MLS coverage audit can
 * name every field import writes for a type whose form does not collect it — an import-side
 * fix, not an Ask AI one.
 *
 * Pure: no container, no config, no I/O.
 */
final class AskAiFieldApplicability
{
    public const LEGACY_NO_INPUT = 'legacy_no_input';

    private const ALL_SELLER   = [PT::RESIDENTIAL, PT::INCOME, PT::COMMERCIAL, PT::BUSINESS, PT::VACANT_LAND];
    private const ALL_LANDLORD = [PT::RESIDENTIAL, PT::COMMERCIAL];

    /** Seller: not Vacant Land. property-preferences :1911 / :2134 / :2445 blocks. */
    private const SELLER_BUILT = [PT::RESIDENTIAL, PT::INCOME, PT::COMMERCIAL, PT::BUSINESS];

    /** Property types each role's wizard offers. */
    public const ROLE_TYPES = [
        'seller'   => self::ALL_SELLER,
        'landlord' => self::ALL_LANDLORD,
    ];

    /**
     * role => canonical field => property types that collect it, or LEGACY_NO_INPUT.
     *
     * SP = offer-seller-tabs/commission-based/, LP = offer-landlord-tabs/commission-based/.
     *
     * @var array<string, array<string, list<string>|string>>
     */
    public const MAP = [
        'seller' => [
            'description'                    => self::ALL_SELLER,                              // SP additional-details:16
            'asking_price'                   => self::ALL_SELLER,                              // SP seller-terms:299
            'closing_date'                   => self::ALL_SELLER,                              // SP seller-terms:183
            'occupant_status'                => self::SELLER_BUILT,                            // SP seller-terms:203 != 'Vacant Land'
            'home_warranty_offered'          => self::ALL_SELLER,                              // SP seller-terms:2118
            'bedrooms'                       => [PT::RESIDENTIAL],                             // SP property-preferences:808
            'bathrooms'                      => [PT::RESIDENTIAL, PT::BUSINESS, PT::COMMERCIAL], // :841
            'square_feet'                    => [PT::RESIDENTIAL, PT::BUSINESS, PT::COMMERCIAL], // :891
            'year_built'                     => self::SELLER_BUILT,                            // :1911 / :2134 / :2445
            'total_acreage'                  => self::ALL_SELLER,                              // :953
            'lot_size'                       => self::ALL_SELLER,                              // reads total_acreage (:953)
            'lot_dimensions'                 => [PT::VACANT_LAND],                             // :2939 inside :2743
            'zoning'                         => [PT::COMMERCIAL, PT::BUSINESS, PT::VACANT_LAND], // :2156 / :2537 / :2796
            'pool'                           => [PT::RESIDENTIAL, PT::INCOME],                 // :1274
            'pool_type'                      => [PT::RESIDENTIAL, PT::INCOME],                 // :1297 inside :1274
            'garage'                         => [PT::RESIDENTIAL],                             // :1049
            'carport'                        => [PT::RESIDENTIAL],                             // :1012
            'waterfront'                     => self::ALL_SELLER,                              // :1166
            'water_access'                   => self::ALL_SELLER,                              // :1183
            'water_view'                     => self::ALL_SELLER,                              // :1207
            'waterfront_feet'                => self::ALL_SELLER,                              // :1244
            'interior_features'              => self::ALL_SELLER,                              // :1258
            'appliances'                     => self::SELLER_BUILT,                            // :964 (not Vacant Land)
            'roof_type'                      => self::SELLER_BUILT,                            // :1933 / :2169 / :2550
            'exterior_construction'          => self::SELLER_BUILT,                            // :1955 / :2191 / :2572
            'foundation'                     => self::SELLER_BUILT,                            // :1977 / :2213 / :2594
            'heating_and_fuel'               => self::SELLER_BUILT,                            // :1999 / :2345 / :2682
            'heating_fuel'                   => self::SELLER_BUILT,                            // same meta key
            'air_conditioning'               => self::SELLER_BUILT,                            // :2021 / :2367 / :2704
            'building_features'              => [PT::COMMERCIAL],                              // :2428 inside :2134
            'furnished'                      => [PT::COMMERCIAL],                              // reads building_features
            'utilities'                      => self::ALL_SELLER,                              // :2087 / :2279 / :2616 / :3009
            'water'                          => self::ALL_SELLER,                              // :2043 / :2301 / :2638 / :3031
            'water_source'                   => self::ALL_SELLER,                              // same meta key
            'sewer'                          => self::ALL_SELLER,                              // :2065 / :2323 / :2660 / :3053
            'property_items'                 => self::ALL_SELLER,                              // :683
            'pets_allowed'                   => [PT::RESIDENTIAL, PT::INCOME],                 // :1417
            'number_of_pets_allowed'         => [PT::RESIDENTIAL, PT::INCOME],                 // :1447 inside :1436
            'max_pet_weight'                 => [PT::RESIDENTIAL, PT::INCOME],                 // :1483 inside :1436
            // tax-legal-hoa-disclosures has no property-type conditional at all.
            'hoa_association'                => self::ALL_SELLER,                              // :344
            'hoa_fee'                        => self::ALL_SELLER,                              // :418 under has_hoa
            'hoa_payment_schedule'           => self::ALL_SELLER,                              // :437
            'association_name'               => self::ALL_SELLER,                              // :400
            'hoa_name'                       => self::ALL_SELLER,                              // same meta key
            'association_fee_includes'       => self::ALL_SELLER,                              // :534
            'association_approval_required'  => self::ALL_SELLER,                              // :472
            'rental_restrictions'            => self::ALL_SELLER,                              // :598 under has_hoa
            'has_cdd'                        => self::ALL_SELLER,                              // :244
            'annual_cdd_fee'                 => self::ALL_SELLER,                              // :264
            'has_special_assessments'        => self::ALL_SELLER,                              // :282
            'special_assessment_amount'      => self::ALL_SELLER,                              // :302
            'special_assessment_description' => self::ALL_SELLER,                              // :318
            'additional_parcels'             => self::ALL_SELLER,                              // :75
            'total_parcel_count'             => self::ALL_SELLER,                              // :94
            'flood_zone_code'                => self::ALL_SELLER,                              // :149
            'annual_property_taxes'          => self::ALL_SELLER,                              // :58
            'tax_year'                       => self::ALL_SELLER,                              // :42
        ],
        'landlord' => [
            'description'              => self::ALL_LANDLORD,                     // LP additional-details:16
            'rent_amount'              => self::ALL_LANDLORD,                     // LP lease-terms:1112
            'available_date'           => self::ALL_LANDLORD,                     // lease-terms:1261
            'renewal_option'           => self::ALL_LANDLORD,                     // lease-terms:1358
            'additional_lease_terms'   => self::ALL_LANDLORD,                     // lease-terms:1394
            'smoking_policy'           => self::ALL_LANDLORD,                     // lease-terms:1559
            'subletting_policy'        => self::ALL_LANDLORD,                     // lease-terms:1591
            'lease_terms'              => [PT::COMMERCIAL],                       // lease-terms:1027 inside :928 (terms_of_lease — lease STRUCTURE)
            'pet_fee_type'             => self::ALL_LANDLORD,                     // lease-terms:1280
            'pet_fee_amount'           => self::ALL_LANDLORD,                     // lease-terms:1307
            'bedrooms'                 => [PT::RESIDENTIAL],                      // LP property-preferences:388
            'bathrooms'                => self::ALL_LANDLORD,                     // :426
            'square_feet'              => [PT::RESIDENTIAL],                      // :461 (commercial: minimum_leaseable)
            'year_built'               => self::ALL_LANDLORD,                     // :1005 / :1321
            'condition_prop'           => self::ALL_LANDLORD,                     // :364
            'property_items'           => self::ALL_LANDLORD,                     // :316
            'appliances'               => self::ALL_LANDLORD,                     // :569
            'interior_features'        => self::ALL_LANDLORD,                     // :836
            'parking_terms'            => self::ALL_LANDLORD,                     // :725
            'building_features'        => [PT::COMMERCIAL],                       // :1610 inside :1310
            'water_view'               => self::ALL_LANDLORD,                     // :782
            'view'                     => self::ALL_LANDLORD,                     // :899 (view_preference)
            'pet_policy'               => [PT::RESIDENTIAL],                      // pets :1769 inside :1757
            'lot_dimensions'           => [PT::RESIDENTIAL],                      // :1020 inside :994
            'zoning'                   => [PT::COMMERCIAL],                       // :1336 inside :1310
            'waterfront'               => self::ALL_LANDLORD,                     // :740
            'water_access'             => self::ALL_LANDLORD,                     // :757
            'roof_type'                => [PT::RESIDENTIAL],                      // :1034 inside :994
            'exterior_construction'    => [PT::RESIDENTIAL],                      // :1059
            'foundation'               => [PT::RESIDENTIAL],                      // :1084
            'heating_fuel'             => self::ALL_LANDLORD,                     // :1109 / :1513
            'air_conditioning'         => self::ALL_LANDLORD,                     // :1135 / :1539
            'water'                    => self::ALL_LANDLORD,                     // :1161 / :1462
            'sewer'                    => self::ALL_LANDLORD,                     // :1186 / :1487
            // LP tax-legal-hoa-disclosures: no property-type conditional.
            'has_hoa'                  => self::ALL_LANDLORD,                     // :348
            'association_name'         => self::ALL_LANDLORD,                     // :403
            'association_fee_amount'   => self::ALL_LANDLORD,                     // :421
            'association_fee_frequency' => self::ALL_LANDLORD,                    // :440
            'association_amenities'    => self::ALL_LANDLORD,                     // :573
            'association_fee_includes' => self::ALL_LANDLORD,                     // under has_hoa
            'leasing_restrictions'     => self::ALL_LANDLORD,                     // :601
            'flood_zone_code'          => self::ALL_LANDLORD,                     // :149
            'annual_property_taxes'    => self::ALL_LANDLORD,                     // :58
            'tax_year'                 => self::ALL_LANDLORD,                     // :42
            // No input on the current landlord form. unit_size is declared, loaded and saved
            // by LandlordOfferListing without ever being rendered; the others are written only
            // by legacy or import paths.
            'unit_size'                => self::LEGACY_NO_INPUT,
            'number_of_units'          => self::LEGACY_NO_INPUT,
            'pet_species_allowed'      => self::LEGACY_NO_INPUT,
            'pet_max_weight_lbs'       => self::LEGACY_NO_INPUT,
            'pet_deposit_fee_rent'     => self::LEGACY_NO_INPUT,
        ],
    ];

    /** @return list<string>|string|null the FORM half: PT tokens, LEGACY_NO_INPUT, or null when undeclared */
    public static function for(string $role, string $field): array|string|null
    {
        return self::MAP[$role][$field] ?? null;
    }

    public static function collects(string $role, string $field, string $propertyType): bool
    {
        $types = self::for($role, $field);

        return is_array($types) && in_array($propertyType, $types, true);
    }

    /**
     * Types MLS quick import can write this canonical field for. REPORTING ONLY — never a gate;
     * see the class docblock.
     *
     * @return list<string>
     */
    public static function importTypes(string $role, string $field): array
    {
        $sources = AskAiContextBuilderService::CANONICAL_SOURCE_MAP[$role][$field] ?? null;
        if ($sources === null || !isset(self::ROLE_TYPES[$role])) {
            return [];
        }
        $metaKeys = array_filter((array) $sources, static fn ($k) => is_string($k) && !str_starts_with($k, 'native:'));

        $map   = MlsFieldMap::forRole($role);
        $scope = MlsFieldMap::propertyTypeApplicability($role);
        $types = [];

        foreach (array_unique(array_values(MlsListingPrefillService::ALLOWED_FIELDS)) as $importKey) {
            // Landlord `furnished` is never written: the projection merges furnished only into
            // the SELLER's building_features list.
            if ($role === 'landlord' && $importKey === 'furnished') {
                continue;
            }
            $target = $map[$importKey] ?? null;
            $meta   = is_string($target) ? ltrim($target, '*') : null;
            if ($role === 'seller' && $importKey === 'furnished') {
                $meta = 'building_features';
            }
            if ($meta === null || $meta === '' || !in_array($meta, $metaKeys, true)) {
                continue;
            }

            if (!isset($scope[$importKey])) {
                return self::ROLE_TYPES[$role];
            }
            foreach ($scope[$importKey] as $raw) {
                foreach (PT::tokensFor($role, $raw) as $token) {
                    $types[$token] = true;
                }
            }
        }

        return array_values(array_filter(self::ROLE_TYPES[$role], static fn ($t) => isset($types[$t])));
    }
}
