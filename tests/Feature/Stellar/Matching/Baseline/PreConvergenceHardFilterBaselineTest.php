<?php

namespace Tests\Feature\Stellar\Matching\Baseline;

use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-A0 — which listings the live results path SELECTS today (SQL hard filters in
 * BuyerMatchQueryBuilder + the inline IDX gate in BuyerMatchService), pinned per
 * filter so a later change to candidate selection or eligibility cannot move one
 * silently.
 *
 * CHARACTERIZATION OF CURRENT BEHAVIOR — NOT DESIRED BUSINESS LOGIC.
 * Several of these pin rules the P1 plan calls out as open decisions (bathroom
 * integer-vs-decimal, the loose IDX gate). Each such test says so; changing one is
 * a deliberate, reviewed act, never a side effect of a refactor.
 *
 * Every listing is a real Stellar fixture stored through the real normalizer.
 */
class PreConvergenceHardFilterBaselineTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;

    /** @return list<string> the listing keys the live service returned, sorted. */
    private function selectedKeys(array $criteria, string $role = 'buyer', int $cap = 200): array
    {
        $keys = $this->baselineMatchService()
            ->match($this->baselinePayload($criteria), $cap, $role)
            ->map(fn ($r) => $r->listingKey)
            ->all();
        sort($keys);

        return $keys;
    }

    // ── status ──────────────────────────────────────────────────────────────

    public function test_only_active_standard_status_is_selected(): void
    {
        $this->storeBaselineFixture('residential', [], 'active');
        $this->storeBaselineFixture('residential', ['StandardStatus' => 'Pending'], 'pending');
        $this->storeBaselineFixture('residential', ['StandardStatus' => 'Active Under Contract'], 'auc');
        $this->storeBaselineFixture('residential', ['StandardStatus' => 'Coming Soon'], 'coming');
        $this->storeBaselineFixture('residential', ['StandardStatus' => 'Closed', 'MlsStatus' => 'Active'], 'closed_mls_active');

        $this->assertSame(
            [self::baselineKey('residential', 'active')],
            $this->selectedKeys(['preferred_cities' => ['ST PETERSBURG']]),
            'StandardStatus = Active is the only status selected; MlsStatus is never consulted'
        );
    }

    // ── property type (exact RESO strings, no vocabulary translation) ────────

    public function test_property_type_is_an_exact_match_on_the_feed_string(): void
    {
        $this->storeAllBaselineFixtures();

        $this->assertSame(
            [self::baselineKey('residential')],
            $this->selectedKeys(['property_types' => ['Residential'], 'preferred_state' => 'FL']),
            'Residential selects the sale record only — never Residential Lease or Income'
        );
        $this->assertSame(
            [self::baselineKey('commercial_lease'), self::baselineKey('residential_lease')],
            $this->selectedKeys(['property_types' => ['Residential Lease', 'Commercial Lease'], 'preferred_state' => 'FL'], 'tenant')
        );
        $this->assertSame(
            [],
            $this->selectedKeys(['property_types' => ['Commercial'], 'preferred_state' => 'FL']),
            'a BidYourOffer category name is not a feed PropertyType, so it selects nothing'
        );
    }

    // ── price ceiling ────────────────────────────────────────────────────────

    public function test_sale_price_ceiling_is_inclusive_and_a_null_price_passes(): void
    {
        $this->storeBaselineFixture('residential', [], 'at');                                   // 184,900
        $this->storeBaselineFixture('residential', ['ListPrice' => 184901], 'over');
        $this->storeBaselineFixture('residential', ['ListPrice' => null], 'no_price');

        $this->assertSame(
            [self::baselineKey('residential', 'at'), self::baselineKey('residential', 'no_price')],
            $this->selectedKeys(['preferred_cities' => ['ST PETERSBURG'], 'max_price' => 184900])
        );
    }

    public function test_lease_price_ceiling_is_widened_so_non_monthly_rents_survive_selection(): void
    {
        // $3,000/month budget. SQL cannot see the period, so the lease ceiling is the
        // budget × the widest period multiplier; the exact monthly comparison happens
        // in the scorer (pinned in the golden snapshot).
        $this->storeBaselineFixture('residential_lease', ['ListPrice' => 2500, 'LeaseAmountFrequency' => 'Weekly'], 'weekly');
        $this->storeBaselineFixture('residential_lease', ['ListPrice' => 30000, 'LeaseAmountFrequency' => 'Annually'], 'annual');
        $this->storeBaselineFixture('residential_lease', ['ListPrice' => 40000, 'LeaseAmountFrequency' => 'Annually'], 'annual_over');
        $this->storeBaselineFixture('residential_lease', ['ListPrice' => 3000000, 'LeaseAmountFrequency' => 'Monthly'], 'absurd');

        // Widest multiplier is 12 (annual), so the SQL ceiling is $36,000: a weekly
        // $2,500 (≈ $10,833/mo) survives selection and is judged by the scorer;
        // $40,000/yr (≈ $3,333/mo — actually over budget) is excluded by SQL; and
        // $30,000/yr (= $2,500/mo) survives.
        $this->assertSame(
            [
                self::baselineKey('residential_lease', 'annual'),
                self::baselineKey('residential_lease', 'weekly'),
            ],
            $this->selectedKeys(['property_types' => ['Residential Lease'], 'preferred_state' => 'FL', 'max_price' => 3000], 'tenant')
        );
    }

    // ── bedrooms / bathrooms ─────────────────────────────────────────────────

    public function test_bedroom_minimum_is_inclusive_and_an_unknown_count_passes(): void
    {
        $this->storeBaselineFixture('residential', [], 'two');                          // 2 beds
        $this->storeBaselineFixture('residential', ['BedroomsTotal' => 1], 'one');
        $this->storeBaselineFixture('residential', ['BedroomsTotal' => 0], 'studio');
        $this->storeBaselineFixture('residential', ['BedroomsTotal' => null], 'unknown');

        $this->assertSame(
            [self::baselineKey('residential', 'two'), self::baselineKey('residential', 'unknown')],
            $this->selectedKeys(['preferred_cities' => ['ST PETERSBURG'], 'min_bedrooms' => 2])
        );
        $this->assertSame(
            [self::baselineKey('residential', 'one'), self::baselineKey('residential', 'two'), self::baselineKey('residential', 'unknown')],
            $this->selectedKeys(['preferred_cities' => ['ST PETERSBURG'], 'min_bedrooms' => 1]),
            'a studio (0 bedrooms) is excluded by a 1-bedroom minimum — 0 is a fact here, not unknown'
        );
    }

    /**
     * CHARACTERIZATION — bathroom semantics are an OPEN DECISION (plan §13).
     * The filter reads BathroomsTotalInteger; the fixture carries 2 (integer) with
     * a 1.5 decimal total, so a 2-bath minimum passes today.
     */
    public function test_bathroom_minimum_reads_the_integer_total_not_the_decimal(): void
    {
        $this->storeBaselineFixture('residential', [], 'int2_dec15');
        $this->storeBaselineFixture('residential', ['BathroomsTotalInteger' => 1, 'BathroomsTotalDecimal' => 1.5], 'int1_dec15');
        $this->storeBaselineFixture('residential', ['BathroomsTotalInteger' => null], 'unknown');

        $this->assertSame(
            [self::baselineKey('residential', 'int2_dec15'), self::baselineKey('residential', 'unknown')],
            $this->selectedKeys(['preferred_cities' => ['ST PETERSBURG'], 'min_bathrooms' => 2])
        );
    }

    // ── 55+ senior community gate (legal compliance) ─────────────────────────

    public function test_senior_communities_are_excluded_unless_the_seeker_is_eligible_and_unknown_passes(): void
    {
        $this->storeBaselineFixture('residential', ['SeniorCommunityYN' => false], 'not_senior');
        $this->storeBaselineFixture('residential', ['SeniorCommunityYN' => true], 'senior');
        $this->storeBaselineFixture('residential', [], 'senior_unknown', ['senior_community_yn' => null]);

        $criteria = ['preferred_cities' => ['ST PETERSBURG']];

        $this->assertSame(
            [self::baselineKey('residential', 'not_senior'), self::baselineKey('residential', 'senior_unknown')],
            $this->selectedKeys($criteria)
        );
        $this->assertSame(
            [self::baselineKey('residential', 'not_senior'), self::baselineKey('residential', 'senior'), self::baselineKey('residential', 'senior_unknown')],
            $this->selectedKeys($criteria + ['is_55_plus_eligible' => true])
        );
    }

    // ── geography ────────────────────────────────────────────────────────────

    public function test_each_geography_tier_selects_on_its_own_and_tiers_are_ored(): void
    {
        $this->storeAllBaselineFixtures();

        $types = ['Residential', 'Commercial Sale', 'Business Opportunity', 'Vacant Land', 'Income'];
        $all   = fn (array $geo) => $this->selectedKeys(['property_types' => $types] + $geo);

        $this->assertSame(
            [self::baselineKey('business_opportunity')],
            $all(['preferred_cities' => ['WIMAUMA']]),
            'city is an exact match on the stored (upper-case) feed spelling'
        );
        $this->assertSame([], $all(['preferred_cities' => ['Wimauma']]), 'the SQL city match is case-sensitive on SQLite');
        $this->assertSame(
            [self::baselineKey('income'), self::baselineKey('residential')],
            $all(['preferred_zip_codes' => ['33710']])
        );
        $this->assertSame(
            [self::baselineKey('business_opportunity')],
            $all(['preferred_counties' => ['Manatee']])
        );
        $this->assertSame(
            [
                self::baselineKey('business_opportunity'), self::baselineKey('commercial_sale'),
                self::baselineKey('income'), self::baselineKey('residential'), self::baselineKey('vacant_land'),
            ],
            $all(['preferred_state' => 'FL']),
            'a state alone is declared geography and selects the whole state'
        );
        $this->assertSame(
            [self::baselineKey('business_opportunity'), self::baselineKey('income'), self::baselineKey('residential')],
            $all(['preferred_zip_codes' => ['33710'], 'preferred_counties' => ['Manatee']]),
            'tiers are ORed, never ANDed'
        );
    }

    public function test_no_geography_at_all_applies_no_geographic_clause(): void
    {
        $this->storeAllBaselineFixtures();

        $this->assertSame(
            [self::baselineKey('residential')],
            $this->selectedKeys(['property_types' => ['Residential']]),
            'the service itself applies no location requirement; the results controller refuses a criteria with no location before calling it'
        );
    }

    public function test_radius_and_polygon_select_by_bounding_box_not_by_exact_distance(): void
    {
        $listing = $this->storeBaselineFixture('residential');
        $lat     = (float) $listing->latitude;
        $lng     = (float) $listing->longitude;

        // A 1-mile radius whose bbox CORNER holds the listing: inside the box
        // (selected) but ~1.3 mi from the centre, so it earns no proximity points.
        $corner = ['lat' => $lat - 0.9 / 69.0, 'lng' => $lng - 0.9 / (69.0 * cos(deg2rad($lat))), 'radius_miles' => 1];
        $this->assertSame([$listing->listing_key], $this->selectedKeys(['radius_searches' => [$corner]]));

        $far = ['lat' => $lat + 3 / 69.0, 'lng' => $lng, 'radius_miles' => 1];
        $this->assertSame([], $this->selectedKeys(['radius_searches' => [$far]]));

        // A concave (L-shaped) polygon whose bbox contains the listing while the
        // shape does not: selected by SQL, 0 polygon points in the scorer.
        $l = [['path' => [
            ['lat' => $lat - 0.02, 'lng' => $lng - 0.02], ['lat' => $lat - 0.02, 'lng' => $lng + 0.02],
            ['lat' => $lat - 0.01, 'lng' => $lng + 0.02], ['lat' => $lat - 0.01, 'lng' => $lng - 0.01],
            ['lat' => $lat + 0.02, 'lng' => $lng - 0.01], ['lat' => $lat + 0.02, 'lng' => $lng - 0.02],
        ]]];
        $results = $this->baselineMatchService()->match($this->baselinePayload(['polygons' => $l]), 200, 'buyer');
        $this->assertSame([$listing->listing_key], $results->map(fn ($r) => $r->listingKey)->all());
        $this->assertSame(0, $results->first()->categoryScores['location'], 'outside the drawn shape: bbox-selected, zero location points');

        // Radius rows with no positive radius add no clause at all.
        $this->assertSame([$listing->listing_key], $this->selectedKeys(['radius_searches' => [['lat' => 0, 'lng' => 0, 'radius_miles' => 0]], 'preferred_state' => 'FL']));
    }

    // ── IDX / display eligibility ───────────────────────────────────────────

    /**
     * CHARACTERIZATION — the results path reads IDXParticipationYN ONLY, and fails
     * OPEN on anything it cannot read. InternetEntireListingDisplayYN = false is NOT
     * applied here (it is on the detail page → D-3, pinned in
     * PreConvergenceKnownDefectCharacterizationTest).
     */
    public function test_results_idx_gate_reads_idx_participation_only_and_fails_open(): void
    {
        $this->storeBaselineFixture('residential', [], 'idx_true');
        $this->storeBaselineFixture('residential', ['IDXParticipationYN' => false], 'idx_false');
        $this->storeBaselineFixture('residential', ['IDXParticipationYN' => 'false'], 'idx_string_false');
        $this->storeBaselineFixture('residential', ['IDXParticipationYN' => 0], 'idx_zero');
        $this->storeBaselineFixture('residential', ['IDXParticipationYN' => 'maybe'], 'idx_garbage');
        $this->storeBaselineFixture('residential', ['IDXParticipationYN' => null], 'idx_null');
        $this->storeBaselineFixture('residential', ['InternetEntireListingDisplayYN' => false], 'internet_false');
        $absent = $this->fixtureRecord('residential');
        unset($absent['IDXParticipationYN']);
        $this->storeBaselineFixture('residential', [], 'idx_absent', ['raw_json' => json_encode(array_merge($absent, ['ListingKey' => self::baselineKey('residential', 'idx_absent')]))]);
        $this->storeBaselineFixture('residential', [], 'malformed', ['raw_json' => '{not json']);

        // Note the asymmetry, pinned as-is: an ABSENT key, an unparseable value and
        // malformed JSON all pass (fail open), but an explicit JSON null is EXCLUDED —
        // filter_var(null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) is false,
        // not null (the same null→false collapse as the P0-5 boolean follow-up).
        $this->assertSame(
            [
                self::baselineKey('residential', 'idx_absent'),
                self::baselineKey('residential', 'idx_garbage'),
                self::baselineKey('residential', 'idx_true'),
                self::baselineKey('residential', 'internet_false'),
                self::baselineKey('residential', 'malformed'),
            ],
            $this->selectedKeys(['preferred_cities' => ['ST PETERSBURG']])
        );
    }

    // ── cap / over-fetch ─────────────────────────────────────────────────────

    public function test_candidate_cap_trims_after_the_idx_gate_from_a_125_percent_overfetch(): void
    {
        foreach (range(1, 6) as $i) {
            $this->storeBaselineFixture('residential', ['ListPrice' => 180000 + $i * 1000], "p{$i}");
        }
        $this->storeBaselineFixture('residential', ['ListPrice' => 180000, 'IDXParticipationYN' => false], 'p0_hidden');

        // cap 4 → fetch ceil(4 × 1.25) = 5 rows ordered by |price − ideal|; the
        // hidden row is the closest, so it consumes one fetched slot and 4 remain.
        $keys = $this->selectedKeys(['preferred_cities' => ['ST PETERSBURG'], 'ideal_price' => 180000], 'buyer', 4);

        $this->assertSame(
            [
                self::baselineKey('residential', 'p1'), self::baselineKey('residential', 'p2'),
                self::baselineKey('residential', 'p3'), self::baselineKey('residential', 'p4'),
            ],
            $keys
        );

        $query = (new BuyerMatchQueryBuilder())->build($this->baselinePayload(['preferred_cities' => ['X']]), 200);
        $this->assertSame(250, $query->getQuery()->limit, 'the live cap of 200 fetches 250 rows');
        $this->assertSame(1.25, BuyerMatchQueryBuilder::IDX_OVERFETCH_MULTIPLIER);
    }
}
