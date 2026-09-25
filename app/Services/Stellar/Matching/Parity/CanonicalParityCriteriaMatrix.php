<?php

namespace App\Services\Stellar\Matching\Parity;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\ListingMatchFacts;
use App\Support\Matching\MonthlyEquivalent;

/**
 * P1-B2 — the deterministic, SYNTHETIC criteria the offline parity runner scores each
 * listing against. No persisted Buyer/Tenant criteria and no user data is read: every
 * payload is built from the listing's own values.
 *
 *  1. The P1-A0 case families (PreConvergenceScoringBaselineTest::allCasesFor()),
 *     anchored on each real listing the way A0 anchors on its fixture — but on facts A,
 *     the live path's values, so thresholds sit exactly where the legacy path puts the
 *     listing. A drift test pins that on the seven committed fixtures these overrides
 *     equal A0's own.
 *  2. Synthetic edge cases A0 does not have for every type.
 *  3. Cohort criteria, used only for the ranking comparison.
 *
 * The post-attachment case (seeker tag picks) is built by the runner, the one parity
 * file allowed to name that subsystem.
 *
 * Returns payload OVERRIDES (arrays), not payload objects; the runner builds each
 * BuyerCriteriaPayload through its ordinary constructor, so no loader or live criteria
 * path is involved or changed.
 *
 * Not final: the runner takes a matrix through its constructor, and the status tests hand
 * it a one-case matrix to construct a precise outcome (a one-sided exception).
 */
class CanonicalParityCriteriaMatrix
{
    private const RESIDENTIAL_SHAPED = ['Residential', 'Residential Lease', 'Income'];
    private const RENTAL             = ['Residential Lease', 'Commercial Lease'];
    private const BUILDING_AREA      = ['Income', 'Commercial Sale'];

    /**
     * @param string $stratum one of CanonicalMatchingParityRunner::STRATA or `other_type`
     * @return array<string, array<string,mixed>> case name => payload overrides
     */
    public function casesFor(ListingMatchFacts $a, BridgeProperty $row, string $stratum): array
    {
        $type = $a->propertyType;

        if (!is_string($type) || trim($type) === '') {
            return [];
        }

        return $this->a0Families($a, $row, $stratum, $type) + $this->edgeCases($a, $stratum, $type);
    }

