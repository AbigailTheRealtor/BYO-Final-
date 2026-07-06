<?php

namespace Tests\Unit\Stellar\MatchCheck;

use App\Services\Stellar\MatchCheck\MatchReport;
use Tests\TestCase;

/**
 * Phase 4 · git-C9 (Plan-C5, F3/F8) — MatchReport DTO.
 *
 * Verifies the pure data model only: construction, the snake_case toArray() round-trip, the
 * nullable AI/narrative default, the injected (never-now()) timestamp, and F8 serializability.
 * No scoring, building, flag, or I/O is involved.
 */
class MatchReportTest extends TestCase
{
    private function report(?array $narrative = null): MatchReport
    {
        return new MatchReport(
            criteriaId: 7,
            criteriaType: 'buyer',
            listingKey: 'KEY-9',
            source: 'bridge',
            totalScore: 88,
            categoryScores: ['location' => 24, 'price' => 20],
            whyThisMatches: [['dimension' => 'location', 'label' => 'In your area']],
            whyNot: [['dimension' => 'price', 'label' => 'Above budget']],
            tradeoffs: [['label' => 'Smaller lot']],
            missingData: [['field' => 'year_built']],
            confidence: ['level' => 'high', 'score' => 0.92],
            recommendations: [['label' => 'Widen price ~$25k']],
            generatedAt: '2026-07-06T18:45:16+00:00',
            narrative: $narrative,
        );
    }

    /** @test */
    public function it_constructs_and_round_trips_through_to_array(): void
    {
        $report = $this->report();

        $this->assertSame([
            'criteria_id'      => 7,
            'criteria_type'    => 'buyer',
            'listing_key'      => 'KEY-9',
            'source'           => 'bridge',
            'total_score'      => 88,
            'category_scores'  => ['location' => 24, 'price' => 20],
            'why_this_matches' => [['dimension' => 'location', 'label' => 'In your area']],
            'why_not'          => [['dimension' => 'price', 'label' => 'Above budget']],
            'tradeoffs'        => [['label' => 'Smaller lot']],
            'missing_data'     => [['field' => 'year_built']],
            'confidence'       => ['level' => 'high', 'score' => 0.92],
            'recommendations'  => [['label' => 'Widen price ~$25k']],
            'generated_at'     => '2026-07-06T18:45:16+00:00',
            'narrative'        => null,
        ], $report->toArray());
    }

    /** @test */
    public function narrative_defaults_to_null_and_is_carried_when_supplied(): void
    {
        $this->assertNull($this->report()->narrative);
        $this->assertNull($this->report()->toArray()['narrative']);

        $withNarrative = $this->report(['summary' => 'A strong match.']);
        $this->assertSame(['summary' => 'A strong match.'], $withNarrative->narrative);
        $this->assertSame(['summary' => 'A strong match.'], $withNarrative->toArray()['narrative']);
    }

    /** @test */
    public function confidence_may_be_null(): void
    {
        $report = new MatchReport(
            criteriaId: 1,
            criteriaType: 'tenant',
            listingKey: 'KEY-1',
            source: 'bridge',
            totalScore: 40,
            categoryScores: [],
            whyThisMatches: [],
            whyNot: [],
            tradeoffs: [],
            missingData: [],
            confidence: null,
            recommendations: [],
            generatedAt: '2026-07-06T00:00:00+00:00',
        );

        $this->assertNull($report->confidence);
        $this->assertNull($report->toArray()['confidence']);
    }

    /** @test */
    public function generated_at_is_exactly_the_injected_value_no_internal_now(): void
    {
        $stamp = '2020-01-02T03:04:05+00:00';
        $report = new MatchReport(
            criteriaId: 1,
            criteriaType: 'buyer',
            listingKey: 'K',
            source: 'bridge',
            totalScore: 0,
            categoryScores: [],
            whyThisMatches: [],
            whyNot: [],
            tradeoffs: [],
            missingData: [],
            confidence: null,
            recommendations: [],
            generatedAt: $stamp,
        );

        $this->assertSame($stamp, $report->generatedAt);
        $this->assertSame($stamp, $report->toArray()['generated_at']);
    }

    /** @test */
    public function to_array_is_json_serializable(): void
    {
        // F8 — the DTO must contain nothing non-serializable.
        $json = json_encode($this->report()->toArray());

        $this->assertNotFalse($json);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }
}
