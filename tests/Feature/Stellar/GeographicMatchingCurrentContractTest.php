<?php

namespace Tests\Feature\Stellar;

use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Models\BridgeProperty;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The geographic eligibility and scoring contract Buyer and Tenant run today.
 *
 * ⚠ THIS FILE PINS CURRENT BEHAVIOUR. IT DOES NOT ENDORSE IT. ⚠
 * ------------------------------------------------------------
 * Everything asserted here is the ADDITIVE Phase-1 model that
 * {@see \App\Services\Stellar\Matching\BuyerMatchScorer} implements today, and
 * that commit 174e495cf describes as deliberate: a 30-point Location category
 * built from sub-dimensions that SUM, with sub-market (+3) and subdivision (+3)
 * still to come. These tests are a SAFETY BASELINE for a future, separately
 * reviewed geography-ranking redesign — the proposed
 * Polygon > Radius > ZIP > City > County > State winner-take-all model.
 *
 * They do NOT assert that additive scoring is the desired permanent end-state.
 * When a `GeographicMatch` resolver arrives, the intended use of this file is to
 * diff winner-take-all ranking against this baseline and see exactly which
 * listings move and by how much. A failure here after that change is therefore
 * EXPECTED and is the signal to update this file deliberately — not a bug to be
 * silenced by loosening an assertion.
 *
 * WHY THIS FILE EXISTS AT ALL
 * ---------------------------
 * The audit found the arithmetic almost entirely unpinned. `BuyerMatchingEngineTest`
 * covers radius exclusion (TC-16), polygon inclusion (TC-26) and one
 * polygon-vs-city comparison (TC-27), and there is no dedicated
 * `BuyerMatchQueryBuilder` or `BuyerMatchScorer` test file at all. Nothing
 * asserted the County value, that City and ZIP share one 6-point block rather
 * than scoring 12, that polygon and radius cannot stack, that repeated polygons
 * or radii cannot stack, or that the 24-point ceiling holds. A redesign could
 * have changed any of those silently.
 *
 * WHAT IS DELIBERATELY NOT HERE
 * -----------------------------
 * `BuyerMatchScorer::score()` is exercised directly for the arithmetic, because
 * the alternative — driving the full pipeline — makes every scoring assertion
 * also depend on the price, size, amenity and IDX paths, so an unrelated change
 * would fail this file for a reason its name does not describe. Eligibility IS
 * exercised through the real query builder, because that is where OR semantics
 * actually live.
 *
 * @see \Tests\Feature\Stellar\BuyerTenantStateMatchingTest for the State
 *      ELIGIBILITY contract; this file pins State's ranking contribution (zero).
 */
class GeographicMatchingCurrentContractTest extends TestCase
{
    use DatabaseTransactions;

    /** The maximum the Location category can reach in Phase 1. */
    private const LOCATION_MAX = 24;

    /** Full proximity: inside a polygon, or at the exact centre of a radius. */
    private const PROXIMITY_MAX = 18;

    /** City OR ZIP. One block, not two. */
    private const CITY_ZIP_PTS = 6;

    /** County, and only when neither City nor ZIP matched. */
    private const COUNTY_PTS = 3;

    /** A polygon around downtown Tampa. */
    private const POLYGON = [
        'label' => 'Downtown Tampa',
        'path'  => [
            ['lat' => 27.93, 'lng' => -82.48],
            ['lat' => 27.93, 'lng' => -82.44],
            ['lat' => 27.97, 'lng' => -82.44],
            ['lat' => 27.97, 'lng' => -82.48],
        ],
    ];

    /** A point inside self::POLYGON. */
    private const IN_POLY = ['lat' => 27.95, 'lng' => -82.46];

    private function skipIfTableMissing(): void
    {
        if (! Schema::hasTable('bridge_properties')) {
            $this->markTestSkipped('bridge_properties table is not present.');
        }
    }