    /**
     * The A0 families for one listing (see PreConvergenceScoringBaselineTest::allCasesFor()).
     *
     * @return array<string, array<string,mixed>>
     */
    public function a0Families(ListingMatchFacts $a, BridgeProperty $row, string $stratum, string $type): array
    {
        $lat  = (float) $a->latitude;
        $lng  = (float) $a->longitude;
        $mile = 1 / 69.0; // degrees of latitude per mile
        $base = ['property_types' => [$type], 'preferred_cities' => [$a->city]];

        $price = (float) ($a->listPrice ?? 0);

        $cases = [
            // ── geography & location score ─────────────────────────────────────
            'baseline_city_only'    => $base,
            'location_zip_only'     => ['property_types' => [$type], 'preferred_zip_codes' => [$a->postalCode]],
            'location_county_only'  => ['property_types' => [$type], 'preferred_counties' => [$a->countyOrParish]],
            'location_state_only'   => ['property_types' => [$type], 'preferred_state' => $a->stateOrProvince],
            'location_radius_at_listing' => ['property_types' => [$type], 'radius_searches' => [['lat' => $lat, 'lng' => $lng, 'radius_miles' => 5]]],
            'location_radius_half_way'   => ['property_types' => [$type], 'radius_searches' => [['lat' => $lat + 2.5 * $mile, 'lng' => $lng, 'radius_miles' => 5]]],
            'location_radius_legacy_center_shape' => ['property_types' => [$type], 'radius_searches' => [['center' => ['lat' => $lat, 'lng' => $lng], 'radius_miles' => 3]]],
            'location_radius_elsewhere'  => ['property_types' => [$type], 'radius_searches' => [['lat' => $lat + 20 * $mile, 'lng' => $lng, 'radius_miles' => 5]]],
            'location_polygon_containing' => ['property_types' => [$type], 'polygons' => [['path' => [
                ['lat' => $lat - 0.01, 'lng' => $lng - 0.01], ['lat' => $lat - 0.01, 'lng' => $lng + 0.01],
                ['lat' => $lat + 0.01, 'lng' => $lng + 0.01], ['lat' => $lat + 0.01, 'lng' => $lng - 0.01],
            ]]]],
            'location_city_and_radius' => $base + ['radius_searches' => [['lat' => $lat + 1 * $mile, 'lng' => $lng, 'radius_miles' => 4]]],
            'important_place_within' => $base + ['important_places' => [
                ['type' => 'Work', 'address' => 'Office', 'lat' => $lat + 1 * $mile, 'lng' => $lng, 'distance_pref' => 'miles', 'distance_value' => 3],
            ]],
            'important_place_beyond' => $base + ['important_places' => [
                ['type' => 'School', 'address' => 'School', 'lat' => $lat + 10 * $mile, 'lng' => $lng, 'distance_pref' => 'miles', 'distance_value' => 3],
            ]],
            'important_places_mixed' => $base + ['important_places' => [
                ['type' => 'Work', 'address' => 'Office', 'lat' => $lat + 1 * $mile, 'lng' => $lng, 'distance_pref' => 'miles', 'distance_value' => 3],
                ['type' => 'Other', 'type_other' => 'Gym', 'address' => 'Gym', 'lat' => $lat + 10 * $mile, 'lng' => $lng, 'distance_pref' => 'miles', 'distance_value' => 3],
            ]],
        ];

        // Price families need a price to anchor on, exactly as the size families below need
        // an area: a missing, zero, negative or sub-dollar price would synthesise an
        // `ideal_price` of 0, a budget no buyer states. (The payload budget is monthly on a
        // lease search.)
        if (self::usablePrice($price)) {
            $cases += [
                'price_ideal_at_list'   => $base + ['ideal_price' => (int) round($price)],
                'price_ideal_20pct_off' => $base + ['ideal_price' => (int) round($price * 1.2)],
                'price_max_comfortable' => $base + ['max_price' => (int) ceil($price / 0.85)],
                'price_max_tight'       => $base + ['max_price' => (int) ceil($price / 0.95)],
                'price_max_below_list'  => $base + ['max_price' => (int) floor($price * 0.8)],
            ];
        }

        $cases += [
            // ── property type / subtype ───────────────────────────────────────
            'subtype_match'    => $base + ['property_sub_types' => [$a->propertySubType]],
            'subtype_mismatch' => $base + ['property_sub_types' => ['Single Family Residence']],

            // ── 55+ eligibility ───────────────────────────────────────────────
            'senior_eligible' => $base + ['is_55_plus_eligible' => true],
        ];

        $area = $a->livingArea === null ? null : (float) $a->livingArea;
        if ($area !== null && $area > 0) {
            $cases += [
                'size_sqft_in_range' => $base + ['min_sqft' => (int) ($area * 0.8), 'max_sqft' => (int) ($area * 1.2)],
                'size_sqft_near_min' => $base + ['min_sqft' => (int) ($area * 1.1)],
                'size_sqft_far_min'  => $base + ['min_sqft' => (int) ($area * 2)],
                'size_sqft_over_max' => $base + ['max_sqft' => (int) ($area * 0.5)],
            ];
        }

        $lot = (int) ($a->lotSizeSqft ?? 0);
        $cases += [
            'lot_in_range' => $base + ['min_lot_sqft' => (int) max(0, $lot * 0.5), 'max_lot_sqft' => (int) max(1, $lot * 2)],
            'lot_near_min' => $base + ['min_lot_sqft' => (int) max(1, $lot * 1.1)],
            'lot_far_min'  => $base + ['min_lot_sqft' => (int) max(1, $lot * 3)],
        ];

        if ($a->yearBuilt !== null) {
            $y = (int) $a->yearBuilt;
            $cases += [
                'year_built_in_range' => $base + ['year_built_min' => $y - 5, 'year_built_max' => $y + 5],
                'year_built_within_10' => $base + ['year_built_min' => $y + 8],
                'year_built_far'      => $base + ['year_built_min' => $y + 30],
            ];
        }

        if (in_array($stratum, self::RESIDENTIAL_SHAPED, true)) {
            $beds  = (int) ($row->bedrooms_total ?? 0);
            $baths = (int) ($row->bathrooms_total_integer ?? 0);
            $cases += [
                'beds_baths_met'          => $base + ['min_bedrooms' => $beds, 'min_bathrooms' => $baths],
                'beds_above_listing'      => $base + ['min_bedrooms' => $beds + 1],
                'baths_above_listing'     => $base + ['min_bathrooms' => $baths + 1],
                'amenities_all_wanted'    => $base + ['wants_pool' => true, 'wants_garage' => true, 'wants_waterfront' => true, 'wants_any_view' => true],
                'amenities_pool_only'     => $base + ['wants_pool' => true],
                'amenities_waterfront_only' => $base + ['wants_waterfront' => true],
                // D-1 — characterization only: false is scored like true today.
                'amenities_negative_pool_garage' => $base + ['wants_pool' => false, 'wants_garage' => false],
                'financial_total_burden_500' => $base + ['max_monthly_total_burden' => 500],
                'financial_hoa_max_200'      => $base + ['max_monthly_hoa' => 200],
                'lifestyle_all'  => $base + [
                    'community_feature_keywords' => ['Pool', 'Clubhouse'],
                    'wants_energy_efficient'     => true,
                    'wants_new_construction'     => true,
                    'wants_pet_friendly'         => true,
                ],
                'lifestyle_pets_only' => $base + ['wants_pet_friendly' => true],
            ];
        }

        if (in_array($stratum, self::RENTAL, true)) {
            $cases += [
                'lease_terms_1_year'      => $base + ['preferred_lease_terms' => ['1 Year']],
                'lease_terms_mtm'         => $base + ['preferred_lease_terms' => ['Month-to-Month']],
                'lease_terms_3_5_years'   => $base + ['preferred_lease_terms' => ['3-5 Years']],
                'lease_terms_6_months'    => $base + ['preferred_lease_terms' => ['6 Months']],
            ];

            // A rent-derived budget needs a usable rent, exactly as the price families above
            // need a price: a missing, zero or negative rent would synthesise a budget of 0.
            if (self::usablePrice($price)) {
                $cases['lease_rent_with_terms'] = $base + ['max_price' => (int) ceil($price * 1.05), 'preferred_lease_terms' => ['1 Year']];
            }
        }

        if (in_array($stratum, self::BUILDING_AREA, true)) {
            $bat = (int) ($a->buildingAreaTotal ?? 0);
            $cases += [
                'building_area_in_range' => $base + ['min_sqft' => (int) ($bat * 0.9), 'max_sqft' => (int) ($bat * 1.1)],
                'building_area_near'     => $base + ['min_sqft' => (int) ($bat * 1.15)],
            ];
        }

        return $cases;
    }

