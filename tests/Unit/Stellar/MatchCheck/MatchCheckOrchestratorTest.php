<?php

namespace Tests\Unit\Stellar\MatchCheck;

use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Services\Stellar\MatchCheck\CriteriaIntentDetector;
use App\Services\Stellar\MatchCheck\ListingVisibilityGate;
use App\Services\Stellar\MatchCheck\MatchCheckCriteriaLoader;
use App\Services\Stellar\MatchCheck\MatchCheckOrchestrator;
use App\Services\Stellar\MatchCheck\MatchCheckPreparation;
use App\Services\Stellar\MatchCheck\MatchCheckScorer;
use App\Services\Stellar\MatchCheck\VisibilityDecision;
use App\Services\Stellar\TenantCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

/**
 * Phase 4 · Wave 2 / C5 + C6 — MatchCheckOrchestrator.
 *
 * Verifies the COMPOSITION only: prepare() (C5 — flag gate → visibility → intent →
 * preferred) and evaluate() (C6 — prepare() + scoring layer), with the collaborators
 * mocked. The collaborators' own behaviour is already covered by their own tests; here we
 * assert the wiring, the short-circuits, and that the feature stays inert while the master
 * flag is OFF.
 */
class MatchCheckOrchestratorTest extends TestCase
{
    private function consumer(): User
    {
        $u = new User();
        $u->user_type = 'buyer';
        return $u;
    }

    private function listing(?string $propertyType = 'Residential'): BridgeProperty
    {
        return new BridgeProperty(['property_type' => $propertyType]);
    }

    /** @return array{ListingVisibilityGate&\Mockery\MockInterface, CriteriaIntentDetector&\Mockery\MockInterface, CriteriaListingResolver&\Mockery\MockInterface} */
    private function mocks(): array
    {
        return [
            Mockery::mock(ListingVisibilityGate::class),
            Mockery::mock(CriteriaIntentDetector::class),
            Mockery::mock(CriteriaListingResolver::class),
        ];
    }

    // -------------------------------------------------------------------------
    // git-C8 loader test doubles. MatchCheckCriteriaLoader is final (git-C7), so it cannot be
    // mocked directly; instead we build a REAL loader wrapping mocked leaf dependencies — the same
    // pattern these tests already use for MatchCheckScorer (real) around a mock BuyerMatchScorer.
    // -------------------------------------------------------------------------

    /**
     * A real MatchCheckCriteriaLoader whose per-type leaf loader returns $flat (or null). The
     * resolver's access-scope call is stubbed so no DB is touched.
     */
    private function loaderReturning(string $type, ?array $flat): MatchCheckCriteriaLoader
    {
        $leaves = [
            'buyer'        => Mockery::mock(BuyerCriteriaLoader::class),
            'tenant'       => Mockery::mock(TenantCriteriaLoader::class),
            'buyer_offer'  => Mockery::mock(BuyerOfferListingCriteriaLoader::class),
            'tenant_offer' => Mockery::mock(TenantOfferListingCriteriaLoader::class),
        ];
        $leaves[$type]->shouldReceive('loadById')->once()->andReturn($flat);

        $resolver = Mockery::mock(CriteriaListingResolver::class);
        $resolver->shouldReceive('resolveAllowedUserIds')->andReturn([99]);

        return new MatchCheckCriteriaLoader(
            $leaves['buyer'], $leaves['tenant'], $leaves['buyer_offer'], $leaves['tenant_offer'], $resolver
        );
    }

    /**
     * A real MatchCheckCriteriaLoader whose every dependency asserts it is never touched — proves
     * the orchestrator did not invoke the loader at all (the auto-load guard stayed false).
     */
    private function neverCalledLoader(): MatchCheckCriteriaLoader
    {
        $mk = function (string $class) {
            $m = Mockery::mock($class);
            $m->shouldNotReceive('loadById');
            return $m;
        };
        $resolver = Mockery::mock(CriteriaListingResolver::class);
        $resolver->shouldNotReceive('resolveAllowedUserIds');

        return new MatchCheckCriteriaLoader(
            $mk(BuyerCriteriaLoader::class),
            $mk(TenantCriteriaLoader::class),
            $mk(BuyerOfferListingCriteriaLoader::class),
            $mk(TenantOfferListingCriteriaLoader::class),
            $resolver,
        );
    }

    /** @test */
    public function flag_off_returns_disabled_and_touches_no_collaborator(): void
    {
        config()->set('mls_match_check.enabled', false);

        [$gate, $detector, $resolver] = $this->mocks();
        // Inert: with the flag off, NONE of the pieces should be consulted.
        $gate->shouldNotReceive('decide');
        $detector->shouldNotReceive('detectFromModel');
        $resolver->shouldNotReceive('resolvePreferred');

        $prep = (new MatchCheckOrchestrator($gate, $detector, $resolver))
            ->prepare($this->listing(), $this->consumer());

        $this->assertTrue($prep->isDisabled());
        $this->assertFalse($prep->enabled);
        $this->assertSame('feature_disabled', $prep->visibilityReason);
        $this->assertNull($prep->intent);
        $this->assertNull($prep->preferredCriteria);
    }

