<?php

namespace Tests\Unit\Stellar\MatchCheck;

use App\Services\Stellar\MatchCheck\MatchCheckPreparation;
use App\Services\Stellar\MatchCheck\MatchCheckResult;
use App\Services\Stellar\MatchCheck\VisibilityDecision;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Phase 4 · Wave 2 / C6 — MatchCheckResult value object.
 *
 * Covers each factory's status, scorability, and carried-through fields. This is a pure
 * data object, so the tests assert shape only — no scoring, I/O, or collaborators.
 */
class MatchCheckResultTest extends TestCase
{
    private function readyPrep(?string $intent, ?array $criteria): MatchCheckPreparation
    {
        return MatchCheckPreparation::ready(VisibilityDecision::visible('idx_true'), $intent, $criteria);
    }

    private function criteriaRecord(): array
    {
        return ['id' => 9, 'type' => 'buyer', 'label' => 'B', 'created_at' => Carbon::parse('2026-05-01')];
    }

    /** @test */
    public function disabled_is_not_scorable_and_carries_the_disabled_reason(): void
    {
        $result = MatchCheckResult::disabled(MatchCheckPreparation::disabled());

        $this->assertTrue($result->isDisabled());
        $this->assertFalse($result->scorable);
        $this->assertNull($result->intent);
        $this->assertSame('feature_disabled', $result->visibilityReason);
        $this->assertNull($result->listingKey);
        $this->assertNull($result->totalScore);
        $this->assertSame([], $result->categoryScores);
    }

    /** @test */
    public function blocked_is_not_scorable_and_carries_the_visibility_reason(): void
    {
        $prep = MatchCheckPreparation::blocked(VisibilityDecision::blocked('idx_participation_false'));

        $result = MatchCheckResult::blocked($prep);

        $this->assertTrue($result->isBlocked());
        $this->assertFalse($result->scorable);
        $this->assertNull($result->intent);
        $this->assertSame('idx_participation_false', $result->visibilityReason);
        $this->assertNull($result->totalScore);
    }

    /** @test */
    public function no_criteria_is_not_scorable_but_keeps_intent(): void
    {
        $result = MatchCheckResult::noCriteria($this->readyPrep('buyer', null));

        $this->assertTrue($result->isNoCriteria());
        $this->assertFalse($result->scorable);
        $this->assertSame('buyer', $result->intent);
        $this->assertSame('idx_true', $result->visibilityReason);
        $this->assertNull($result->totalScore);
    }

    /** @test */
    public function criteria_not_loaded_is_not_scorable_but_keeps_intent(): void
    {
        $result = MatchCheckResult::criteriaNotLoaded($this->readyPrep('tenant', $this->criteriaRecord()));

        $this->assertTrue($result->isCriteriaNotLoaded());
        $this->assertFalse($result->scorable);
        $this->assertSame('tenant', $result->intent);
        $this->assertNull($result->totalScore);
        $this->assertSame([], $result->categoryScores);
    }

    /** @test */
    public function scored_is_scorable_and_carries_score_fields(): void
    {
        $categories = ['location' => 24, 'price' => 20, 'size' => 15];

        $result = MatchCheckResult::scored(
            $this->readyPrep('buyer', $this->criteriaRecord()),
            'LISTING-KEY-1',
            83,
            $categories,
        );

        $this->assertTrue($result->isScored());
        $this->assertTrue($result->scorable);
        $this->assertSame('buyer', $result->intent);
        $this->assertSame('LISTING-KEY-1', $result->listingKey);
        $this->assertSame(83, $result->totalScore);
        $this->assertSame($categories, $result->categoryScores);
    }

    /** @test */
    public function status_predicates_are_mutually_exclusive(): void
    {
        $scored = MatchCheckResult::scored($this->readyPrep('buyer', $this->criteriaRecord()), 'K', 50, []);

        $this->assertTrue($scored->isScored());
        $this->assertFalse($scored->isDisabled());
        $this->assertFalse($scored->isBlocked());
        $this->assertFalse($scored->isNoCriteria());
        $this->assertFalse($scored->isCriteriaNotLoaded());
    }
}