    /**
     * Edge cases A0 does not give every type. The payload requires a non-empty
     * property_types, so "empty criteria" is the minimal constructible set: type only.
     *
     * @return array<string, array<string,mixed>>
     */
    public function edgeCases(ListingMatchFacts $a, string $stratum, string $type): array
    {
        $cases = [
            'edge_minimal_type_only' => ['property_types' => [$type]],
            'edge_amenities_true'    => ['property_types' => [$type], 'wants_pool' => true, 'wants_garage' => true, 'wants_waterfront' => true],
            'edge_amenities_false'   => ['property_types' => [$type], 'wants_pool' => false, 'wants_garage' => false, 'wants_waterfront' => false],
            'edge_type_other'        => ['property_types' => [$this->otherType($stratum)], 'preferred_cities' => [$a->city]],
        ];

        $price = (float) ($a->listPrice ?? 0);

        if (in_array($stratum, self::RENTAL, true) && self::usablePrice($price)) {
            // The lease budget is monthly: one case at the listing's monthly equivalent (its own
            // frequency), one at the raw price — the pair exposes any rent-period divergence.
            $factor = MonthlyEquivalent::leaseFactor(is_string($a->leaseFrequency) ? $a->leaseFrequency : null);

            $cases['edge_rent_budget_raw_price'] = ['property_types' => [$type], 'max_price' => (int) ceil($price * 1.05)];

            if ($factor !== null) {
                $cases['edge_rent_budget_monthly_equivalent'] = ['property_types' => [$type], 'max_price' => (int) ceil($price * $factor * 1.05)];
            }
        }

        return $cases;
    }

    /**
     * Cohort criteria for ranking one stratum's listings against each other.
     *
     * @param list<ListingMatchFacts> $facts facts A of every examined listing in the stratum
     * @return array<string, array<string,mixed>>
     */
    public function cohortsFor(string $type, array $facts): array
    {
        $cohorts = ['cohort_type_only' => ['property_types' => [$type]]];

        $state = self::mostCommon(array_map(static fn (ListingMatchFacts $f) => $f->stateOrProvince, $facts));
        if ($state !== null) {
            $cohorts['cohort_type_state'] = ['property_types' => [$type], 'preferred_state' => $state];
        }

        $prices = array_values(array_filter(
            array_map(static fn (ListingMatchFacts $f) => $f->listPrice === null ? null : (float) $f->listPrice, $facts),
            static fn (?float $p) => $p !== null && $p > 0
        ));
        if ($prices !== []) {
            sort($prices);
            $median = $prices[intdiv(count($prices), 2)];

            // The band anchors on the median exactly as a listing's families anchor on its price.
            if (self::usablePrice($median)) {
                $cohorts['cohort_type_price_band'] = [
                    'property_types' => [$type],
                    'min_price'      => (int) floor($median * 0.8),
                    'max_price'      => (int) ceil($median * 1.2),
                    'ideal_price'    => (int) round($median),
                ];
            }
        }

        return $cohorts;
    }

    /**
     * Whether a list price (or rent) can anchor a price- or rent-derived case. Missing, zero,
     * negative and sub-dollar values cannot: each would synthesise a budget of 0 once rounded
     * — a budget no buyer states, and the divide-by-zero input BuyerMatchResultBuilder is
     * known not to guard (a follow-up outside this diagnostic).
     */
    public static function usablePrice(?float $price): bool
    {
        return $price !== null && $price >= 1.0;
    }

    /** A deterministic different primary type, so the type mismatch path is exercised. */
    private function otherType(string $stratum): string
    {
        return in_array($stratum, self::RENTAL, true) ? 'Residential' : 'Residential Lease';
    }

    /** The most frequent non-empty string, ties broken by string order; null if none. */
    public static function mostCommon(array $values): ?string
    {
        $counts = [];
        foreach ($values as $v) {
            if (is_string($v) && trim($v) !== '') {
                $counts[$v] = ($counts[$v] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            return null;
        }

        uksort($counts, static fn (string $x, string $y) => [$counts[$y], $x] <=> [$counts[$x], $y]);

        return array_key_first($counts);
    }
}