    /** @test */
    public function not_visible_returns_blocked_and_never_resolves_criteria(): void
    {
        config()->set('mls_match_check.enabled', true);

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldReceive('decide')
            ->once()
            ->andReturn(VisibilityDecision::blocked('idx_participation_false'));
        // A hidden listing must not trigger intent detection or a criteria DB read.
        $detector->shouldNotReceive('detectFromModel');
        $resolver->shouldNotReceive('resolvePreferred');

        $prep = (new MatchCheckOrchestrator($gate, $detector, $resolver))
            ->prepare($this->listing(), $this->consumer());

        $this->assertTrue($prep->isBlocked());
        $this->assertTrue($prep->enabled);
        $this->assertFalse($prep->visible);
        $this->assertSame('idx_participation_false', $prep->visibilityReason);
        $this->assertNull($prep->intent);
        $this->assertNull($prep->preferredCriteria);
    }

    /** @test */
    public function visible_sale_resolves_buyer_intent_and_passes_intent_to_resolver(): void
    {
        config()->set('mls_match_check.enabled', true);

        $record = ['id' => 7, 'type' => 'buyer', 'label' => 'B', 'created_at' => Carbon::parse('2026-05-01')];
        $listing = $this->listing('Commercial Sale');
        $user = $this->consumer();

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector->shouldReceive('detectFromModel')->once()->with($listing)->andReturn(CriteriaIntentDetector::BUYER);
        // The detected intent must be threaded into resolvePreferred so the right side is picked.
        $resolver->shouldReceive('resolvePreferred')->once()->with($user, CriteriaIntentDetector::BUYER)->andReturn($record);

        $prep = (new MatchCheckOrchestrator($gate, $detector, $resolver))->prepare($listing, $user);

        $this->assertTrue($prep->isReady());
        $this->assertTrue($prep->visible);
        $this->assertSame('idx_true', $prep->visibilityReason);
        $this->assertSame(CriteriaIntentDetector::BUYER, $prep->intent);
        $this->assertTrue($prep->hasPreferredCriteria());
        $this->assertSame(7, $prep->preferredCriteria['id']);
    }

    /** @test */
    public function visible_but_ambiguous_intent_is_passed_through_as_null(): void
    {
        config()->set('mls_match_check.enabled', true);

        $listing = $this->listing('Residential'); // tenure-ambiguous
        $user = $this->consumer();

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_absent_default_eligible'));
        $detector->shouldReceive('detectFromModel')->once()->with($listing)->andReturn(null);
        $resolver->shouldReceive('resolvePreferred')->once()->with($user, null)->andReturn(null);

        $prep = (new MatchCheckOrchestrator($gate, $detector, $resolver))->prepare($listing, $user);

        $this->assertTrue($prep->isReady());
        $this->assertNull($prep->intent);
        $this->assertFalse($prep->hasPreferredCriteria());
        $this->assertNull($prep->preferredCriteria);
    }

    /** @test */
    public function visible_with_no_accessible_record_is_ready_but_has_no_criteria(): void
    {
        config()->set('mls_match_check.enabled', true);

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector->shouldReceive('detectFromModel')->once()->andReturn(CriteriaIntentDetector::TENANT);
        $resolver->shouldReceive('resolvePreferred')->once()->andReturn(null);

        $prep = (new MatchCheckOrchestrator($gate, $detector, $resolver))
            ->prepare($this->listing('Commercial Lease'), $this->consumer());

        $this->assertTrue($prep->isReady());
        $this->assertSame(CriteriaIntentDetector::TENANT, $prep->intent);
        $this->assertNull($prep->preferredCriteria);
    }

    /** @test */
    public function is_enabled_mirrors_the_master_flag(): void
    {
        [$gate, $detector, $resolver] = $this->mocks();
        $orchestrator = new MatchCheckOrchestrator($gate, $detector, $resolver);

        config()->set('mls_match_check.enabled', false);
        $this->assertFalse($orchestrator->isEnabled());

        config()->set('mls_match_check.enabled', true);
        $this->assertTrue($orchestrator->isEnabled());
    }

    // =========================================================================
    // C6 — evaluate() composes prepare() with the scoring layer.
    // =========================================================================

