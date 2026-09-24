<?php

namespace Tests\Feature\Stellar\Matching\Baseline;

use App\Models\User;
use App\Services\ListingImport\Mls\MlsDisplayPermissions;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\BuyerResultViewMapper;
use App\Services\Stellar\MatchCheck\CriteriaIntentDetector;
use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\PropertyMatchContextService;
use App\Services\Stellar\TenantCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use App\Support\Listing\PropertyTypeVocabulary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * ┌──────────────────────────────────────────────────────────────────────────────┐
 * │ CHARACTERIZATION OF CURRENT BEHAVIOR — NOT DESIRED BUSINESS LOGIC            │
 * │                                                                              │
 * │ Every test in this class pins a KNOWN DEFECT (P1 plan §27, D-n) exactly as   │
 * │ it behaves today, so the P1 refactors cannot change it by accident. None of  │
 * │ them is a specification. The PR that FIXES a defect is expected to rewrite   │
 * │ or delete the matching test here — and only that PR, deliberately, with the  │
 * │ defect named in its description.                                             │
 * │                                                                              │
 * │ Names follow `test_known_defect_dN_<what currently happens>`; nothing here   │
 * │ is worded as the behaviour being correct.                                    │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * Plan reference: docs/plans/p1-canonical-matching-convergence.md §27.
 */
class PreConvergenceKnownDefectCharacterizationTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * D-1 — a NEGATIVE amenity preference ("no pool", "no garage") is scored as if
     * the seeker WANTED the amenity: earned points go to `=== true` regardless of
     * the preference's sign. A no-pool seeker is penalised on a no-pool home and
     * rewarded on a pool home.
     */
    public function test_known_defect_d1_a_no_pool_no_garage_preference_currently_scores_as_wanting_both(): void
    {
        $bare = $this->storeBaselineFixture('residential');                                               // no pool, no garage
        $full = $this->storeBaselineFixture('residential', ['PoolPrivateYN' => true, 'GarageYN' => true], 'pool_garage');
        $scorer = new BuyerMatchScorer();

        $noPref  = $this->baselinePayload(['preferred_cities' => ['ST PETERSBURG']]);
        $wantsNo = $this->baselinePayload(['preferred_cities' => ['ST PETERSBURG'], 'wants_pool' => false, 'wants_garage' => false]);
        $wants   = $this->baselinePayload(['preferred_cities' => ['ST PETERSBURG'], 'wants_pool' => true, 'wants_garage' => true]);

        $this->assertSame(10, $scorer->score($bare, $noPref)->categoryScores['amenities']);
        $this->assertSame(0, $scorer->score($bare, $wantsNo)->categoryScores['amenities'], 'CURRENT: "no pool/garage" on a home without them earns 0');
        $this->assertSame(10, $scorer->score($full, $wantsNo)->categoryScores['amenities'], 'CURRENT: "no pool/garage" on a home WITH them earns full points');
        $this->assertSame(
            $scorer->score($bare, $wants)->categoryScores,
            $scorer->score($bare, $wantsNo)->categoryScores,
            'CURRENT: false and true preferences score identically'
        );
    }

    /**
     * D-2 — the property-detail match context never runs BuyerMatchResultBuilder,
     * so its explanation blocks are always empty, while the results page shows
     * them for the same listing and criteria.
     */
    public function test_known_defect_d2_the_detail_page_match_context_currently_has_empty_explanations(): void
    {
        $listing = $this->storeBaselineFixture('residential');
        $flat    = ['property_types' => ['Residential'], 'is_55_plus_eligible' => false, 'preferred_cities' => ['ST PETERSBURG'], 'max_price' => 250000];

        $buyerOffer = Mockery::mock(BuyerOfferListingCriteriaLoader::class);
        $buyerOffer->shouldReceive('loadById')->andReturn($flat);
        $context = new PropertyMatchContextService(
            Mockery::mock(BuyerCriteriaLoader::class),
            Mockery::mock(TenantCriteriaLoader::class),
            $buyerOffer,
            Mockery::mock(TenantOfferListingCriteriaLoader::class),
            new BuyerMatchScorer(),
            new BuyerResultViewMapper(),
        );
        $user = new User();
        $user->id = 1;

        $detail = $context->resolve($listing, 'buyer_offer', 7, $user);

        $this->assertSame([], $detail['why_this_matches'], 'CURRENT: empty on the detail page');
        $this->assertSame([], $detail['tradeoffs']);
        $this->assertSame([], $detail['caution_flags']);
        $this->assertSame([], $detail['missing_data']);

        // The same listing on the results page, via the live service, explains itself.
        $results = $this->baselineMatchService()->match($this->baselinePayload($flat), 200, 'buyer');
        $this->assertSame([$listing->listing_key], $results->map(fn ($r) => $r->listingKey)->all());
        $this->assertNotSame([], $results->first()->whyThisMatches);
        $this->assertNotSame([], $results->first()->cautionFlags);
        $this->assertSame($results->first()->totalScore, $detail['total_score'], 'the SCORE agrees; only the explanations are missing');
    }

    /**
     * D-3 — the results path applies only IDXParticipationYN, while the detail page
     * applies MlsDisplayPermissions::listingDisplayable() (IDX AND
     * InternetEntireListingDisplayYN). A listing the MLS says may not be shown on
     * the internet is currently LISTED on the results page and then 403s on click.
     */
    public function test_known_defect_d3_a_non_internet_displayable_listing_is_currently_listed_then_403s(): void
    {
        $listing = $this->storeBaselineFixture('residential', ['InternetEntireListingDisplayYN' => false], 'nointernet'); // route keys are [A-Za-z0-9-]+

        $results = $this->baselineMatchService()->match($this->baselinePayload(['preferred_cities' => ['ST PETERSBURG']]), 200, 'buyer');
        $this->assertSame([$listing->listing_key], $results->map(fn ($r) => $r->listingKey)->all(), 'CURRENT: selected and scored');
        $this->assertCount(1, (new BuyerResultViewMapper())->map($results), 'CURRENT: rendered as a results card');

        $this->assertFalse(MlsDisplayPermissions::fromRecord(json_decode($listing->raw_json, true))->listingDisplayable());

        $userId = DB::table('users')->insertGetId([
            'first_name' => 'P1A0', 'last_name' => 'D3', 'name' => 'P1A0 D3', 'short_id' => 'P1A0D3' . uniqid(),
            'user_name' => 'p1a0d3_' . uniqid(), 'email' => 'p1a0d3-' . uniqid() . '@example.com',
            'password' => bcrypt('password'), 'user_type' => 'buyer', 'is_approved' => true,
            'is_super' => false, 'is_deleted' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs(User::findOrFail($userId))
            ->get(route('stellar.property.show', ['listingKey' => $listing->listing_key]))
            ->assertForbidden();
    }

    /**
     * D-4 — the candidate query has no deterministic tie-break, and no ORDER BY at
     * all when the seeker gave no price. Which 250 rows survive the LIMIT, and the
     * order of equal scores, therefore depend on database row order. Pinned on the
     * query shape only — never on an observed row order, which would be flaky.
     */
    public function test_known_defect_d4_the_candidate_query_currently_has_no_tie_break_and_no_order_without_a_price(): void
    {
        $builder = new BuyerMatchQueryBuilder();

        $noPrice = $builder->build($this->baselinePayload(['preferred_state' => 'FL']))->getQuery();
        $this->assertNull($noPrice->orders, 'CURRENT: LIMIT 250 with no ORDER BY');
        $this->assertSame(250, $noPrice->limit);

        $withPrice = $builder->build($this->baselinePayload(['preferred_state' => 'FL', 'max_price' => 400000]))->getQuery();
        $this->assertCount(1, $withPrice->orders, 'CURRENT: a single ORDER BY expression …');
        $this->assertSame('(list_price IS NULL), ABS(list_price - ?)', $withPrice->orders[0]['sql'], '… with no id tie-break');
    }

    /**
     * D-5 — sale vs lease is decided by three different rules. Match Check's
     * CriteriaIntentDetector uses SUBSTRINGS and cannot classify a bare
     * 'Residential' record, which the governed vocabulary reads as a sale.
     */
    public function test_known_defect_d5_sale_vs_lease_is_currently_decided_three_different_ways(): void
    {
        $detector = new CriteriaIntentDetector();

        $this->assertNull($detector->detectFromType('Residential'), 'CURRENT: Match Check cannot tell');
        $this->assertSame(PropertyTypeVocabulary::TRANSACTION_SALE, PropertyTypeVocabulary::transactionFor('Residential'), 'the governed vocabulary says sale');
        $this->assertFalse($this->baselinePayload(['property_types' => ['Residential']])->isLeaseSearch(), 'the payload rule says not a lease');

        // And the scorer's non-residential branch is a third, exact-string rule:
        // 'Residential Income' / 'Land' (RESO spellings the vocabulary maps to Income /
        // Vacant Land) currently earn the default 0 there.
        $income = $this->storeBaselineFixture('income', ['PropertyType' => 'Residential Income'], 'reso_spelling');
        $this->assertSame(0, (new BuyerMatchScorer())->score($income, $this->baselinePayload(['property_types' => ['Residential Income']]))->categoryScores['non_residential']);
        $this->assertSame(10, (new BuyerMatchScorer())->score($this->storeBaselineFixture('income'), $this->baselinePayload(['property_types' => ['Income']]))->categoryScores['non_residential']);
    }

    /**
     * D-7 — a rent is shown on the results card with no period, so a weekly or an
     * annual rent reads as though it were monthly (or a purchase price).
     */
    public function test_known_defect_d7_a_results_card_rent_currently_shows_no_lease_period(): void
    {
        $monthly = $this->storeBaselineFixture('residential_lease');
        $weekly  = $this->storeBaselineFixture('residential_lease', ['LeaseAmountFrequency' => 'Weekly', 'ListPrice' => 800], 'weekly');
        $payload = $this->baselinePayload(['property_types' => ['Residential Lease'], 'preferred_cities' => ['NEW SMYRNA BEACH']]);
        $mapper  = new BuyerResultViewMapper();
        $scorer  = new BuyerMatchScorer();

        $this->assertSame('$3,495', $mapper->mapOne($scorer->score($monthly, $payload))['price_display'], 'CURRENT: no "/mo"');
        $this->assertSame('$800', $mapper->mapOne($scorer->score($weekly, $payload))['price_display'], 'CURRENT: no "/wk"');
    }

    /**
     * Observed while building this baseline (not in the plan's list): the lease-term
     * parser does not recognise the spaced spelling "Month To Month", and any term it
     * cannot parse earns the full 2 lease-term points for EVERY preference — so an
     * unparseable term is indistinguishable from a matching one.
     */
    public function test_known_defect_an_unparseable_lease_term_currently_earns_full_credit_for_any_preference(): void
    {
        $scorer = new BuyerMatchScorer();
        $pay    = fn (array $terms) => $this->baselinePayload(['property_types' => ['Residential Lease'], 'preferred_lease_terms' => $terms]);

        $spaced = $this->storeBaselineFixture('residential_lease', ['LeaseTerm' => 'Month To Month'], 'mtm_spaced');
        $short  = $this->storeBaselineFixture('residential_lease', ['LeaseTerm' => 'Short Term Lease'], 'short');
        $year   = $this->storeBaselineFixture('residential_lease', ['LeaseTerm' => '12 Months'], 'year');

        foreach ([$spaced, $short] as $listing) {
            foreach ([['6 Months'], ['1 Year'], ['3-5 Years'], ['Month-to-Month']] as $terms) {
                $this->assertSame(2, $scorer->score($listing, $pay($terms))->categoryScores['lifestyle'], 'CURRENT: unparseable → full credit');
            }
        }

        // A parseable term does discriminate.
        $this->assertSame(2, $scorer->score($year, $pay(['1 Year']))->categoryScores['lifestyle']);
        $this->assertSame(0, $scorer->score($year, $pay(['6 Months']))->categoryScores['lifestyle']);
    }
}
