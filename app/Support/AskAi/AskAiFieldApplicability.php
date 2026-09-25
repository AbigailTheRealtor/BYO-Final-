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
 * THIS MAP IS THE FORM; MLS IMPORT IS ADMITTED SEPARATELY (owner decision, 2026-09-24)
 * -------------------------------------------------------------------------------------
 * Import can write facts the form does not render for a type (bedrooms, bathrooms and square
 * footage on an Income listing; square footage on a Commercial Lease), because
 * MlsFactProjection's applicability gate covers only pool, garage and carport — and the public
 * page prints them. Until 2026-09-24 Ask AI refused them, which lost facts the page beside it
 * showed. The owner decided the PAGE governs: AskAiPublicPropertyQuestionService admits a
 * question for such a field when the listing is MLS-imported AND importTypes() says import
 * writes that field for the listing's type. That admission lives in the question service, not
 * here, so this map stays the form's own truth and a MANUAL listing with a stray value on a
 * type whose form never collected it (Vacant Land with a bedrooms row) is still never asked.
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

            // ── Universal coverage audit (2026-09-24) ──────────────────────────────
            // SP tax-legal-hoa-disclosures (no type conditional; HOA children under has_hoa).
            'flood_insurance_required'       => self::ALL_SELLER,                              // :185
            'flood_zone_panel'               => self::ALL_SELLER,                              // :204
            'association_type'               => self::ALL_SELLER,                              // :365
            'association_approval_process'   => self::ALL_SELLER,                              // :491
            'association_application_fee'    => self::ALL_SELLER,                              // under :482
            'association_amenities'          => self::ALL_SELLER,                              // :570
            'min_lease_period'               => self::ALL_SELLER,                              // :620 (leasing_restrictions Yes)
            'max_leases_per_year'            => self::ALL_SELLER,                              // :651
            'additional_lease_restrictions'  => self::ALL_SELLER,                              // :677
            // SP property-preferences
            'condition_prop'                 => self::SELLER_BUILT,                            // :785 (!= Vacant Land :761)
            'building_sqft'                  => self::SELLER_BUILT,                            // :916 (!= Vacant Land :908)
            'pet_types_allowed'              => [PT::RESIDENTIAL, PT::INCOME],                 // :1460 inside :1436 (pets Yes)
            'business_assets'                => [PT::INCOME, PT::COMMERCIAL, PT::BUSINESS],    // :1538 inside :1528
            'business_assets_other'          => [PT::INCOME, PT::COMMERCIAL, PT::BUSINESS],    // :1552 (assets Other)
            'real_estate_purchase'           => [PT::BUSINESS],                                // :1516 inside :1506
            'total_buildings'                => [PT::INCOME],                                  // :1581 inside :1558
            'number_water_meters'            => [PT::INCOME],                                  // :2113 inside :2104
            'number_electric_meters'         => [PT::INCOME],                                  // :2126
            'ceiling_height'                 => [PT::COMMERCIAL],                              // :2411 inside :2134
            'road_frontage'                  => [PT::COMMERCIAL, PT::VACANT_LAND],             // :2235 / :2965
            'road_surface_type'              => [PT::COMMERCIAL, PT::VACANT_LAND],             // :2257 / :2987
            'electrical_service'             => [PT::COMMERCIAL, PT::BUSINESS],                // :2389 / :2726
            'business_name'                  => [PT::BUSINESS],                                // :2454 inside :2445
            'year_established'               => [PT::BUSINESS],                                // :2467
            'licenses'                       => [PT::BUSINESS],                                // :2480
            'sale_includes'                  => [PT::BUSINESS],                                // :2502
            'current_use'                    => [PT::VACANT_LAND],                             // :2752 inside :2743
            'current_adjacent_use'           => [PT::VACANT_LAND],                             // :2774
            'water_available'                => [PT::VACANT_LAND],                             // :2809
            'sewer_available'                => [PT::VACANT_LAND],                             // :2835
            'electric_available'             => [PT::VACANT_LAND],                             // :2861
            'gas_available'                  => [PT::VACANT_LAND],                             // :2887
            'telecom_available'              => [PT::VACANT_LAND],                             // :2913
            'front_footage'                  => [PT::VACANT_LAND],                             // :2952
            'number_of_wells'                => [PT::VACANT_LAND],                             // :3075
            'number_of_septics'              => [PT::VACANT_LAND],                             // :3088
            'fences'                         => [PT::VACANT_LAND],                             // :3101
            'vegetation'                     => [PT::VACANT_LAND],                             // :3123
            'buildable'                      => [PT::VACANT_LAND],                             // :3145
            'easements'                      => [PT::VACANT_LAND],                             // :3161
            // The Business Type input sits in a wrapper that is always `d-none` (:741) — no
            // user can reach it, so only import or a legacy row writes the field.
            'business_type'                  => self::LEGACY_NO_INPUT,
            // SP financial-details
            'gross_annual_income'            => [PT::INCOME],                                  // :59 inside :13
            'annual_operating_expenses'      => [PT::INCOME],                                  // :76
            'rent_roll_available'            => [PT::INCOME],                                  // :92
            'operating_statement_available'  => [PT::INCOME],                                  // :109
            'price_per_sqft'                 => [PT::COMMERCIAL],                              // :168 inside :122
            'existing_lease_type'            => [PT::COMMERCIAL],                              // :184
            'lease_expiration'               => [PT::COMMERCIAL],                              // :218
            'lease_assignable'               => [PT::COMMERCIAL],                              // :231
            'annual_revenue'                 => [PT::BUSINESS],                                // :291 inside :245
            'gross_profit'                   => [PT::BUSINESS],                                // :308
            'sde_ebitda'                     => [PT::BUSINESS],                                // :325
            'inventory_value'                => [PT::BUSINESS],                                // :342
            'ffe_value'                      => [PT::BUSINESS],                                // :359
            'employee_count'                 => [PT::BUSINESS],                                // :407
            'financial_statements_available' => [PT::BUSINESS],                                // :421
            'tax_returns_available'          => [PT::BUSINESS],                                // :438
            'nda_required'                   => [PT::BUSINESS],                                // :455
            'business_location_leased'       => [PT::BUSINESS],                                // :477
            'business_lease_monthly_rent'    => [PT::BUSINESS],                                // :495 (location leased Yes :486)
            'business_lease_expiration'      => [PT::BUSINESS],                                // :507
            'business_lease_renewal_options' => [PT::BUSINESS],                                // :518
            'business_lease_assignable'      => [PT::BUSINESS],                                // :534
            'business_lease_additional_terms' => [PT::BUSINESS],                               // :551
            // SP seller-terms: no property-type conditional; financing children open on the
            // offered_financing selection (ConditionalTerms enforces it at answer time).
            'occupied_until'                     => self::SELLER_BUILT,                        // under occupant_status (!= Vacant Land)
            'sale_provision'                     => self::ALL_SELLER,                          // :45
            'sale_provision_assignment'          => self::ALL_SELLER,                          // under :45 (Assignment Contract)
            'assignment_fee_type'                => self::ALL_SELLER,                          // under sale_provision_assignment Yes
            'assignment_fee'                     => self::ALL_SELLER,                          // under a chosen fee structure ($ / %)
            'unit_mix_summary'                   => [PT::INCOME],                              // unit_type_configurations, inside :1558
            'cryptocurrency_type'                => self::ALL_SELLER,                          // :662
            'crypto_percentage'                  => self::ALL_SELLER,                          // :676
            'cash_percentage_crypto'             => self::ALL_SELLER,                          // :696
            'crypto_exchange_method'             => self::ALL_SELLER,                          // :714
            'crypto_custodian_wallet'            => self::ALL_SELLER,                          // :729
            'crypto_transaction_fees'            => self::ALL_SELLER,                          // :744
            'crypto_transfer_timing'             => self::ALL_SELLER,                          // :763
            'exchange_item'                      => self::ALL_SELLER,                          // :804
            'exchange_item_value'                => self::ALL_SELLER,                          // :834
            'exchange_item_condition'            => self::ALL_SELLER,                          // :852
            'exchange_additional_cash'           => self::ALL_SELLER,                          // :877
            'value_determination'                => self::ALL_SELLER,                          // :895
            'exchange_transfer_method'           => self::ALL_SELLER,                          // :910
            'exchange_liens_disclosure'          => self::ALL_SELLER,                          // :925
            'exchange_liens_details'             => self::ALL_SELLER,                          // :937
            'exchange_inspection_rights'         => self::ALL_SELLER,                          // :953
            'lease_option_price'                 => self::ALL_SELLER,                          // :980
            'lease_option_payment'               => self::ALL_SELLER,                          // :997
            'lease_option_duration'              => self::ALL_SELLER,                          // :1012
            'option_fee_offered'                 => self::ALL_SELLER,                          // :1025
            'option_fee_amount'                  => self::ALL_SELLER,                          // :1038
            'lease_option_fee_credit'            => self::ALL_SELLER,                          // :1055
            'lease_option_fee_credit_percentage' => self::ALL_SELLER,                          // :1069
            'lease_option_conditions'            => self::ALL_SELLER,                          // :1087
            'lease_option_terms'                 => self::ALL_SELLER,                          // :1101
            'lease_option_maintenance'           => self::ALL_SELLER,                          // :1116
            'lease_option_extension_terms'       => self::ALL_SELLER,                          // :1135
            'lease_purchase_price'               => self::ALL_SELLER,                          // :1166
            'lease_purchase_payment'             => self::ALL_SELLER,                          // :1185
            'lease_purchase_duration'            => self::ALL_SELLER,                          // :1200
            'lease_purchase_rent_credit'         => self::ALL_SELLER,                          // :1214
            'lease_purchase_rent_credit_amount'  => self::ALL_SELLER,                          // :1228
            'lease_purchase_conditions'          => self::ALL_SELLER,                          // :1263
            'lease_purchase_terms'               => self::ALL_SELLER,                          // :1277
            'lease_purchase_maintenance'         => self::ALL_SELLER,                          // :1291
            'lease_purchase_extension_terms'     => self::ALL_SELLER,                          // :1310
            'nft_description'                    => self::ALL_SELLER,                          // :1333
            'nft_percentage'                     => self::ALL_SELLER,                          // :1348
            'cash_percentage_nft'                => self::ALL_SELLER,                          // :1364
            'nft_valuation_method'               => self::ALL_SELLER,                          // :1380
            'nft_transfer_method'                => self::ALL_SELLER,                          // :1395
            'nft_gas_fees'                       => self::ALL_SELLER,                          // :1410
            'escrow_agent_preference'            => self::ALL_SELLER,                          // :1830
            'inspection_contingency_preference'  => self::ALL_SELLER,                          // :1866
            'preferred_inspection_period'        => self::ALL_SELLER,                          // :1884
            'appraisal_contingency_preference'   => self::ALL_SELLER,                          // :1909
            'appraisal_contingency_period'       => self::ALL_SELLER,                          // :1927
            'financing_contingency_preference'   => self::ALL_SELLER,                          // :1948
            'financing_contingency_period'       => self::ALL_SELLER,                          // :1966
            'sale_of_buyer_property_contingency' => self::ALL_SELLER,                          // :1989
            'sale_of_buyer_property_period'      => self::ALL_SELLER,                          // :2007
            'seller_credit_offered'              => self::ALL_SELLER,                          // :2023
            'seller_credit_amount'               => self::ALL_SELLER,                          // :2038
            'possession_preference'              => self::ALL_SELLER,                          // :2054
            'possession_details'                 => self::ALL_SELLER,                          // :2072
            'included_personal_property'         => self::ALL_SELLER,                          // :2088
            'excluded_items'                     => self::ALL_SELLER,                          // :2103
            'home_warranty_details'              => self::ALL_SELLER,                          // :2133
            'hoa_condo_association_terms'        => self::ALL_SELLER,                          // :2149
            'additional_seller_sale_terms'       => self::ALL_SELLER,                          // :2164
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
            'waterfront_feet'          => self::ALL_LANDLORD,                     // :822
            'total_acreage'            => self::ALL_LANDLORD,                     // :549
            'pool'                     => [PT::RESIDENTIAL],                      // :865
            'garage'                   => [PT::RESIDENTIAL],                      // :661
            'carport'                  => [PT::RESIDENTIAL],                      // :626
            'floor_covering'           => [PT::RESIDENTIAL],                      // :1264
            'lease_available_date'     => self::ALL_LANDLORD,                     // LP lease-terms :1200
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
            'pet_rent'                 => self::LEGACY_NO_INPUT,
            'pet_fee'                  => self::LEGACY_NO_INPUT,
            'pet_monthly_fee'          => self::LEGACY_NO_INPUT,
            'pet_deposit_amount'       => self::LEGACY_NO_INPUT,

            // ── Universal coverage audit (2026-09-24) ──────────────────────────────
            // LP lease-terms: leasing-space children are value-gated by leasing_spaces, which
            // ConditionalTerms enforces at answer time; the type gate is the @if around them.
            'lease_amount_frequency'            => self::ALL_LANDLORD,              // lease-terms :1129
            'leasing_space'                     => self::ALL_LANDLORD,              // lease-terms :137
            'occupant_status'                   => self::ALL_LANDLORD,              // lease-terms :101
            'occupied_until'                    => self::ALL_LANDLORD,              // lease-terms :120 (occupant_status Tenant/Owner :111)
            'restrictions'                      => self::ALL_LANDLORD,              // lease-terms :163 / :267
            'maintenance_by'                    => self::ALL_LANDLORD,              // lease-terms :182 / :299 / :455 / :662
            'maintenance_response_time'         => self::ALL_LANDLORD,              // lease-terms :201 / :318 / :474 / :681
            'common_areas_access'               => self::ALL_LANDLORD,              // lease-terms :283 / :646 (Single Room)
            'common_areas_cleaning'             => self::ALL_LANDLORD,              // lease-terms :351 / :714 (Single Room)
            'bathroom_facilities'               => self::ALL_LANDLORD,              // lease-terms :398 / :762 (Single Room)
            'room_size'                         => self::ALL_LANDLORD,              // lease-terms :415 / :779 (Single Room)
            'included_storage_space_res_both'   => [PT::RESIDENTIAL],               // lease-terms :215 inside :148
            'storage_space_res_both'            => [PT::RESIDENTIAL],               // lease-terms :233
            'included_storage_space_res_single' => [PT::RESIDENTIAL],               // lease-terms :366
            'storage_space_res_single'          => [PT::RESIDENTIAL],               // under :366
            'included_storage_space_com_entire' => [PT::COMMERCIAL],                // lease-terms :487 inside :423
            'storage_space_com_entire'          => [PT::COMMERCIAL],                // under :487
            'included_storage_space_com_single' => [PT::COMMERCIAL],                // lease-terms :729
            'storage_space_com_single'          => [PT::COMMERCIAL],                // under :729
            'shared_amenities'                  => [PT::COMMERCIAL],                // lease-terms :519 inside :423
            'building_hours'                    => [PT::COMMERCIAL],                // lease-terms :534
            'access_24_7'                       => [PT::COMMERCIAL],                // lease-terms :549
            'zoning_allows'                     => [PT::COMMERCIAL],                // lease-terms :567
            'space_features'                    => [PT::COMMERCIAL],                // lease-terms :583
            'neighboring_tenants'               => [PT::COMMERCIAL],                // lease-terms :599
            'tenant_pays'                       => [PT::COMMERCIAL],                // lease-terms :936 inside :928
            'owner_pays'                        => [PT::COMMERCIAL],                // lease-terms :982 inside :928
            'rent_includes'                     => [PT::RESIDENTIAL],               // lease-terms :1608 inside :1600
            'll_maintenance_responsibility'     => self::ALL_LANDLORD,              // lease-terms :1344
            'renewal_option_details'            => self::ALL_LANDLORD,              // lease-terms :1377 (renewal Yes/Negotiable)
            'commercial_lease_type'             => [PT::COMMERCIAL],                // lease-terms :1412 inside :1403
            'cam_nnn_additional_rent_charges'   => [PT::COMMERCIAL],                // lease-terms :1440
            'rent_escalation_terms'             => [PT::COMMERCIAL],                // lease-terms :1454
            'tenant_improvement_buildout_terms' => [PT::COMMERCIAL],                // lease-terms :1468
            'permitted_use_restrictions'        => [PT::COMMERCIAL],                // lease-terms :1482
            'signage_rights'                    => [PT::COMMERCIAL],                // lease-terms :1496
            // LP property-preferences
            'minimum_leaseable'                 => [PT::COMMERCIAL],                // :492 inside :482
            'garage_parking_features'           => [PT::COMMERCIAL],                // :691 inside :682
            'furnishings'                       => [PT::RESIDENTIAL],               // :602 inside :591
            'pets_allowed_count'                => [PT::RESIDENTIAL],               // :1788 inside :1757 (pets Yes)
            'pet_types_allowed'                 => [PT::RESIDENTIAL],               // :1803
            'pet_weight_limit'                  => [PT::RESIDENTIAL],               // :1855
            // LP applicant-requirements (no property-type conditional): utility estimates.
            'est_water_sewer_trash'             => self::ALL_LANDLORD,              // :446
            'est_electric'                      => self::ALL_LANDLORD,              // :457
            'est_internet'                      => self::ALL_LANDLORD,              // :468
            'est_cable'                         => self::ALL_LANDLORD,              // :479
            // LP tax-legal-hoa-disclosures (no property-type conditional).
            'additional_parcels'                => self::ALL_LANDLORD,              // :75
            'total_parcel_count'                => self::ALL_LANDLORD,              // :94
            'flood_insurance_required'          => self::ALL_LANDLORD,              // :189
            'flood_zone_panel'                  => self::ALL_LANDLORD,              // :208
            'flood_zone_date'                   => self::ALL_LANDLORD,              // :223
            'has_cdd'                           => self::ALL_LANDLORD,              // :248
            'annual_cdd_fee'                    => self::ALL_LANDLORD,              // :268
            'has_special_assessments'           => self::ALL_LANDLORD,              // :286
            'special_assessment_amount'         => self::ALL_LANDLORD,              // :306
            'special_assessment_description'    => self::ALL_LANDLORD,              // :322
            'association_type'                  => self::ALL_LANDLORD,              // :369 (has_hoa Yes)
            'association_approval_required'     => self::ALL_LANDLORD,              // :475
            'association_approval_process'      => self::ALL_LANDLORD,              // :494
            'association_application_fee'       => self::ALL_LANDLORD,              // :509
            'max_leases_per_year'               => self::ALL_LANDLORD,              // :654 (leasing_restrictions Yes)
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
     * Types MLS quick import can write this canonical field for. Never gates a MANUAL listing;
     * for an MLS-imported one it is the import admission the question service applies (see the
     * class docblock).
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