    /** @test */
    public function evaluate_with_flag_off_returns_disabled_result_and_never_touches_the_engine(): void
    {
        config()->set('mls_match_check.enabled', false);

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldNotReceive('decide');
        $detector->shouldNotReceive('detectFromModel');
        $resolver->shouldNotReceive('resolvePreferred');

        // Real scorer wrapping a mock engine → prove the engine is never reached when inert.
        $engine = Mockery::mock(BuyerMatchScorer::class);
        $engine->shouldNotReceive('score');
        $scorer = new MatchCheckScorer($engine);

        $result = (new MatchCheckOrchestrator($gate, $detector, $resolver, $scorer))
            ->evaluate($this->listing(), $this->consumer(), new BuyerCriteriaPayload([
                'property_types'      => ['Residential'],
                'is_55_plus_eligible' => false,
            ]));

        $this->assertTrue($result->isDisabled());
        $this->assertFalse($result->scorable);
    }

    /** @test */
    public function evaluate_defaults_the_scorer_when_none_is_injected(): void
    {
        // Constructed with the C5 three-arg signature (scorer defaults to null); the flag is
        // OFF so evaluate() short-circuits before the lazily-defaulted engine is ever used.
        config()->set('mls_match_check.enabled', false);

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldNotReceive('decide');

        $result = (new MatchCheckOrchestrator($gate, $detector, $resolver))
            ->evaluate($this->listing(), $this->consumer());

        $this->assertTrue($result->isDisabled());
    }

    /** @test */
    public function evaluate_visible_with_criteria_and_payload_returns_a_scored_result(): void
    {
        config()->set('mls_match_check.enabled', true);

        $listing = $this->listing('Commercial Sale');
        $user    = $this->consumer();
        $payload = new BuyerCriteriaPayload([
            'property_types'      => ['Commercial Sale'],
            'is_55_plus_eligible' => false,
        ]);
        $record = ['id' => 3, 'type' => 'buyer', 'label' => 'B', 'created_at' => Carbon::parse('2026-05-01')];

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector->shouldReceive('detectFromModel')->once()->andReturn(CriteriaIntentDetector::BUYER);
        $resolver->shouldReceive('resolvePreferred')->once()->andReturn($record);

        $engine = Mockery::mock(BuyerMatchScorer::class);
        $engine->shouldReceive('score')->once()->with($listing, $payload)->andReturn(
            new BuyerMatchResult('KEY-9', 88, ['location' => 24], $listing)
        );
        $scorer = new MatchCheckScorer($engine);

        $result = (new MatchCheckOrchestrator($gate, $detector, $resolver, $scorer))
            ->evaluate($listing, $user, $payload);

        $this->assertTrue($result->isScored());
        $this->assertSame('KEY-9', $result->listingKey);
        $this->assertSame(88, $result->totalScore);
        $this->assertSame(CriteriaIntentDetector::BUYER, $result->intent);
    }

    /** @test */
    public function evaluate_visible_with_criteria_but_no_payload_returns_criteria_not_loaded(): void
    {
        // git-C8: with no payload supplied, evaluate() now auto-loads via MatchCheckCriteriaLoader.
        // When the loader cannot produce a payload (record gone / inaccessible / incomplete), the
        // outcome stays CRITERIA_NOT_LOADED — same terminal state as before, now reached via the
        // loader instead of a bare null.
        config()->set('mls_match_check.enabled', true);

        $record = ['id' => 4, 'type' => 'tenant', 'label' => 'T', 'created_at' => Carbon::parse('2026-05-01')];

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector->shouldReceive('detectFromModel')->once()->andReturn(CriteriaIntentDetector::TENANT);
        $resolver->shouldReceive('resolvePreferred')->once()->andReturn($record);

        // Loader is consulted (type 'tenant') but yields no payload → CRITERIA_NOT_LOADED.
        $loader = $this->loaderReturning('tenant', null);

        $engine = Mockery::mock(BuyerMatchScorer::class);
        $engine->shouldNotReceive('score');
        $scorer = new MatchCheckScorer($engine);

        $result = (new MatchCheckOrchestrator($gate, $detector, $resolver, $scorer, $loader))
            ->evaluate($this->listing('Commercial Lease'), $this->consumer(), null);

        $this->assertTrue($result->isCriteriaNotLoaded());
        $this->assertSame(CriteriaIntentDetector::TENANT, $result->intent);
    }

    // =========================================================================
    // git-C8 — evaluate() closes the CRITERIA_NOT_LOADED seam via MatchCheckCriteriaLoader.
    // =========================================================================

