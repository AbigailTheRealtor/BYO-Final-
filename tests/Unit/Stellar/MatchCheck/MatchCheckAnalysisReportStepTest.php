<?php

namespace Tests\Unit\Stellar\MatchCheck;

use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\BridgePropertyCandidateAdapter;
use App\Services\Property\PropertyCandidate;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\MatchCheck\CriteriaIntentDetector;
use App\Services\Stellar\MatchCheck\EnrichmentThrottleSnapshot;
use App\Services\Stellar\MatchCheck\EnrichmentThrottleStore;
use App\Services\Stellar\MatchCheck\ListingVisibilityGate;
use App\Services\Stellar\MatchCheck\LocationDnaEnrichmentGuard;
use App\Services\Stellar\MatchCheck\MatchCheckCriteriaLoader;
use App\Services\Stellar\MatchCheck\MatchCheckOrchestrator;
use App\Services\Stellar\MatchCheck\MatchReport;
use App\Services\Stellar\MatchCheck\VisibilityDecision;
use App\Services\Stellar\TenantCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

/**
 * Phase 4 · git-C13b — the SCORED-only report step wired into analyzeBy*().
 *
 * Verifies the COMPOSITION of git-C13b: a scored analyze path produces a populated MatchReport
 * (re-run pure engine → buildDetailed → MatchReportFactory) threaded onto MatchCheckAnalysis.report
 * with the right identity + injected generatedAt; every non-SCORED status keeps report === null. The
 * factory/builder projections have their own isolated coverage; here the real ones are used so the
 * wiring end-to-end is exercised. BridgeProperty::find() is the one real DB touch.
 */
class MatchCheckAnalysisReportStepTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 7, 7, 12, 0, 0));
        config()->set('mls_match_check.enabled', true);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    private function consumer(int $id = 99): User
    {
        $u = new User();
        $u->id = $id;
        $u->user_type = 'buyer';

        return $u;
    }

    private function seedListing(string $suffix): BridgeProperty
    {
        return BridgeProperty::create([
            'listing_key'       => 'PHPUNIT-C13B-K-' . $suffix,
            'listing_id'        => 'PHPUNIT-C13B-' . $suffix,
            'standard_status'   => 'Active',
            'property_type'     => 'Residential',
            'list_price'        => 400000,
            'unparsed_address'  => '20 Report Way',
            'city'              => 'PhpunitC13bCity',
            'state_or_province' => 'FL',
            'postal_code'       => '33601',
            'raw_json'          => json_encode([
                'ListingKey'    => 'PHPUNIT-C13B-K-' . $suffix,
                'PublicRemarks' => 'RESTRICTED remarks that must never leak',
                'ListAgentKey'  => 'RESTRICTED-agent',
            ]),
        ]);
    }

    private function candidateFor(BridgeProperty $listing): PropertyCandidate
    {
        return (new BridgePropertyCandidateAdapter())->fromModel($listing->fresh());
    }

    /** A real MatchCheckCriteriaLoader whose buyer leaf returns $flat (mirrors the C6/C8 tests). */
    private function loaderReturning(?array $flat): MatchCheckCriteriaLoader
    {
        $buyer = Mockery::mock(BuyerCriteriaLoader::class);
        $buyer->shouldReceive('loadById')->andReturn($flat);

        $resolver = Mockery::mock(CriteriaListingResolver::class);
        $resolver->shouldReceive('resolveAllowedUserIds')->andReturn([99]);

        return new MatchCheckCriteriaLoader(
            $buyer,
            Mockery::mock(TenantCriteriaLoader::class),
            Mockery::mock(BuyerOfferListingCriteriaLoader::class),
            Mockery::mock(TenantOfferListingCriteriaLoader::class),
            $resolver,
        );
    }

    private function passingStore(): EnrichmentThrottleStore
    {
        $store = Mockery::mock(EnrichmentThrottleStore::class);
        $store->shouldReceive('snapshot')->andReturn(new EnrichmentThrottleSnapshot(null, 0, null));
        $store->shouldReceive('recordAttempt');

        return $store;
    }

    /** @test */
    public function scored_path_produces_a_populated_report_with_identity_and_injected_timestamp(): void
    {
        $listing = $this->seedListing('SCORED');
        $user    = $this->consumer();

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByMlsNumber')->once()->andReturn($this->candidateFor($listing));

        $gate = Mockery::mock(ListingVisibilityGate::class);
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector = Mockery::mock(CriteriaIntentDetector::class);
        $detector->shouldReceive('detectFromModel')->once()->andReturn(CriteriaIntentDetector::BUYER);
        $resolver = Mockery::mock(CriteriaListingResolver::class);
        $resolver->shouldReceive('resolvePreferred')->once()->andReturn([
            'id' => 7, 'type' => 'buyer', 'label' => 'My buyer criteria', 'created_at' => Carbon::parse('2026-05-01'),
        ]);

        // Real MatchCheckScorer/BuyerMatchScorer (scorer=null) + real builder/factory (defaults) →
        // genuine SCORED and a genuinely projected report. Loader supplies the payload.
        $orchestrator = new MatchCheckOrchestrator(
            visibilityGate: $gate,
            intentDetector: $detector,
            criteriaResolver: $resolver,
            scorer: null,
            criteriaLoader: $this->loaderReturning([
                'property_types'      => ['Residential'],
                'is_55_plus_eligible' => false,
            ]),
            lookup: $lookup,
            enrichmentGuard: new LocationDnaEnrichmentGuard(),
            throttleStore: $this->passingStore(),
        );

        $analysis = $orchestrator->analyzeByMlsNumber($listing->listing_id, $user);

        $this->assertTrue($analysis->isScored());
        $this->assertInstanceOf(MatchReport::class, $analysis->report);
        $this->assertTrue($analysis->hasReport());

        $report = $analysis->report;
        // Identity + source injected by the report step.
        $this->assertSame(7, $report->criteriaId);
        $this->assertSame('buyer', $report->criteriaType);
        $this->assertSame('bridge', $report->source);
        // Score fields consistent with the lean result the analysis also carries (wiring, not math).
        $this->assertSame($analysis->result->totalScore, $report->totalScore);
        $this->assertSame($analysis->result->categoryScores, $report->categoryScores);
        $this->assertSame($listing->listing_key, $report->listingKey);
        // Injected ISO-8601 timestamp — exactly the frozen now, no internal now().
        $this->assertSame(CarbonImmutable::now()->toIso8601String(), $report->generatedAt);
        $this->assertNull($report->narrative);
        // F3 blocks are present (arrays, never null) after buildDetailed().
        $this->assertIsArray($report->whyNot);
        $this->assertIsArray($report->recommendations);

        // F7 — nothing restricted leaks through the analysis (incl. the nested report).
        $encoded = json_encode($analysis->toArray());
        $this->assertStringNotContainsString('PublicRemarks', $encoded);
        $this->assertStringNotContainsString('RESTRICTED', $encoded);
        $this->assertStringNotContainsString('raw_json', $encoded);
    }

    /** @test */
    public function blocked_status_keeps_report_null(): void
    {
        $listing = $this->seedListing('BLOCK');

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByMlsNumber')->once()->andReturn($this->candidateFor($listing));

        $gate = Mockery::mock(ListingVisibilityGate::class);
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::blocked('idx_participation_false'));
        $detector = Mockery::mock(CriteriaIntentDetector::class);
        $detector->shouldNotReceive('detectFromModel');
        $resolver = Mockery::mock(CriteriaListingResolver::class);
        $resolver->shouldNotReceive('resolvePreferred');

        $analysis = (new MatchCheckOrchestrator(
            visibilityGate: $gate,
            intentDetector: $detector,
            criteriaResolver: $resolver,
            lookup: $lookup,
            throttleStore: $this->neverTouchedStore(),
        ))->analyzeByMlsNumber($listing->listing_id, $this->consumer());

        $this->assertTrue($analysis->isBlocked());
        $this->assertNull($analysis->report);
        $this->assertFalse($analysis->hasReport());
    }

    /** @test */
    public function no_criteria_status_keeps_report_null(): void
    {
        $listing = $this->seedListing('NOCRIT');

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByMlsNumber')->once()->andReturn($this->candidateFor($listing));

        $gate = Mockery::mock(ListingVisibilityGate::class);
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector = Mockery::mock(CriteriaIntentDetector::class);
        $detector->shouldReceive('detectFromModel')->once()->andReturn(CriteriaIntentDetector::BUYER);
        $resolver = Mockery::mock(CriteriaListingResolver::class);
        $resolver->shouldReceive('resolvePreferred')->once()->andReturnNull(); // no record → NO_CRITERIA

        $analysis = (new MatchCheckOrchestrator(
            visibilityGate: $gate,
            intentDetector: $detector,
            criteriaResolver: $resolver,
            lookup: $lookup,
            throttleStore: $this->neverTouchedStore(),
        ))->analyzeByMlsNumber($listing->listing_id, $this->consumer());

        $this->assertTrue($analysis->isNoCriteria());
        $this->assertNull($analysis->report);
    }

    /** @test */
    public function disabled_and_not_found_keep_report_null(): void
    {
        // Flag OFF → DISABLED, report null, no lookup.
        config()->set('mls_match_check.enabled', false);
        $disabled = (new MatchCheckOrchestrator(
            visibilityGate: Mockery::mock(ListingVisibilityGate::class),
            intentDetector: Mockery::mock(CriteriaIntentDetector::class),
            criteriaResolver: Mockery::mock(CriteriaListingResolver::class),
            lookup: $this->neverTouchedLookup(),
            throttleStore: $this->neverTouchedStore(),
        ))->analyzeByMlsNumber('X', $this->consumer());
        $this->assertTrue($disabled->isDisabled());
        $this->assertNull($disabled->report);

        // Flag ON but identifier resolves to nothing → NOT_FOUND, report null.
        config()->set('mls_match_check.enabled', true);
        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByMlsNumber')->once()->andReturnNull();
        $notFound = (new MatchCheckOrchestrator(
            visibilityGate: Mockery::mock(ListingVisibilityGate::class),
            intentDetector: Mockery::mock(CriteriaIntentDetector::class),
            criteriaResolver: Mockery::mock(CriteriaListingResolver::class),
            lookup: $lookup,
            throttleStore: $this->neverTouchedStore(),
        ))->analyzeByMlsNumber('MISSING', $this->consumer());
        $this->assertTrue($notFound->isNotFound());
        $this->assertNull($notFound->report);
    }

    private function neverTouchedStore(): EnrichmentThrottleStore
    {
        $store = Mockery::mock(EnrichmentThrottleStore::class);
        $store->shouldNotReceive('snapshot');
        $store->shouldNotReceive('recordAttempt');

        return $store;
    }

    private function neverTouchedLookup(): BridgeListingLookupService
    {
        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldNotReceive('findByMlsNumber');

        return $lookup;
    }
}