    private function criteria(array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
        ], $overrides));
    }

    /**
     * An unsaved listing model — enough for the scorer, which reads attributes
     * and touches no database.
     */
    private function listing(array $attributes = []): BridgeProperty
    {
        return new BridgeProperty(array_merge([
            'listing_key'       => 'CONTRACT-' . uniqid(),
            'standard_status'   => 'Active',
            'property_type'     => 'Residential',
            'city'              => 'Nowhere',
            'state_or_province' => 'TX',   // see insertListing(): matches nothing by default
            'postal_code'       => '00000',
            'county_or_parish'  => 'Nowhereshire',
            'latitude'          => null,
            'longitude'         => null,
        ], $attributes));
    }

    /** The Location category score for one listing/criteria pair. */
    private function locationScore(BridgeProperty $listing, BuyerCriteriaPayload $criteria): int
    {
        return (new BuyerMatchScorer())->score($listing, $criteria)->categoryScores['location'];
    }

    private function insertListing(array $overrides = []): string
    {
        $key = $overrides['listing_key'] ?? ('CONTRACT-' . uniqid());

        DB::table('bridge_properties')->insert(array_merge([
            'listing_key'             => $key,
            'listing_id'              => 'LID-' . uniqid(),
            'standard_status'         => 'Active',
            'property_type'           => 'Residential',
            'list_price'              => 400000,
            'city'                    => 'Nowhere',
            // Deliberately NOT Florida. The neutral fixture has to match no
            // tier at all, and every criteria set in this file is Florida-based
            // — an 'FL' default would make the "matches nothing" control match
            // the State tier and quietly weaken the assertion.
            'state_or_province'       => 'TX',
            'postal_code'             => '00000',
            'county_or_parish'        => 'Nowhereshire',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1800,
            'senior_community_yn'     => false,
            'raw_json'                => json_encode(['IDXParticipationYN' => true]),
            'created_at'              => now(),
            'updated_at'              => now(),
        ], $overrides, ['listing_key' => $key]));

        return $key;
    }

    /** @return list<string> listing_keys the real geographic filter admits */
    private function eligibleKeys(BuyerCriteriaPayload $criteria): array
    {
        return (new BuyerMatchQueryBuilder())->build($criteria)->pluck('listing_key')->all();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1 · ELIGIBILITY — every tier is a way IN, and they OR
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     * @dataProvider eachTierAlone
     *
     * Each of the six tiers can admit a listing on its own. Parameterised
     * because the point is the SET being complete, not six near-identical
     * bodies — a tier silently losing its ability to admit anything is exactly
     * what this catches.
     */
    public function any_single_geographic_tier_can_make_a_listing_eligible(
        array $criteriaOverrides,
        array $listingOverrides
    ): void {
        $this->skipIfTableMissing();

        $match = $this->insertListing($listingOverrides);
        $miss  = $this->insertListing(); // matches nothing

        $keys = $this->eligibleKeys($this->criteria($criteriaOverrides));

        $this->assertContains($match, $keys, 'This tier must be able to admit a listing on its own');
        $this->assertNotContains($miss, $keys, 'A listing matching no tier must not be admitted');
    }

    public function eachTierAlone(): array
    {
        return [
            'polygon' => [
                ['polygons' => [self::POLYGON]],
                ['latitude' => self::IN_POLY['lat'], 'longitude' => self::IN_POLY['lng']],
            ],
            'radius' => [
                ['radius_searches' => [['lat' => 27.95, 'lng' => -82.46, 'radius_miles' => 5]]],
                ['latitude' => 27.95, 'longitude' => -82.46],
            ],
            'zip'    => [['preferred_zip_codes' => ['33602']], ['postal_code' => '33602']],
            'city'   => [['preferred_cities' => ['Tampa']],    ['city' => 'Tampa']],
            'county' => [['preferred_counties' => ['Hillsborough']], ['county_or_parish' => 'Hillsborough']],
            'state'  => [['preferred_state' => 'Florida'],     ['state_or_province' => 'FL']],
        ];
    }

    /**
     * @test
     * @dataProvider orPairs
     *
     * The load-bearing property of the current contract: a criterion that does
     * NOT match never blocks one that does. Geographic eligibility only ever
     * WIDENS. Each row is a pair where exactly one side matches.
     */
    public function a_failing_tier_never_blocks_a_matching_one(array $criteriaOverrides, array $listingOverrides): void
    {
        $this->skipIfTableMissing();

        $key  = $this->insertListing($listingOverrides);
        $keys = $this->eligibleKeys($this->criteria($criteriaOverrides));

        $this->assertContains(
            $key,
            $keys,
            'Geographic eligibility is OR — a failing criterion must not exclude a listing another criterion admits'
        );
    }

    public function orPairs(): array
    {
        $farFromPolygon = ['latitude' => 40.0, 'longitude' => -100.0];

        return [
            'polygon fails, city matches' => [
                ['polygons' => [self::POLYGON], 'preferred_cities' => ['Tampa']],
                array_merge($farFromPolygon, ['city' => 'Tampa']),
            ],
            'radius fails, ZIP matches' => [
                ['radius_searches' => [['lat' => 27.95, 'lng' => -82.46, 'radius_miles' => 5]],
                 'preferred_zip_codes' => ['33602']],
                array_merge($farFromPolygon, ['postal_code' => '33602']),
            ],
            'State fails, City matches' => [
                ['preferred_state' => 'Florida', 'preferred_cities' => ['Austin']],
                ['state_or_province' => 'TX', 'city' => 'Austin'],
            ],
            'State matches, City fails' => [
                ['preferred_state' => 'Florida', 'preferred_cities' => ['Miami']],
                ['state_or_province' => 'FL', 'city' => 'Orlando'],
            ],
            'county fails, state matches' => [
                ['preferred_counties' => ['Pinellas'], 'preferred_state' => 'Florida'],
                ['county_or_parish' => 'Orange', 'state_or_province' => 'FL'],
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2 · SCORING — the proximity block
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function a_polygon_match_alone_scores_full_proximity(): void
    {
        $score = $this->locationScore(
            $this->listing(['latitude' => self::IN_POLY['lat'], 'longitude' => self::IN_POLY['lng']]),
            $this->criteria(['polygons' => [self::POLYGON]])
        );

        $this->assertSame(self::PROXIMITY_MAX, $score, 'Inside a drawn polygon is full proximity, and nothing else');
    }

    /** @test */
    public function a_radius_match_at_the_exact_centre_scores_full_proximity(): void
    {
        $score = $this->locationScore(
            $this->listing(['latitude' => 27.95, 'longitude' => -82.46]),
            $this->criteria(['radius_searches' => [['lat' => 27.95, 'lng' => -82.46, 'radius_miles' => 5]]])
        );

        $this->assertSame(self::PROXIMITY_MAX, $score, 'At the centre, radius reaches the same ceiling as a polygon');
    }

    /**
     * @test
     *
     * Radius decays with distance; polygon does not. Asserted as an ordering
     * rather than an exact figure because the decay curve is not what this file
     * is pinning — the ceiling and the non-stacking are.
     */
    public function a_radius_match_away_from_the_centre_scores_less_than_the_ceiling(): void
    {
        $criteria = $this->criteria(['radius_searches' => [['lat' => 27.95, 'lng' => -82.46, 'radius_miles' => 10]]]);

        $near = $this->locationScore($this->listing(['latitude' => 27.95, 'longitude' => -82.46]), $criteria);
        $far  = $this->locationScore($this->listing(['latitude' => 28.02, 'longitude' => -82.46]), $criteria);

        $this->assertSame(self::PROXIMITY_MAX, $near);
        $this->assertGreaterThan(0, $far, 'Still inside the radius, so still scoring');
        $this->assertLessThan($near, $far, 'Proximity decays with distance from the centre');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3 · SCORING — proximity and the city/ZIP block ADD
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     * @dataProvider additivePairs
     *
     * The behaviour a precedence redesign would deliberately change. A listing
     * matching BOTH a proximity criterion and a named-boundary criterion scores
     * the sum, not the stronger of the two.
     */
    public function proximity_and_the_named_boundary_block_add_together(
        array $criteriaOverrides,
        array $listingOverrides,
        int $expected
    ): void {
        $listing = $this->listing(array_merge(
            ['latitude' => self::IN_POLY['lat'], 'longitude' => self::IN_POLY['lng']],
            $listingOverrides
        ));

        $this->assertSame(
            $expected,
            $this->locationScore($listing, $this->criteria($criteriaOverrides)),
            'Current Phase-1 behaviour is additive; a precedence redesign would change this number'
        );
    }

    public function additivePairs(): array
    {
        $poly   = ['polygons' => [self::POLYGON]];
        $radius = ['radius_searches' => [['lat' => 27.95, 'lng' => -82.46, 'radius_miles' => 5]]];

        return [
            'polygon + city'          => [$poly + ['preferred_cities' => ['Tampa']], ['city' => 'Tampa'], 24],
            'polygon + ZIP'           => [$poly + ['preferred_zip_codes' => ['33602']], ['postal_code' => '33602'], 24],
            'polygon + county'        => [$poly + ['preferred_counties' => ['Hillsborough']], ['county_or_parish' => 'Hillsborough'], 21],
            'radius centre + city'    => [$radius + ['preferred_cities' => ['Tampa']], ['city' => 'Tampa'], 24],
            'radius centre + ZIP'     => [$radius + ['preferred_zip_codes' => ['33602']], ['postal_code' => '33602'], 24],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4 · SCORING — what does NOT stack
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * City and ZIP are ONE block scored with `||`, not two blocks that sum.
     * Matching both is worth 6, not 12 — this is already a precedence-shaped
     * rule inside the current model, and it is worth pinning because it is the
     * one place a naive "add every signal" refactor would break first.
     */
    public function city_and_zip_share_one_block_and_do_not_double(): void
    {
        $listing  = $this->listing(['city' => 'Tampa', 'postal_code' => '33602']);
        $criteria = $this->criteria([
            'preferred_cities'    => ['Tampa'],
            'preferred_zip_codes' => ['33602'],
        ]);

        $this->assertSame(
            self::CITY_ZIP_PTS,
            $this->locationScore($listing, $criteria),
            'City and ZIP are ORed inside one 6-point block — matching both must not score 12'
        );
    }

    /**
     * @test
     * @dataProvider countySubordination
     *
     * County scores 3, but only when neither City nor ZIP matched — it sits
     * behind an `elseif`. So City+County is 6 (not 9) and ZIP+County is 6
     * (not 9). The only genuine subordination the current model already has.
     */
    public function county_only_scores_when_city_and_zip_did_not(
        array $criteriaOverrides,
        array $listingOverrides,
        int $expected,
        string $why
    ): void {
        $this->assertSame(
            $expected,
            $this->locationScore($this->listing($listingOverrides), $this->criteria($criteriaOverrides)),
            $why
        );
    }

    public function countySubordination(): array
    {
        return [
            'county alone' => [
                ['preferred_counties' => ['Hillsborough']],
                ['county_or_parish' => 'Hillsborough'],
                self::COUNTY_PTS,
                'County alone is worth 3',
            ],
            'city + county' => [
                ['preferred_cities' => ['Tampa'], 'preferred_counties' => ['Hillsborough']],
                ['city' => 'Tampa', 'county_or_parish' => 'Hillsborough'],
                self::CITY_ZIP_PTS,
                'City wins the block outright — county adds nothing, so 6 not 9',
            ],
            'zip + county' => [
                ['preferred_zip_codes' => ['33602'], 'preferred_counties' => ['Hillsborough']],
                ['postal_code' => '33602', 'county_or_parish' => 'Hillsborough'],
                self::CITY_ZIP_PTS,
                'ZIP wins the block outright — county adds nothing, so 6 not 9',
            ],
        ];
    }

    /**
     * @test
     *
     * Polygon and radius write the SAME `$proximityScore` variable, so matching
     * both cannot exceed the single-block ceiling.
     */
    public function polygon_and_radius_do_not_stack(): void
    {
        $listing = $this->listing(['latitude' => self::IN_POLY['lat'], 'longitude' => self::IN_POLY['lng']]);

        $score = $this->locationScore($listing, $this->criteria([
            'polygons'        => [self::POLYGON],
            'radius_searches' => [['lat' => self::IN_POLY['lat'], 'lng' => self::IN_POLY['lng'], 'radius_miles' => 5]],
        ]));

        $this->assertSame(
            self::PROXIMITY_MAX,
            $score,
            'Polygon and radius share one proximity slot — matching both must not reach 36'
        );
    }

    /** @test */
    public function multiple_matching_polygons_do_not_stack(): void
    {
        $second = self::POLYGON;
        $second['label'] = 'Overlapping';

        $listing = $this->listing(['latitude' => self::IN_POLY['lat'], 'longitude' => self::IN_POLY['lng']]);

        $this->assertSame(
            self::PROXIMITY_MAX,
            $this->locationScore($listing, $this->criteria(['polygons' => [self::POLYGON, $second]])),
            'Two overlapping polygons are still one proximity answer'
        );
    }

    /**
     * @test
     *
     * Multiple radii resolve with `max()`, so the BEST (nearest relative to its
     * own radius) wins and the others contribute nothing.
     */
    public function multiple_matching_radii_take_the_best_not_the_sum(): void
    {
        $listing = $this->listing(['latitude' => 27.95, 'longitude' => -82.46]);

        // One centred on the listing (full marks), one offset but still covering it.
        $score = $this->locationScore($listing, $this->criteria([
            'radius_searches' => [
                ['lat' => 27.99, 'lng' => -82.46, 'radius_miles' => 10],
                ['lat' => 27.95, 'lng' => -82.46, 'radius_miles' => 10],
            ],
        ]));

        $this->assertSame(
            self::PROXIMITY_MAX,
            $score,
            'The best radius wins via max() — two matching radii must not sum'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 5 · STATE — eligible, but worth nothing in ranking
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * State was made a real ELIGIBILITY criterion (PR #108) and deliberately
     * given no ranking weight. Both halves are asserted together here because
     * the pair is the contract: a state-only match reaches the results page and
     * ranks on everything except its geography.
     */
    public function state_makes_a_listing_eligible_but_contributes_zero_ranking_points(): void
    {
        $this->skipIfTableMissing();

        $key      = $this->insertListing(['state_or_province' => 'FL', 'city' => 'Orlando']);
        $criteria = $this->criteria(['preferred_state' => 'Florida']);

        $this->assertContains($key, $this->eligibleKeys($criteria), 'State must admit the listing');

        $this->assertSame(
            0,
            $this->locationScore($this->listing(['state_or_province' => 'FL', 'city' => 'Orlando']), $criteria),
            'State is eligibility-only in the current contract and must score nothing'
        );
    }

    /** @test */
    public function adding_a_matching_state_does_not_change_any_other_score(): void
    {
        $listing = $this->listing([
            'latitude' => self::IN_POLY['lat'], 'longitude' => self::IN_POLY['lng'],
            'city' => 'Tampa', 'state_or_province' => 'FL',
        ]);

        $without = $this->locationScore($listing, $this->criteria([
            'polygons' => [self::POLYGON], 'preferred_cities' => ['Tampa'],
        ]));
        $with = $this->locationScore($listing, $this->criteria([
            'polygons' => [self::POLYGON], 'preferred_cities' => ['Tampa'], 'preferred_state' => 'Florida',
        ]));

        $this->assertSame($without, $with, 'A matching state must not move a score in either direction');
        $this->assertSame(self::LOCATION_MAX, $with);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 6 · THE CEILING
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * 18 + 6 = 24 = `LOCATION_MAX_PHASE1_PTS`, so the clamp is exactly reachable
     * and never actually binding today. Every tier at once still lands on 24.
     */
    public function the_phase_one_geographic_ceiling_is_twenty_four(): void
    {
        $listing = $this->listing([
            'latitude'          => self::IN_POLY['lat'],
            'longitude'         => self::IN_POLY['lng'],
            'city'              => 'Tampa',
            'postal_code'       => '33602',
            'county_or_parish'  => 'Hillsborough',
            'state_or_province' => 'FL',
        ]);

        $score = $this->locationScore($listing, $this->criteria([
            'polygons'            => [self::POLYGON],
            'radius_searches'     => [['lat' => self::IN_POLY['lat'], 'lng' => self::IN_POLY['lng'], 'radius_miles' => 5]],
            'preferred_cities'    => ['Tampa'],
            'preferred_zip_codes' => ['33602'],
            'preferred_counties'  => ['Hillsborough'],
            'preferred_state'     => 'Florida',
        ]));

        $this->assertSame(self::LOCATION_MAX, $score, 'Every tier matching at once is still 24');
        $this->assertSame(
            self::LOCATION_MAX,
            BuyerMatchScorer::LOCATION_MAX_PHASE1_PTS,
            'The constant and this contract must not drift apart'
        );
    }

    /** @test */
    public function no_geographic_criteria_scores_nothing_and_filters_nothing(): void
    {
        $this->skipIfTableMissing();

        $anywhere = $this->insertListing(['city' => 'Austin', 'state_or_province' => 'TX']);

        $this->assertContains(
            $anywhere,
            $this->eligibleKeys($this->criteria()),
            'With no geography declared the filter is skipped entirely — this is what made State-only searches nationwide'
        );

        $this->assertSame(0, $this->locationScore($this->listing(), $this->criteria()));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 7 · BUYER / TENANT PARITY — behavioural, not by class name
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     * @dataProvider parityCases
     *
     * Buyer and Tenant reach the SAME query builder and the SAME scorer;
     * `BuyerMatchService::match()`'s `$role` argument only selects the Bridge
     * import filter builder. So parity is proven by running the identical
     * payload through the identical components and asserting the identical
     * answer, rather than by asserting that a class name appears somewhere.
     *
     * The tenant payloads differ from the buyer ones only in property type —
     * exactly the way the real tenant loaders differ — which is what makes this
     * a parity check rather than a copy of the buyer assertions.
     */
    public function tenant_payloads_get_the_same_geography_answers_as_buyer_payloads(
        array $geography,
        array $listingOverrides,
        int $expectedScore
    ): void {
        $this->skipIfTableMissing();

        $buyer  = $this->criteria($geography);
        $tenant = new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Residential Lease'],
            'is_55_plus_eligible' => false,
        ], $geography));

        $listing = $this->listing($listingOverrides);

        $this->assertSame(
            $expectedScore,
            $this->locationScore($listing, $buyer),
            'Buyer geographic arithmetic'
        );
        $this->assertSame(
            $this->locationScore($listing, $buyer),
            $this->locationScore($listing, $tenant),
            'Tenant must get byte-identical geographic scoring — one implementation serves both'
        );

        // …and the same eligibility answer, through the real query builder.
        $key = $this->insertListing(array_merge($listingOverrides, ['property_type' => 'Residential Lease']));
        $this->assertContains(
            $key,
            $this->eligibleKeys($tenant),
            'Tenant eligibility must follow the same OR as Buyer'
        );
    }

    public function parityCases(): array
    {
        return [
            'polygon + city' => [
                ['polygons' => [self::POLYGON], 'preferred_cities' => ['Tampa']],
                ['latitude' => self::IN_POLY['lat'], 'longitude' => self::IN_POLY['lng'], 'city' => 'Tampa'],
                24,
            ],
            'county only' => [
                ['preferred_counties' => ['Hillsborough']],
                ['county_or_parish' => 'Hillsborough'],
                3,
            ],
            'state only' => [
                ['preferred_state' => 'Florida'],
                ['state_or_province' => 'FL'],
                0,
            ],
        ];
    }

    /**
     * @test
     *
     * The known legacy asymmetry, pinned rather than fixed: `TenantCriteriaLoader`
     * hard-codes `preferred_zip_codes => []` because the legacy tenant criteria
     * form has no ZIP field. That is a LOADER limitation, not a matching one —
     * the shared engine scores a tenant ZIP perfectly well when a payload
     * carries one, which is what the modern `TenantOfferListingCriteriaLoader`
     * supplies. Asserting it here keeps the limitation visible and stops anyone
     * "discovering" it later as a scoring bug.
     */
    public function the_shared_engine_scores_zip_for_tenant_payloads_even_though_the_legacy_loader_sends_none(): void
    {
        $tenantWithZip = new BuyerCriteriaPayload([
            'property_types'      => ['Residential Lease'],
            'is_55_plus_eligible' => false,
            'preferred_zip_codes' => ['33602'],
        ]);

        $this->assertSame(
            self::CITY_ZIP_PTS,
            $this->locationScore($this->listing(['postal_code' => '33602']), $tenantWithZip),
            'The engine has no tenant-specific ZIP behaviour; only the legacy loader withholds ZIPs'
        );
    }
}