    /** @test */
    public function evaluate_auto_loads_payload_when_none_supplied_and_scores(): void
    {
        config()->set('mls_match_check.enabled', true);

        $listing = $this->listing('Commercial Sale');
        $user    = $this->consumer();
        $payload = new BuyerCriteriaPayload([
            'property_types'      => ['Commercial Sale'],
            'is_55_plus_eligible' => false,
        ]);
        $record = ['id' => 3, 'type' => 'buyer', 'label' => 'B', 'created_at' => Carbon::parse('2026-05-01')];

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector->shouldReceive('detectFromModel')->once()->andReturn(CriteriaIntentDetector::BUYER);
        $resolver->shouldReceive('resolvePreferred')->once()->andReturn($record);

        // The loader produces the payload evaluate() did not receive → seam closed → SCORED.
        // (The real loader builds its own BuyerCriteriaPayload from the flat array, so the engine
        // is matched on payload type rather than object identity.)
        $loader = $this->loaderReturning('buyer', [
            'property_types'      => ['Commercial Sale'],
            'is_55_plus_eligible' => false,
        ]);

        $engine = Mockery::mock(BuyerMatchScorer::class);
        $engine->shouldReceive('score')->once()
            ->with($listing, Mockery::type(BuyerCriteriaPayload::class))
            ->andReturn(new BuyerMatchResult('KEY-42', 91, ['location' => 25], $listing));
        $scorer = new MatchCheckScorer($engine);

        $result = (new MatchCheckOrchestrator($gate, $detector, $resolver, $scorer, $loader))
            ->evaluate($listing, $user, null);

        $this->assertTrue($result->isScored());
        $this->assertSame('KEY-42', $result->listingKey);
        $this->assertSame(91, $result->totalScore);
        $this->assertSame(CriteriaIntentDetector::BUYER, $result->intent);
    }

    /** @test */
    public function evaluate_with_explicit_payload_never_calls_the_loader(): void
    {
        // Override preserved: a caller-supplied payload is used verbatim; the loader is skipped.
        config()->set('mls_match_check.enabled', true);

        $listing = $this->listing('Commercial Sale');
        $payload = new BuyerCriteriaPayload([
            'property_types'      => ['Commercial Sale'],
            'is_55_plus_eligible' => false,
        ]);
        $record = ['id' => 5, 'type' => 'buyer', 'label' => 'B', 'created_at' => Carbon::parse('2026-05-01')];

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector->shouldReceive('detectFromModel')->once()->andReturn(CriteriaIntentDetector::BUYER);
        $resolver->shouldReceive('resolvePreferred')->once()->andReturn($record);

        $loader = $this->neverCalledLoader();

        $engine = Mockery::mock(BuyerMatchScorer::class);
        $engine->shouldReceive('score')->once()->with($listing, $payload)->andReturn(
            new BuyerMatchResult('KEY-5', 77, ['location' => 20], $listing)
        );
        $scorer = new MatchCheckScorer($engine);

        $result = (new MatchCheckOrchestrator($gate, $detector, $resolver, $scorer, $loader))
            ->evaluate($listing, $this->consumer(), $payload);

        $this->assertTrue($result->isScored());
        $this->assertSame(77, $result->totalScore);
    }

    /** @test */
    public function evaluate_flag_off_never_constructs_or_calls_the_loader(): void
    {
        // Inert: flag OFF → prepare() disabled → hasPreferredCriteria() false → loader untouched.
        config()->set('mls_match_check.enabled', false);

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldNotReceive('decide');

        $loader = $this->neverCalledLoader();

        $result = (new MatchCheckOrchestrator($gate, $detector, $resolver, null, $loader))
            ->evaluate($this->listing(), $this->consumer(), null);

        $this->assertTrue($result->isDisabled());
    }

    /** @test */
    public function evaluate_blocked_listing_never_calls_the_loader(): void
    {
        config()->set('mls_match_check.enabled', true);

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::blocked('idx_participation_false'));
        $detector->shouldNotReceive('detectFromModel');
        $resolver->shouldNotReceive('resolvePreferred');

        $loader = $this->neverCalledLoader();

        $result = (new MatchCheckOrchestrator($gate, $detector, $resolver, null, $loader))
            ->evaluate($this->listing(), $this->consumer(), null);

        $this->assertTrue($result->isBlocked());
    }

    /** @test */
    public function evaluate_ready_without_preferred_record_never_calls_the_loader(): void
    {
        // Visible but the user has no accessible criteria record → NO_CRITERIA, loader untouched.
        config()->set('mls_match_check.enabled', true);

        [$gate, $detector, $resolver] = $this->mocks();
        $gate->shouldReceive('decide')->once()->andReturn(VisibilityDecision::visible('idx_true'));
        $detector->shouldReceive('detectFromModel')->once()->andReturn(CriteriaIntentDetector::BUYER);
        $resolver->shouldReceive('resolvePreferred')->once()->andReturnNull();

        $loader = $this->neverCalledLoader();

        $result = (new MatchCheckOrchestrator($gate, $detector, $resolver, null, $loader))
            ->evaluate($this->listing('Commercial Sale'), $this->consumer(), null);

        $this->assertTrue($result->isNoCriteria());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
