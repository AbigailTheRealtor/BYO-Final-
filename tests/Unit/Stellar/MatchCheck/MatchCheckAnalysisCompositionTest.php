<?php

namespace Tests\Unit\Stellar\MatchCheck;

use App\Jobs\ComputeLocationDna;
use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\BridgePropertyCandidateAdapter;
use App\Services\Property\PropertyCandidate;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\MatchCheck\CriteriaIntentDetector;
use App\Services\Stellar\MatchCheck\EnrichmentGuardDecision;
use App\Services\Stellar\MatchCheck\EnrichmentThrottleSnapshot;
use App\Services\Stellar\MatchCheck\EnrichmentThrottleStore;
use App\Services\Stellar\MatchCheck\ListingVisibilityGate;
use App\Services\Stellar\MatchCheck\LocationDnaEnrichmentGuard;
use App\Services\Stellar\MatchCheck\MatchCheckOrchestrator;
use App\Services\Stellar\MatchCheck\MatchCheckPreparation;
use App\Services\Stellar\MatchCheck\MatchCheckResult;
use App\Services\Stellar\MatchCheck\MatchCheckScorer;
use App\Services\Stellar\MatchCheck\VisibilityDecision;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

/**
 * Phase 4 · git-C13a — MatchCheckOrchestrator::analyzeBy*() end-to-end composition.
 *
 * Exercises the lookup front-end + evaluate() + guard-routed enrichment wrapped into a
 * MatchCheckAnalysis, across the status matrix. Collaborators are mocked so the composition (wiring,
 * short-circuits, enrichment routing, inertness) is what's under test — their own behavior has its
 * own coverage. The rich MatchReport slot stays null (git-C13b).
 *
 * BridgeProperty::find() is the one real DB touch (candidate.sourceRecordId → model), so the
 * single-candidate paths seed a row and run against the shared dev DB with DatabaseTransactions.
 */
class MatchCheckAnalysisCompositionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 7, 7, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    // ── fixtures ──────────────────────────────────────────────────────────────

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
            'listing_key'       => 'PHPUNIT-C13A-K-' . $suffix,
            'listing_id'        => 'PHPUNIT-C13A-' . $suffix,
            'standard_status'   => 'Active',
            'property_type'     => 'Residential',
            'list_price'        => 400000,
            'unparsed_address'  => '10 Composition Way',
            'city'              => 'PhpunitC13aCity',
            'state_or_province' => 'FL',
            'postal_code'       => '33601',
            'raw_json'          => json_encode([
                'ListingKey'    => 'PHPUNIT-C13A-K-' . $suffix,
                'PublicRemarks' => 'RESTRICTED remarks that must never leak',
                'ListAgentKey'  => 'RESTRICTED-agent',
            ]),
        ]);
    }

    /** A real PropertyCandidate resolving to $listing (sourceRecordId = its id). */
    private function candidateFor(BridgeProperty $listing): PropertyCandidate
    {
        return (new BridgePropertyCandidateAdapter())->fromModel($listing->fresh());
    }

    /** An unpersisted candidate carrying restricted $raw (for AMBIGUOUS compliance checks). */
    private function detachedCandidate(string $key): PropertyCandidate
    {
        $model = new BridgeProperty([
            'listing_key' => $key,
            'listing_id'  => $key . '-id',
            'city'        => 'PhpunitC13aCity',
            'raw_json'    => json_encode([
                'ListingKey'    => $key,
                'PublicRemarks' => 'RESTRICTED remarks',
            ]),
        ]);

        return (new BridgePropertyCandidateAdapter())->fromModel($model);
    }

    private function passingSnapshot(): EnrichmentThrottleSnapshot
    {
        return new EnrichmentThrottleSnapshot(null, 0, null);
    }

    /**
     * Build an orchestrator. Any collaborator not supplied is a Mockery mock that asserts it is
     * never touched — so a test that passes nothing proves full inertness.
     */
    private function orchestrator(array $deps = []): MatchCheckOrchestrator
    {
        $gate     = $deps['gate']     ?? $this->strictMock(ListingVisibilityGate::class, 'decide');
        $detector = $deps['detector'] ?? $this->strictMock(CriteriaIntentDetector::class, 'detectFromModel');
        $resolver = $deps['resolver'] ?? $this->strictMock(CriteriaListingResolver::class, 'resolvePreferred');
        $scorer   = $deps['scorer']   ?? null;
        $lookup   = $deps['lookup']   ?? $this->strictMock(BridgeListingLookupService::class, 'findByMlsNumber', 'findByListingKey', 'searchByAddress');
        $guard    = $deps['guard']    ?? new LocationDnaEnrichmentGuard();
        $store    = $deps['store']    ?? $this->strictMock(EnrichmentThrottleStore::class, 'snapshot', 'recordAttempt');

        return new MatchCheckOrchestrator(
            visibilityGate: $gate,
            intentDetector: $detector,
            criteriaResolver: $resolver,
            scorer: $scorer,
            criteriaLoader: null,
            lookup: $lookup,
            enrichmentGuard: $guard,
            throttleStore: $store,
        );
    }

    private function strictMock(string $class, string ...$forbidden): object
    {
        $m = Mockery::mock($class);
        foreach ($forbidden as $method) {
            $m->shouldNotReceive($method);
        }

        return $m;
    }

    /** A MatchCheckScorer mocked to return $result verbatim from score(). */
    private function scorerReturning(MatchCheckResult $result): MatchCheckScorer
    {
        $m = Mockery::mock(MatchCheckScorer::class);
        $m->shouldReceive('score')->andReturn($result);

        return $m;
    }

    private function readyPrep(?string $intent): MatchCheckPreparation
    {
        return MatchCheckPreparation::ready(VisibilityDecision::visible('idx_true'), $intent, null);
    }

    // ── inertness ───────────────────────────────────────────────────────────

    /** @test */
    public function flag_off_returns_disabled_without_lookup_scoring_or_dispatch(): void
    {
        config()->set('mls_match_check.enabled', false);

        // Every collaborator asserts it is never touched.
        $analysis = $this->orchestrator()->analyzeByMlsNumber('ANYTHING', $this->consumer());

        $this->assertTrue($analysis->isDisabled());
        $this->assertNull($analysis->result);
        $this->assertNull($analysis->report);
        $this->assertSame(EnrichmentGuardDecision::REASON_FEATURE_DISABLED, $analysis->enrichmentReason);
        Bus::assertNothingDispatched();
    }

    /** @test */
    public function flag_off_address_entry_is_also_inert(): void
    {
        config()->set('mls_match_check.enabled', false);

        $analysis = $this->orchestrator()->analyzeByAddress(['city' => 'X'], $this->consumer());

        $this->assertTrue($analysis->isDisabled());
        Bus::assertNothingDispatched();
    }

    // ── lookup outcomes ───────────────────────────────────────────────────────

    /** @test */
    public function unresolved_identifier_returns_not_found(): void
    {
        config()->set('mls_match_check.enabled', true);

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByListingKey')->once()->with('MISSING', false)->andReturnNull();

        // gate/detector/resolver/store never reached — default strict mocks enforce that.
        $analysis = $this->orchestrator(['lookup' => $lookup])->analyzeByListingKey('MISSING', $this->consumer());

        $this->assertTrue($analysis->isNotFound());
        $this->assertNull($analysis->result);
        $this->assertSame(EnrichmentGuardDecision::REASON_FEATURE_DISABLED, $analysis->enrichmentReason);
        Bus::assertNothingDispatched();
    }

    /** @test */
    public function multi_candidate_address_returns_ambiguous_with_restricted_free_candidates(): void
    {
        config()->set('mls_match_check.enabled', true);

        $candidates = collect([
            $this->detachedCandidate('PHPUNIT-C13A-UNIT-1'),
            $this->detachedCandidate('PHPUNIT-C13A-UNIT-2'),
        ]);

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('searchByAddress')->once()
            ->with(['street_number' => '1', 'street_name' => 'Main'], false)
            ->andReturn($candidates);

        $analysis = $this->orchestrator(['lookup' => $lookup])
            ->analyzeByAddress(['street_number' => '1', 'street_name' => 'Main'], $this->consumer());

        $this->assertTrue($analysis->isAmbiguous());
        $this->assertNull($analysis->result);
        $this->assertCount(2, $analysis->candidates);
        $this->assertSame('PHPUNIT-C13A-UNIT-1', $analysis->candidates[0]['listing_key']);

        // F7 — candidates carry facts only, never the restricted source record.
        $encoded = json_encode($analysis->toArray());
        $this->assertArrayNotHasKey('raw', $analysis->candidates[0]);
        $this->assertStringNotContainsString('PublicRemarks', $encoded);
        $this->assertStringNotContainsString('RESTRICTED', $encoded);
        Bus::assertNothingDispatched();
    }

    /** @test */
    public function candidate_whose_source_record_is_gone_returns_not_found(): void
    {
        config()->set('mls_match_check.enabled', true);

        // A candidate pointing at a non-existent bridge_properties id.
        $model = new BridgeProperty(['listing_key' => 'GHOST', 'listing_id' => 'GHOST-id']);
        $model->id = 999999999;
        $ghost = (new BridgePropertyCandidateAdapter())->fromModel($model);

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByMlsNumber')->once()->with('GHOST-id', false)->andReturn($ghost);

        $analysis = $this->orchestrator(['lookup' => $lookup])->analyzeByMlsNumber('GHOST-id', $this->consumer());

        $this->assertTrue($analysis->isNotFound());
        Bus::assertNothingDispatched();
    }

    // ── evaluated outcomes + enrichment routing ───────────────────────────────

    /** @test */
    public function blocked_listing_is_wrapped_and_never_enriched(): void
    {
        config()->set('mls_match_check.enabled', true);

        $listing   = $this->seedListing('BLOCK');
        $candidate = $this->candidateFor($listing);

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByMlsNumber')->once()->andReturn($candidate);

        $gate = Mockery::mock(ListingVisibilityGate::class);
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::blocked('idx_participation_false'));

        // Real scorer wrapping a never-scored engine → blocked() carried straight through.
        $engine = Mockery::mock(BuyerMatchScorer::class);
        $engine->shouldNotReceive('score');

        // Store must not be consulted for a non-visible listing.
        $store = $this->strictMock(EnrichmentThrottleStore::class, 'snapshot', 'recordAttempt');

        $analysis = $this->orchestrator([
            'lookup'   => $lookup,
            'gate'     => $gate,
            'detector' => $this->strictMock(CriteriaIntentDetector::class, 'detectFromModel'),
            'resolver' => $this->strictMock(CriteriaListingResolver::class, 'resolvePreferred'),
            'scorer'   => new MatchCheckScorer($engine),
            'store'    => $store,
        ])->analyzeByMlsNumber($listing->listing_id, $this->consumer());

        $this->assertTrue($analysis->isBlocked());
        $this->assertNotNull($analysis->result);
        $this->assertSame(EnrichmentGuardDecision::REASON_FEATURE_DISABLED, $analysis->enrichmentReason);
        Bus::assertNothingDispatched();
    }

    /** @test */
    public function visible_but_no_criteria_defers_enrichment(): void
    {
        // NO_CRITERIA is a visible listing with no criteria context to compare against yet — a
        // non-attempt. Enrichment must stay deferred: the guard/store are never consulted and nothing
        // dispatches, even though the listing itself is visible.
        config()->set('mls_match_check.enabled', true);

        $listing   = $this->seedListing('NOCRIT');
        $candidate = $this->candidateFor($listing);

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByMlsNumber')->once()->andReturn($candidate);

        $gate = Mockery::mock(ListingVisibilityGate::class);
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector = Mockery::mock(CriteriaIntentDetector::class);
        $detector->shouldReceive('detectFromModel')->once()->andReturn(CriteriaIntentDetector::BUYER);
        $resolver = Mockery::mock(CriteriaListingResolver::class);
        $resolver->shouldReceive('resolvePreferred')->once()->andReturnNull(); // no record → NO_CRITERIA

        // Real scorer wrapping a never-scored engine → noCriteria() carried through.
        $engine = Mockery::mock(BuyerMatchScorer::class);
        $engine->shouldNotReceive('score');

        // The throttle store must not be touched for a non-attempt.
        $store = $this->strictMock(EnrichmentThrottleStore::class, 'snapshot', 'recordAttempt');

        $analysis = $this->orchestrator([
            'lookup'   => $lookup,
            'gate'     => $gate,
            'detector' => $detector,
            'resolver' => $resolver,
            'scorer'   => new MatchCheckScorer($engine),
            'store'    => $store,
        ])->analyzeByMlsNumber($listing->listing_id, $this->consumer());

        $this->assertTrue($analysis->isNoCriteria());
        $this->assertNotNull($analysis->result);
        $this->assertSame(EnrichmentGuardDecision::REASON_FEATURE_DISABLED, $analysis->enrichmentReason);
        Bus::assertNothingDispatched();
    }

    /** @test */
    public function criteria_not_loaded_is_a_real_attempt_and_routes_enrichment(): void
    {
        // CRITERIA_NOT_LOADED means a criteria record exists (the payload just wasn't loaded this
        // pass) — a real evaluation attempt, so enrichment IS routed through the guard.
        config()->set('mls_match_check.enabled', true);

        $listing   = $this->seedListing('CNL');
        $candidate = $this->candidateFor($listing);
        $user      = $this->consumer();

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByMlsNumber')->once()->andReturn($candidate);

        $gate = Mockery::mock(ListingVisibilityGate::class);
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector = Mockery::mock(CriteriaIntentDetector::class);
        $detector->shouldReceive('detectFromModel')->once()->andReturn(CriteriaIntentDetector::BUYER);
        $resolver = Mockery::mock(CriteriaListingResolver::class);
        $resolver->shouldReceive('resolvePreferred')->once()->andReturnNull();

        $cnl = MatchCheckResult::criteriaNotLoaded($this->readyPrep(CriteriaIntentDetector::BUYER));

        $store = Mockery::mock(EnrichmentThrottleStore::class);
        $store->shouldReceive('snapshot')->once()
            ->with('bridge', (int) $listing->id, (int) $user->id)
            ->andReturn($this->passingSnapshot());
        $store->shouldReceive('recordAttempt')->once()
            ->with('bridge', (int) $listing->id, (int) $user->id);

        $analysis = $this->orchestrator([
            'lookup'   => $lookup,
            'gate'     => $gate,
            'detector' => $detector,
            'resolver' => $resolver,
            'scorer'   => $this->scorerReturning($cnl),
            'store'    => $store,
        ])->analyzeByMlsNumber($listing->listing_id, $user);

        $this->assertTrue($analysis->isCriteriaNotLoaded());
        $this->assertSame(EnrichmentGuardDecision::REASON_ALLOWED, $analysis->enrichmentReason);
        Bus::assertDispatchedTimes(ComputeLocationDna::class, 1);
    }

    /** @test */
    public function visible_listing_scored_and_enrichment_allowed_dispatches_once_and_records(): void
    {
        config()->set('mls_match_check.enabled', true);

        $listing   = $this->seedListing('SCORE');
        $candidate = $this->candidateFor($listing);
        $user      = $this->consumer();

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByListingKey')->once()->andReturn($candidate);

        // prepare() runs; resolver returns null so evaluate() skips the criteria loader. The scorer is
        // mocked to yield SCORED regardless — this test isolates COMPOSITION, not the score engine.
        $gate = Mockery::mock(ListingVisibilityGate::class);
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector = Mockery::mock(CriteriaIntentDetector::class);
        $detector->shouldReceive('detectFromModel')->once()->andReturn(CriteriaIntentDetector::BUYER);
        $resolver = Mockery::mock(CriteriaListingResolver::class);
        $resolver->shouldReceive('resolvePreferred')->once()->andReturnNull();

        $scored = MatchCheckResult::scored($this->readyPrep(CriteriaIntentDetector::BUYER), 'KEY-SCORE', 88, ['location' => 24]);

        $store = Mockery::mock(EnrichmentThrottleStore::class);
        $store->shouldReceive('snapshot')->once()
            ->with('bridge', (int) $listing->id, (int) $user->id)
            ->andReturn($this->passingSnapshot());
        $store->shouldReceive('recordAttempt')->once()
            ->with('bridge', (int) $listing->id, (int) $user->id);

        $analysis = $this->orchestrator([
            'lookup'   => $lookup,
            'gate'     => $gate,
            'detector' => $detector,
            'resolver' => $resolver,
            'scorer'   => $this->scorerReturning($scored),
            'store'    => $store,
        ])->analyzeByListingKey($listing->listing_key, $user);

        $this->assertTrue($analysis->isScored());
        $this->assertSame('KEY-SCORE', $analysis->result->listingKey);
        $this->assertSame(88, $analysis->result->totalScore);
        $this->assertNull($analysis->report); // git-C13b populates this.
        $this->assertSame(EnrichmentGuardDecision::REASON_ALLOWED, $analysis->enrichmentReason);
        Bus::assertDispatchedTimes(ComputeLocationDna::class, 1);
    }

    /** @test */
    public function enrichment_denied_by_cooldown_does_not_dispatch_or_record(): void
    {
        config()->set('mls_match_check.enabled', true);

        $listing   = $this->seedListing('COOL');
        $candidate = $this->candidateFor($listing);
        $user      = $this->consumer();

        $lookup = Mockery::mock(BridgeListingLookupService::class);
        $lookup->shouldReceive('findByMlsNumber')->once()->andReturn($candidate);

        $gate = Mockery::mock(ListingVisibilityGate::class);
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector = Mockery::mock(CriteriaIntentDetector::class);
        $detector->shouldReceive('detectFromModel')->once()->andReturn(CriteriaIntentDetector::BUYER);
        $resolver = Mockery::mock(CriteriaListingResolver::class);
        $resolver->shouldReceive('resolvePreferred')->once()->andReturnNull();

        $scored = MatchCheckResult::scored($this->readyPrep(CriteriaIntentDetector::BUYER), 'KEY-COOL', 70, []);

        // Listing was enriched 5 minutes ago → within the 24h cooldown → guard denies.
        $store = Mockery::mock(EnrichmentThrottleStore::class);
        $store->shouldReceive('snapshot')->once()->andReturn(
            new EnrichmentThrottleSnapshot(CarbonImmutable::now()->subMinutes(5), 0, null)
        );
        $store->shouldNotReceive('recordAttempt');

        $analysis = $this->orchestrator([
            'lookup'   => $lookup,
            'gate'     => $gate,
            'detector' => $detector,
            'resolver' => $resolver,
            'scorer'   => $this->scorerReturning($scored),
            'store'    => $store,
        ])->analyzeByMlsNumber($listing->listing_id, $user);

        $this->assertTrue($analysis->isScored());
        $this->assertSame(EnrichmentGuardDecision::REASON_COOLDOWN_ACTIVE, $analysis->enrichmentReason);
        Bus::assertNothingDispatched();
    }
}
