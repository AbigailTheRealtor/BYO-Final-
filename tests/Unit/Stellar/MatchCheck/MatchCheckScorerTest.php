<?php

namespace Tests\Unit\Stellar\MatchCheck;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Services\Stellar\MatchCheck\MatchCheckPreparation;
use App\Services\Stellar\MatchCheck\MatchCheckScorer;
use App\Services\Stellar\MatchCheck\VisibilityDecision;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

/**
 * Phase 4 · Wave 2 / C6 — MatchCheckScorer.
 *
 * Verifies the GATING only: each MatchCheckPreparation state maps to the right
 * MatchCheckResult, and — critically for inertness — that the underlying BuyerMatchScorer
 * engine is invoked ONLY in the single SCORED state and never otherwise. BuyerMatchScorer's
 * own scoring math is already covered by its dedicated tests; here we assert the wiring.
 */
class MatchCheckScorerTest extends TestCase
{
    private function listing(): BridgeProperty
    {
        return new BridgeProperty(['property_type' => 'Residential']);
    }

    private function payload(): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
        ]);
    }

    private function criteriaRecord(): array
    {
        return ['id' => 5, 'type' => 'buyer', 'label' => 'B', 'created_at' => Carbon::parse('2026-05-01')];
    }

    private function readyPrep(?string $intent, ?array $criteria): MatchCheckPreparation
    {
        return MatchCheckPreparation::ready(VisibilityDecision::visible('idx_true'), $intent, $criteria);
    }

    /** @test */
    public function disabled_preparation_returns_disabled_and_never_touches_the_engine(): void
    {
        $engine = Mockery::mock(BuyerMatchScorer::class);
        $engine->shouldNotReceive('score');

        $result = (new MatchCheckScorer($engine))
            ->score(MatchCheckPreparation::disabled(), $this->listing(), $this->payload());

        $this->assertTrue($result->isDisabled());
        $this->assertFalse($result->scorable);
    }

    /** @test */
    public function blocked_preparation_returns_blocked_and_never_touches_the_engine(): void
    {
        $engine = Mockery::mock(BuyerMatchScorer::class);
        $engine->shouldNotReceive('score');

        $prep = MatchCheckPreparation::blocked(VisibilityDecision::blocked('idx_participation_false'));

        $result = (new MatchCheckScorer($engine))->score($prep, $this->listing(), $this->payload());

        $this->assertTrue($result->isBlocked());
        $this->assertSame('idx_participation_false', $result->visibilityReason);
    }

    /** @test */
    public function ready_without_criteria_returns_no_criteria_and_never_touches_the_engine(): void
    {
        $engine = Mockery::mock(BuyerMatchScorer::class);
        $engine->shouldNotReceive('score');

        $result = (new MatchCheckScorer($engine))
            ->score($this->readyPrep('buyer', null), $this->listing(), $this->payload());

        $this->assertTrue($result->isNoCriteria());
        $this->assertSame('buyer', $result->intent);
    }

    /** @test */
    public function ready_with_criteria_but_no_payload_returns_criteria_not_loaded(): void
    {
        $engine = Mockery::mock(BuyerMatchScorer::class);
        // Seam state: we know which criteria, but its payload was not supplied → no scoring yet.
        $engine->shouldNotReceive('score');

        $result = (new MatchCheckScorer($engine))
            ->score($this->readyPrep('buyer', $this->criteriaRecord()), $this->listing(), null);

        $this->assertTrue($result->isCriteriaNotLoaded());
        $this->assertFalse($result->scorable);
        $this->assertSame('buyer', $result->intent);
    }

    /** @test */
    public function ready_with_criteria_and_payload_delegates_to_the_engine_and_maps_the_score(): void
    {
        $listing = $this->listing();
        $payload = $this->payload();

        $engineResult = new BuyerMatchResult(
            listingKey:     'KEY-42',
            totalScore:     77,
            categoryScores: ['location' => 24, 'price' => 20],
            listing:        $listing,
        );

        $engine = Mockery::mock(BuyerMatchScorer::class);
        $engine->shouldReceive('score')->once()->with($listing, $payload)->andReturn($engineResult);

        $result = (new MatchCheckScorer($engine))
            ->score($this->readyPrep('buyer', $this->criteriaRecord()), $listing, $payload);

        $this->assertTrue($result->isScored());
        $this->assertTrue($result->scorable);
        $this->assertSame('KEY-42', $result->listingKey);
        $this->assertSame(77, $result->totalScore);
        $this->assertSame(['location' => 24, 'price' => 20], $result->categoryScores);
        $this->assertSame('buyer', $result->intent);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
