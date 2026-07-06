<?php

namespace Tests\Unit\Stellar\MatchCheck;

use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\MatchCheck\CriteriaIntentDetector;
use App\Services\Stellar\MatchCheck\ListingVisibilityGate;
use App\Services\Stellar\MatchCheck\MatchCheckOrchestrator;
use App\Services\Stellar\MatchCheck\MatchCheckPreparation;
use App\Services\Stellar\MatchCheck\VisibilityDecision;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

/**
 * Phase 4 · Wave 2 / C5 — MatchCheckOrchestrator.
 *
 * Verifies the COMPOSITION only (flag gate → visibility → intent → preferred),
 * with the three collaborators mocked. The collaborators' own behaviour is already
 * covered by their C1–C4 tests; here we assert the wiring, the short-circuits, and
 * that the feature stays inert while the master flag is OFF.
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
