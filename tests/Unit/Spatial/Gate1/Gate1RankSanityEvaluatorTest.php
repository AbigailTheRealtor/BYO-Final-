<?php

namespace Tests\Unit\Spatial\Gate1;

use App\Services\Spatial\Gate1\Gate1RankSanityEvaluator;
use App\Services\Spatial\Gate1\Gate1ScenarioSet;
use Tests\TestCase;

/**
 * Phase 2 Batch 2D Part B — Option E runtime validation framework tests.
 *
 * Proves the evaluator (a) passes the license-clean synthetic benchmark, (b) actually DETECTS an
 * embarrassment when one is planted — so a green run means something — and (c) reads only rank
 * order and identity, never a score (erratum E-48). Touches no DB, no network, no corpus.
 */
class Gate1RankSanityEvaluatorTest extends TestCase
{
    private const FIXTURE = 'tests/Fixtures/Spatial/Gate1/synthetic-gate1-scenarios.json';

    /** @test */
    public function the_synthetic_benchmark_passes_with_zero_embarrassments(): void
    {
        $set    = Gate1ScenarioSet::fromJsonFile(base_path(self::FIXTURE));
        $report = (new Gate1RankSanityEvaluator(null, 3, 0.03))->evaluate($set);

        $this->assertTrue($report->passed(), 'The synthetic benchmark must pass the ≤3% gate.');
        $this->assertSame(0, $report->embarrassmentCount());
        $this->assertSame(0.0, $report->rate());
        $this->assertSame(21, $report->evaluatedSlots(), '7 scenarios × top-3 = 21 evaluated slots.');
        $this->assertSame([], $report->offenders());

        // Every category contributes exactly one 3-slot pair, none embarrassing.
        foreach ($report->perCategory() as $category => $stats) {
            $this->assertSame(3, $stats['slots'], "Category {$category} should contribute 3 slots.");
            $this->assertSame(0, $stats['embarrassments']);
        }
    }

    /** @test */
    public function a_planted_embarrassment_is_detected(): void
    {
        // A green harness is only meaningful if a red one is reachable. Distance-dominated
        // transit ranking (distance_weight 0.65) lets a very-close PARKING lot outrank far
        // legitimate stops — exactly the tail case a real corpus needs exclusion rules for. Here
        // it proves the harness catches an illegitimate POI in the top-N window.
        $set = Gate1ScenarioSet::fromArray(['scenarios' => [[
            'key'        => 'transit-trap',
            'category'   => 'transit_station',
            'source_lat' => 28.0000,
            'source_lng' => -82.5000,
            'candidates' => [
                ['name' => 'Synthetic Parking Lot', 'lat' => 28.0010, 'lng' => -82.5000, 'types' => ['parking'], 'legitimate' => false, 'true_category' => 'parking'],
                ['name' => 'Synthetic Bus Stop A',  'lat' => 28.0300, 'lng' => -82.5000, 'types' => ['bus_station', 'transit_station'], 'legitimate' => true],
                ['name' => 'Synthetic Bus Stop B',  'lat' => 28.0310, 'lng' => -82.5000, 'types' => ['transit_station'], 'legitimate' => true],
            ],
        ]]]);

        $report = (new Gate1RankSanityEvaluator(null, 3, 0.03))->evaluate($set);

        $this->assertFalse($report->passed(), 'A top-ranked parking lot must fail the gate.');
        $this->assertSame(1, $report->embarrassmentCount());
        $this->assertGreaterThan(0.03, $report->rate());

        $offenders = $report->offenders();
        $this->assertCount(1, $offenders);
        $this->assertSame('Synthetic Parking Lot', $offenders[0]['name']);
        $this->assertSame(1, $offenders[0]['rank'], 'The parking lot should rank first (distance dominates transit).');
        $this->assertSame('transit_station', $offenders[0]['category']);
    }

    /** @test */
    public function the_report_carries_no_score_bearing_key(): void
    {
        // E-48: the harness compares membership + rank position, never ranking_score.
        $set    = Gate1ScenarioSet::fromJsonFile(base_path(self::FIXTURE));
        $report = (new Gate1RankSanityEvaluator(null, 3, 0.03))->evaluate($set);

        $keys = [];
        $this->collectKeys($report->toArray(), $keys);

        foreach (array_unique($keys) as $key) {
            $this->assertStringNotContainsStringIgnoringCase(
                'score',
                (string) $key,
                "The Gate 1 report must carry no score-bearing key; found '{$key}'.",
            );
        }
    }

    /** @test */
    public function the_top_n_window_is_honoured(): void
    {
        // With top-N = 1 only the single top rank per scenario is inspected: 7 scenarios → 7 slots.
        $set    = Gate1ScenarioSet::fromJsonFile(base_path(self::FIXTURE));
        $report = (new Gate1RankSanityEvaluator(null, 1, 0.03))->evaluate($set);

        $this->assertSame(7, $report->evaluatedSlots());
        $this->assertSame(1, $report->topN());
        $this->assertTrue($report->passed());
    }

    /** Recursively collect every array key in a nested structure. */
    private function collectKeys(array $data, array &$keys): void
    {
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }
            if (is_array($value)) {
                $this->collectKeys($value, $keys);
            }
        }
    }
}
