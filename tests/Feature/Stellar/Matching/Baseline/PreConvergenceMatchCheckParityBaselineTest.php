<?php

namespace Tests\Feature\Stellar\Matching\Baseline;

use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\BridgePropertyCandidateAdapter;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\MatchCheck\CriteriaIntentDetector;
use App\Services\Stellar\MatchCheck\EnrichmentThrottleSnapshot;
use App\Services\Stellar\MatchCheck\EnrichmentThrottleStore;
use App\Services\Stellar\MatchCheck\ListingVisibilityGate;
use App\Services\Stellar\MatchCheck\LocationDnaEnrichmentGuard;
use App\Services\Stellar\MatchCheck\MatchCheckAnalysis;
use App\Services\Stellar\MatchCheck\MatchCheckCriteriaLoader;
use App\Services\Stellar\MatchCheck\MatchCheckOrchestrator;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\ImportantPlaceMatcher;
use App\Services\Stellar\TenantCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-A0 — Match Check agrees with the results page, per property category.
 *
 * CHARACTERIZATION OF CURRENT BEHAVIOR — NOT DESIRED BUSINESS LOGIC.
 *
 * Match Check reaches the same BuyerMatchScorer as the results page, but through
 * its own orchestration: lookup → BridgeProperty::find → ListingVisibilityGate →
 * CriteriaIntentDetector → criteria resolution → MatchCheckScorer (lean result)
 * → a SECOND BuyerMatchScorer::score + buildDetailed for the MatchReport. The
 * double scoring (plan D-6) is left alone; what is pinned is that both of its
 * outputs equal the results-page answer for the same listing and criteria.
 *
 * The visibility gate and the intent detector are REAL here, so their current
 * per-category answers are pinned too. The lookup, the criteria resolver and the
 * criteria loader are stubbed: they choose WHICH listing and WHICH criteria, and
 * this test supplies both directly.
 */
class PreConvergenceMatchCheckParityBaselineTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;

    /** What the REAL CriteriaIntentDetector answers today, per category (plan D-5). */
    private const INTENT = [
        'residential'          => null,  // bare 'Residential' is tenure-ambiguous to the substring rule
        'residential_lease'    => CriteriaIntentDetector::TENANT,
        'income'               => CriteriaIntentDetector::BUYER,
        'commercial_sale'      => CriteriaIntentDetector::BUYER,
        'commercial_lease'     => CriteriaIntentDetector::TENANT,
        'business_opportunity' => CriteriaIntentDetector::BUYER,
        'vacant_land'          => CriteriaIntentDetector::BUYER,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 9, 23, 12, 0, 0));
        config()->set('mls_match_check.enabled', true);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    /** @return array<string,array{string}> */
    public static function categories(): array
    {
        return array_combine(
            array_keys(self::INTENT),
            array_map(fn ($s) => [$s], array_keys(self::INTENT))
        );
    }

    /** @dataProvider categories */
    public function test_match_check_score_and_report_equal_the_results_page_for_each_category(string $slug): void
    {
        $listing  = $this->storeBaselineFixture($slug);
        $record   = $this->fixtureRecord($slug);
        $lat      = (float) $record['Latitude'];
        $lng      = (float) $record['Longitude'];
        $flat = [
            'is_55_plus_eligible' => false,
            'property_types'   => [self::$CATEGORIES[$slug]],
            'preferred_cities' => [$record['City']],
            'max_price'        => (int) ceil(((float) $record['ListPrice']) / 0.85),
            'radius_searches'  => [['lat' => $lat + 1 / 69.0, 'lng' => $lng, 'radius_miles' => 4]],
            'important_places' => [['type' => 'Work', 'address' => 'Office', 'lat' => $lat + 1 / 69.0, 'lng' => $lng, 'distance_pref' => 'miles', 'distance_value' => 3]],
        ];
        $criteria = new BuyerCriteriaPayload($flat);

        [$analysis, $intentSeen] = $this->analyze($listing, $flat, $slug);

        $this->assertSame(self::INTENT[$slug], $intentSeen, "{$slug}: intent passed to criteria resolution");
        $this->assertTrue($analysis->isScored(), "{$slug}: Match Check scores an IDX-visible listing");

        // The results page for the same listing and criteria.
        $service = $this->baselineMatchService()
            ->match($criteria, 200, in_array($slug, ['residential_lease', 'commercial_lease'], true) ? 'tenant' : 'buyer')
            ->first(fn ($r) => $r->listingKey === $listing->listing_key);
        $this->assertNotNull($service, "{$slug}: the results page selects the listing");

        // Lean result (the first of Match Check's two scorings).
        $this->assertSame($service->listingKey, $analysis->result->listingKey);
        $this->assertSame($service->totalScore, $analysis->result->totalScore);
        $this->assertSame($service->categoryScores, $analysis->result->categoryScores);

        // Report (the second scoring + buildDetailed).
        $report   = $analysis->report;
        $detailed = (new BuyerMatchResultBuilder())->buildDetailed((new BuyerMatchScorer())->score($listing, $criteria), $criteria);

        $this->assertSame($service->totalScore, $report->totalScore);
        $this->assertSame($service->categoryScores, $report->categoryScores);
        $this->assertSame($service->whyThisMatches, $report->whyThisMatches, "{$slug}: why_this_matches");
        $this->assertSame($service->tradeoffs, $report->tradeoffs, "{$slug}: tradeoffs");
        $this->assertSame($service->missingData, $report->missingData, "{$slug}: missing_data");
        $this->assertSame($detailed->whyNot, $report->whyNot, "{$slug}: why_not");
        $this->assertSame($detailed->confidence, $report->confidence, "{$slug}: confidence");
        $this->assertSame($detailed->recommendations, $report->recommendations, "{$slug}: recommendations");
        $this->assertSame(ImportantPlaceMatcher::present($service->importantPlaceMatches), $report->importantPlaces, "{$slug}: important places");
        $this->assertSame(CarbonImmutable::now()->toIso8601String(), $report->generatedAt);

        // Caution flags are carried by the results page but NOT by the MatchReport
        // contract — pinned so a refactor does not quietly add or drop them there.
        $this->assertArrayNotHasKey('caution_flags', $report->toArray());
    }

    public function test_an_idx_refused_listing_is_blocked_before_scoring(): void
    {
        $listing  = $this->storeBaselineFixture('residential', ['IDXParticipationYN' => false], 'idx_false');
        $flat     = ['property_types' => ['Residential'], 'is_55_plus_eligible' => false, 'preferred_cities' => ['ST PETERSBURG']];
        $criteria = new BuyerCriteriaPayload($flat);

        [$analysis] = $this->analyze($listing, $flat, 'residential', expectCriteriaResolution: false);

        $this->assertTrue($analysis->isBlocked());
        $this->assertNull($analysis->report);
        $this->assertSame(
            [],
            $this->baselineMatchService()->match($criteria, 200, 'buyer')->all(),
            'the results page excludes it as well'
        );
    }

    // =========================================================================

    /**
     * Run the real orchestrator for one listing. The criteria arrive as the flat
     * array a leaf loader returns, and the REAL MatchCheckCriteriaLoader (final)
     * turns it into the payload, exactly as in production.
     *
     * @return array{0: MatchCheckAnalysis, 1: ?string} the analysis and the intent the
     *         real detector handed to criteria resolution
     */
    private function analyze(BridgeProperty $listing, array $flatCriteria, string $slug, bool $expectCriteriaResolution = true): array
    {
        $user = new User();
        $user->id = 4242;
        $user->user_type = 'buyer';

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByListingKey')->once()
            ->andReturn((new BridgePropertyCandidateAdapter())->fromModel($listing->fresh()));

        $intentSeen = 'not-called';
        $resolver   = Mockery::mock(CriteriaListingResolver::class);
        $resolver->shouldReceive('resolvePreferred')
            ->times($expectCriteriaResolution ? 1 : 0)
            ->andReturnUsing(function ($u, $intent) use (&$intentSeen, $slug) {
                $intentSeen = $intent;

                return [
                    'id' => 7, 'label' => 'Baseline criteria', 'created_at' => CarbonImmutable::parse('2026-09-01'),
                    'type' => in_array($slug, ['residential_lease', 'commercial_lease'], true) ? 'tenant_offer' : 'buyer_offer',
                ];
            });

        $resolver->shouldReceive('resolveAllowedUserIds')->andReturn([4242]);
        $buyerOffer = Mockery::mock(BuyerOfferListingCriteriaLoader::class);
        $buyerOffer->shouldReceive('loadById')->andReturn($flatCriteria);
        $tenantOffer = Mockery::mock(TenantOfferListingCriteriaLoader::class);
        $tenantOffer->shouldReceive('loadById')->andReturn($flatCriteria);

        $loader = new MatchCheckCriteriaLoader(
            Mockery::mock(BuyerCriteriaLoader::class),
            Mockery::mock(TenantCriteriaLoader::class),
            $buyerOffer,
            $tenantOffer,
            $resolver,
        );

        $store = Mockery::mock(EnrichmentThrottleStore::class);
        $store->shouldReceive('snapshot')->andReturn(new EnrichmentThrottleSnapshot(null, 0, null));
        $store->shouldReceive('recordAttempt');

        $orchestrator = new MatchCheckOrchestrator(
            visibilityGate: new ListingVisibilityGate(),
            intentDetector: new CriteriaIntentDetector(),
            criteriaResolver: $resolver,
            scorer: null,
            criteriaLoader: $loader,
            lookup: $lookup,
            enrichmentGuard: new LocationDnaEnrichmentGuard(),
            throttleStore: $store,
        );

        $analysis = $orchestrator->analyzeByListingKey($listing->listing_key, $user);

        return [$analysis, $intentSeen];
    }
}
