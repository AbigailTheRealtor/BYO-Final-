<?php

namespace Tests\Feature\Stellar\Matching\Baseline;

use App\Models\BridgeProperty;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-A0 — GOLDEN BASELINE of today's live buyer/tenant matching, per property category.
 *
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │ CHARACTERIZATION OF CURRENT BEHAVIOR — NOT DESIRED BUSINESS LOGIC            │
 * │                                                                              │
 * │ The snapshots under tests/fixtures/matching/pre_convergence/ record what the │
 * │ engine produces TODAY, defects included. They exist so the P1 convergence    │
 * │ refactors (docs/plans/p1-canonical-matching-convergence.md) can prove "zero  │
 * │ semantic change". A value in a snapshot is not a statement that the value is │
 * │ right. Known defects are listed below and pinned by name in                  │
 * │ PreConvergenceKnownDefectCharacterizationTest.                               │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * WHAT IS PINNED, per (category fixture or variant) × (criteria case):
 *   selected  — did the live BuyerMatchService return this listing? (SQL hard
 *               filters + the inline IDX gate, over the category's own seeded rows)
 *   score     — total_score + the eight category_scores
 *   explain   — why_this_matches / tradeoffs / caution_flags / missing_data
 *   detailed  — why_not / confidence / recommendations (Match Check report path)
 *   places    — the public Important Place rows
 *   card      — the results-page card (BuyerResultViewMapper::mapOne), row id removed
 *
 * And, as an invariant rather than a snapshot: when the live service selects the
 * listing, its result equals the direct scorer + builder projection. That is the
 * "results page and direct scoring agree" half of the Match Check parity question
 * (the other half is PreConvergenceMatchCheckParityBaselineTest).
 *
 * WHICH SCORING CONCEPTS APPLY TODAY, by category (so residential criteria are
 * not forced onto categories where the engine ignores them):
 *
 *   Category              price  size(living/lot/year)  subtype  amenities  financial  lifestyle      non-residential branch
 *   Residential (sale)    yes    yes / lot / yes        yes      yes        yes        yes            none (0)
 *   Residential Lease     rent*  yes / lot / yes        yes      yes        yes        yes + lease    none (0)
 *   Income                yes    yes / lot / yes        yes      yes        yes        yes            BuildingAreaTotal vs sqft pref
 *   Commercial Sale       yes    yes / lot / yes        yes      (n/a data) yes        partial        BuildingAreaTotal + lot pref
 *   Commercial Lease      rent*  yes / lot / yes        yes      (n/a data) yes        + lease term   none — falls to default (0)
 *   Business Opportunity  yes    yes / lot / yes        yes      (n/a data) yes        partial        always 0 (stub)
 *   Vacant Land           yes    — / lot / —            yes      (n/a data) yes        partial        lot pref
 *   * rent is compared as a MONTHLY equivalent using LeaseAmountFrequency; an
 *     unknown period earns 0 price points when the seeker named a budget.
 *
 * Bedrooms/bathrooms are SQL hard filters only (no score); they are pinned via
 * `selected` here and in PreConvergenceHardFilterBaselineTest.
 *
 * KNOWN DEFECTS THESE SNAPSHOTS CONTAIN (plan §27; do not "fix" by regenerating):
 *   D-1  wants_pool / wants_garage = false is scored as if the seeker wanted one
 *        (see cases amenities_negative_*).
 *   D-7  a rent card's price_display carries no lease-period suffix.
 *   plus Phase-1 caps (location max 24/30, price max 20/25) and the flat 2-pt
 *   subtype score when no subtype preference exists — existing product decisions.
 *
 * REGENERATING: only by an explicit, deliberate run —
 *   P1A0_REGENERATE_BASELINE=1 php artisan test --filter=PreConvergenceScoringBaselineTest
 * which rewrites the snapshot files and marks every case INCOMPLETE so a
 * regenerating run can never report green. A regenerated diff must be reviewed
 * and justified in the PR that causes it.
 */
class PreConvergenceScoringBaselineTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;

    private const SNAPSHOT_DIR = 'tests/fixtures/matching/pre_convergence';

    /** @return array<string,array{string}> */
    public static function categories(): array
    {
        return [
            'Residential (sale)'   => ['residential'],
            'Residential Lease'    => ['residential_lease'],
            'Income'               => ['income'],
            'Commercial Sale'      => ['commercial_sale'],
            'Commercial Lease'     => ['commercial_lease'],
            'Business Opportunity' => ['business_opportunity'],
            'Vacant Land'          => ['vacant_land'],
        ];
    }

    /** @dataProvider categories */
    public function test_category_output_matches_the_pre_convergence_snapshot(string $slug): void
    {
        $actual = [];

        foreach ($this->listingsFor($slug) as $label => $listing) {
            foreach ($this->casesFor($slug, $label) as $case => $criteria) {
                $payload    = $this->baselinePayload($criteria);
                $projection = $this->projectScoredListing($listing, $payload);

                // Live results path over this category's seeded rows.
                $results  = $this->baselineMatchService()->match($payload, 200, $this->roleFor($slug));
                $selected = $results->first(fn ($r) => $r->listingKey === $listing->listing_key);

                if ($selected !== null) {
                    // Invariant (not a snapshot): the results page and direct scoring agree.
                    $this->assertSame(
                        [
                            'listing_key'      => $projection['score']['listing_key'],
                            'total_score'      => $projection['score']['total_score'],
                            'category_scores'  => $projection['score']['category_scores'],
                            'why_this_matches' => $projection['explain']['why_this_matches'],
                            'tradeoffs'        => $projection['explain']['tradeoffs'],
                            'caution_flags'    => $projection['explain']['caution_flags'],
                            'missing_data'     => $projection['explain']['missing_data'],
                        ],
                        self::projectServiceResult($selected),
                        "{$label}/{$case}: the live results path must equal direct scoring"
                    );
                }

                // The results card is presentation over the same result: pinned once per
                // listing (the baseline case), minus the blocks `explain`/`places` already hold.
                if ($case === 'baseline_city_only') {
                    $projection['card'] = array_diff_key($projection['card'], array_flip([
                        'why_this_matches', 'tradeoffs', 'caution_flags', 'missing_data', 'important_places',
                    ]));
                } else {
                    unset($projection['card']);
                }

                $actual[$label][$case] = ['selected' => $selected !== null] + $projection;
            }
        }

        $path = base_path(self::SNAPSHOT_DIR . "/{$slug}.json");

        if (getenv('P1A0_REGENERATE_BASELINE') === '1') {
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            file_put_contents($path, self::encodeSnapshot($actual));
            $this->markTestIncomplete("Regenerated {$path}; review the diff. A regenerating run never passes.");
        }

        $this->assertFileExists($path, 'Snapshot missing — generate it deliberately with P1A0_REGENERATE_BASELINE=1.');
        $expected = json_decode((string) file_get_contents($path), true);

        $this->assertSame(
            array_keys($expected),
            array_keys($actual),
            "{$slug}: the set of seeded listings/variants changed"
        );

        foreach ($expected as $label => $cases) {
            $this->assertSame(array_keys($cases), array_keys($actual[$label]), "{$label}: the case list changed");

            foreach ($cases as $case => $want) {
                $this->assertSame($want, $actual[$label][$case], "{$label}/{$case} drifted from the pre-convergence baseline");
            }
        }
    }

    /**
     * One compact JSON line per case, grouped by listing, so a behaviour change
     * shows up as a one-line diff naming the listing and the case.
     */
    private static function encodeSnapshot(array $actual): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;
        $out   = [];

        foreach ($actual as $label => $cases) {
            $lines = [];
            foreach ($cases as $case => $entry) {
                $lines[] = '    ' . json_encode((string) $case, $flags) . ': ' . json_encode($entry, $flags);
            }
            $out[] = '  ' . json_encode((string) $label, $flags) . ": {\n" . implode(",\n", $lines) . "\n  }";
        }

        return "{\n" . implode(",\n", $out) . "\n}\n";
    }

    // =========================================================================
    // Listings: the category fixture plus variants that exercise raw-field paths
    // (lease period, lease term, association fee period) the base fixture cannot.
    // =========================================================================

    /** @return array<string,BridgeProperty> label => listing */
    private function listingsFor(string $slug): array
    {
        $out = [$slug => $this->storeBaselineFixture($slug)];

        if ($slug === 'residential') {
            $out['residential@hoa_monthly']   = $this->storeBaselineFixture($slug, ['AssociationFee' => 350, 'AssociationFeeFrequency' => 'Monthly'], 'hoa_monthly');
            $out['residential@hoa_quarterly'] = $this->storeBaselineFixture($slug, ['AssociationFee' => 900, 'AssociationFeeFrequency' => 'Quarterly'], 'hoa_quarterly');
            $out['residential@hoa_unknown']   = $this->storeBaselineFixture($slug, ['AssociationFee' => 350, 'AssociationFeeFrequency' => null], 'hoa_unknown');
            $out['residential@amenities']     = $this->storeBaselineFixture($slug, [
                'PoolPrivateYN' => true, 'GarageYN' => true, 'WaterfrontYN' => true, 'ViewYN' => true,
                'NewConstructionYN' => true, 'GreenEnergyEfficient' => ['Appliances', 'Windows'],
            ], 'amenities');
        }

        if ($slug === 'residential_lease') {
            $out['residential_lease@weekly']      = $this->storeBaselineFixture($slug, ['LeaseAmountFrequency' => 'Weekly', 'ListPrice' => 800], 'weekly');
            $out['residential_lease@annually']    = $this->storeBaselineFixture($slug, ['LeaseAmountFrequency' => 'Annually', 'ListPrice' => 42000], 'annually');
            $out['residential_lease@seasonal']    = $this->storeBaselineFixture($slug, ['LeaseAmountFrequency' => 'Seasonal'], 'seasonal');
            $out['residential_lease@no_period']   = $this->storeBaselineFixture($slug, ['LeaseAmountFrequency' => null], 'no_period');
            $out['residential_lease@term_12mo']   = $this->storeBaselineFixture($slug, ['LeaseTerm' => '12 Months'], 'term_12mo');
            $out['residential_lease@term_mtm']    = $this->storeBaselineFixture($slug, ['LeaseTerm' => 'Month To Month'], 'term_mtm');
            $out['residential_lease@pets_ok']     = $this->storeBaselineFixture($slug, ['PetsAllowed' => ['Cats OK', 'Dogs OK']], 'pets_ok');
        }

        if ($slug === 'commercial_lease') {
            $out['commercial_lease@annually'] = $this->storeBaselineFixture($slug, ['LeaseAmountFrequency' => 'Annually', 'ListPrice' => 60000], 'annually');
        }

        return $out;
    }

    private function roleFor(string $slug): string
    {
        return in_array($slug, ['residential_lease', 'commercial_lease'], true) ? 'tenant' : 'buyer';
    }

    // =========================================================================
    // Criteria cases. Values are derived from the stored listing so each case
    // says what it tests (at the listing, 10% over, …) rather than a magic number.
    // =========================================================================

    /**
     * A variant differs from its base fixture in a handful of raw fields, so it is
     * run only against the cases those fields can move (plus the baseline, which
     * also pins its card). Re-running geography etc. against it would add bulk and
     * no information.
     */
    private const VARIANT_CASE_PREFIXES = ['baseline_', 'price_', 'lease_', 'financial_', 'amenities_', 'lifestyle_'];

    /** @return array<string,array<string,mixed>> case => payload overrides */
    private function casesFor(string $slug, string $label): array
    {
        $cases = $this->allCasesFor($slug, $label);

        if (!str_contains($label, '@')) {
            return $cases;
        }

        return array_filter($cases, static function (string $case): bool {
            foreach (self::VARIANT_CASE_PREFIXES as $prefix) {
                if (str_starts_with($case, $prefix)) {
                    return true;
                }
            }

            return false;
        }, ARRAY_FILTER_USE_KEY);
    }

    /** @return array<string,array<string,mixed>> case => payload overrides */
    private function allCasesFor(string $slug, string $label): array
    {
        $record = $this->fixtureRecord($slug);
        $type   = self::$CATEGORIES[$slug];
        $lat    = (float) $record['Latitude'];
        $lng    = (float) $record['Longitude'];
        $mile   = 1 / 69.0; // degrees of latitude per mile
        $base   = ['property_types' => [$type], 'preferred_cities' => [$record['City']]];

        $price = (float) ($record['ListPrice'] ?? 0);
        if (str_contains($label, '@weekly'))   { $price = 800; }
        if (str_contains($label, '@annually')) { $price = $slug === 'commercial_lease' ? 60000 : 42000; }

        $cases = [
            // ── geography & location score ─────────────────────────────────────
            'baseline_city_only'    => $base,
            'location_zip_only'     => ['property_types' => [$type], 'preferred_zip_codes' => [$record['PostalCode']]],
            'location_county_only'  => ['property_types' => [$type], 'preferred_counties' => [$record['CountyOrParish']]],
            'location_state_only'   => ['property_types' => [$type], 'preferred_state' => 'FL'],
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

            // ── price / rent (the payload budget is monthly on a lease search) ──
            'price_ideal_at_list'   => $base + ['ideal_price' => (int) round($price)],
            'price_ideal_20pct_off' => $base + ['ideal_price' => (int) round($price * 1.2)],
            'price_max_comfortable' => $base + ['max_price' => (int) ceil($price / 0.85)],
            'price_max_tight'       => $base + ['max_price' => (int) ceil($price / 0.95)],
            'price_max_below_list'  => $base + ['max_price' => (int) floor($price * 0.8)],

            // ── property type / subtype ───────────────────────────────────────
            'subtype_match'    => $base + ['property_sub_types' => [$record['PropertySubType']]],
            'subtype_mismatch' => $base + ['property_sub_types' => ['Single Family Residence']],

            // ── 55+ eligibility flips the senior SQL gate off ─────────────────
            'senior_eligible' => $base + ['is_55_plus_eligible' => true],
        ];

        $area = $record['LivingArea'] ?? null;
        if ($area !== null && $area > 0) {
            $cases += [
                'size_sqft_in_range' => $base + ['min_sqft' => (int) ($area * 0.8), 'max_sqft' => (int) ($area * 1.2)],
                'size_sqft_near_min' => $base + ['min_sqft' => (int) ($area * 1.1)],
                'size_sqft_far_min'  => $base + ['min_sqft' => (int) ($area * 2)],
                'size_sqft_over_max' => $base + ['max_sqft' => (int) ($area * 0.5)],
            ];
        }

        // Lot: every category is exercised, including Residential/Income whose
        // fixture carries LotSizeSquareFeet = 0 (stored 0, not null).
        $lot = (int) ($record['LotSizeSquareFeet'] ?? 0);
        $cases += [
            'lot_in_range' => $base + ['min_lot_sqft' => (int) max(0, $lot * 0.5), 'max_lot_sqft' => (int) max(1, $lot * 2)],
            'lot_near_min' => $base + ['min_lot_sqft' => (int) max(1, $lot * 1.1)],
            'lot_far_min'  => $base + ['min_lot_sqft' => (int) max(1, $lot * 3)],
        ];

        if (isset($record['YearBuilt'])) {
            $y = (int) $record['YearBuilt'];
            $cases += [
                'year_built_in_range' => $base + ['year_built_min' => $y - 5, 'year_built_max' => $y + 5],
                'year_built_within_10' => $base + ['year_built_min' => $y + 8],
                'year_built_far'      => $base + ['year_built_min' => $y + 30],
            ];
        }

        // Residential-shaped seekers: bedroom/bathroom hard filters and amenities.
        if (in_array($slug, ['residential', 'residential_lease', 'income'], true)) {
            $beds  = (int) ($record['BedroomsTotal'] ?? 0);
            $baths = (int) ($record['BathroomsTotalInteger'] ?? 0);
            $cases += [
                'beds_baths_met'          => $base + ['min_bedrooms' => $beds, 'min_bathrooms' => $baths],
                'beds_above_listing'      => $base + ['min_bedrooms' => $beds + 1],
                'baths_above_listing'     => $base + ['min_bathrooms' => $baths + 1],
                'amenities_all_wanted'    => $base + ['wants_pool' => true, 'wants_garage' => true, 'wants_waterfront' => true, 'wants_any_view' => true],
                'amenities_pool_only'     => $base + ['wants_pool' => true],
                'amenities_waterfront_only' => $base + ['wants_waterfront' => true],
                // D-1 — CHARACTERIZATION ONLY: false is scored like true today.
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

        // Rental seekers: lease term buckets (read from raw LeaseTerm today).
        if (in_array($slug, ['residential_lease', 'commercial_lease'], true)) {
            $cases += [
                'lease_terms_1_year'      => $base + ['preferred_lease_terms' => ['1 Year']],
                'lease_terms_mtm'         => $base + ['preferred_lease_terms' => ['Month-to-Month']],
                'lease_terms_3_5_years'   => $base + ['preferred_lease_terms' => ['3-5 Years']],
                'lease_terms_6_months'    => $base + ['preferred_lease_terms' => ['6 Months']],
                'lease_rent_with_terms'   => $base + ['max_price' => (int) ceil($price * 1.05), 'preferred_lease_terms' => ['1 Year']],
            ];
        }

        // Non-residential size preferences are read from BuildingAreaTotal first.
        if (in_array($slug, ['income', 'commercial_sale'], true)) {
            $bat = (int) ($record['BuildingAreaTotal'] ?? 0);
            $cases += [
                'building_area_in_range' => $base + ['min_sqft' => (int) ($bat * 0.9), 'max_sqft' => (int) ($bat * 1.1)],
                'building_area_near'     => $base + ['min_sqft' => (int) ($bat * 1.15)],
            ];
        }

        return $cases;
    }
}
