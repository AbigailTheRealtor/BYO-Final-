<?php

namespace Tests\Unit\Spatial\Gate1;

use App\Services\Spatial\Gate1\Gate1Scenario;
use App\Services\Spatial\Gate1\Gate1ScenarioSet;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase 2 Batch 2D Part B — Option D scenario loader tests.
 *
 * Pins the fail-closed contract of the synthetic-benchmark loader and the shape adaptation that
 * keeps the evaluator-only labels away from the ranking engine. Touches no DB and no network.
 */
class Gate1ScenarioSetTest extends TestCase
{
    private const FIXTURE = 'tests/Fixtures/Spatial/Gate1/synthetic-gate1-scenarios.json';

    /** @return array<string, mixed> */
    private function candidate(array $overrides = []): array
    {
        return $overrides + [
            'name'       => 'Synthetic A',
            'lat'        => 27.95,
            'lng'        => -82.46,
            'types'      => ['supermarket'],
            'legitimate' => true,
        ];
    }

    /** @test */
    public function it_loads_the_shipped_synthetic_fixture(): void
    {
        $set = Gate1ScenarioSet::fromJsonFile(base_path(self::FIXTURE));

        $this->assertSame(7, $set->count());
        $this->assertContainsOnlyInstancesOf(Gate1Scenario::class, $set->all());
        $this->assertSame('grocery-clean-1', $set->all()[0]->key());
    }

    /** @test */
    public function it_fails_closed_on_an_empty_scenario_list(): void
    {
        // Erratum E-41: a harness that evaluates nothing must not be constructible and then
        // silently "pass".
        $this->expectException(InvalidArgumentException::class);
        Gate1ScenarioSet::fromArray(['scenarios' => []]);
    }

    /** @test */
    public function it_rejects_duplicate_scenario_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Gate1ScenarioSet::fromArray(['scenarios' => [
            ['key' => 'dup', 'category' => 'grocery_store', 'source_lat' => 27.9, 'source_lng' => -82.5, 'candidates' => [$this->candidate()]],
            ['key' => 'dup', 'category' => 'grocery_store', 'source_lat' => 27.9, 'source_lng' => -82.5, 'candidates' => [$this->candidate()]],
        ]]);
    }

    /** @test */
    public function it_rejects_duplicate_candidate_names_within_a_scenario(): void
    {
        // Names are the identity the harness matches ranked output against; duplicates would make
        // the legitimacy lookup ambiguous.
        $this->expectException(InvalidArgumentException::class);
        Gate1Scenario::fromArray([
            'key' => 's', 'category' => 'grocery_store', 'source_lat' => 27.9, 'source_lng' => -82.5,
            'candidates' => [
                $this->candidate(['name' => 'Same']),
                $this->candidate(['name' => 'Same']),
            ],
        ]);
    }

    /** @test */
    public function it_rejects_a_candidate_missing_a_required_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Gate1Scenario::fromArray([
            'key' => 's', 'category' => 'grocery_store', 'source_lat' => 27.9, 'source_lng' => -82.5,
            'candidates' => [['name' => 'X', 'lat' => 27.9, 'lng' => -82.5]], // no types / legitimate
        ]);
    }

    /** @test */
    public function raw_candidates_withhold_labels_and_include_ratings_only_when_present(): void
    {
        $scenario = Gate1Scenario::fromArray([
            'key' => 's', 'category' => 'grocery_store', 'source_lat' => 27.9, 'source_lng' => -82.5,
            'candidates' => [
                $this->candidate(['name' => 'Rated', 'rating' => 4.3, 'user_ratings_total' => 120, 'true_category' => 'grocery_store']),
                $this->candidate(['name' => 'Unrated']),
            ],
        ]);

        $raw = $scenario->rawCandidates();

        // Labels never reach the engine.
        foreach ($raw as $row) {
            $this->assertArrayNotHasKey('legitimate', $row);
            $this->assertArrayNotHasKey('true_category', $row);
            $this->assertSame(['lat', 'lng'], array_keys($row['geometry']['location']));
        }

        // Rating/reviews present only where supplied (mirrors a rating-free corpus row exactly).
        $this->assertArrayHasKey('rating', $raw[0]);
        $this->assertSame(4.3, $raw[0]['rating']);
        $this->assertSame(120, $raw[0]['user_ratings_total']);
        $this->assertArrayNotHasKey('rating', $raw[1]);
        $this->assertArrayNotHasKey('user_ratings_total', $raw[1]);

        // Legitimacy map is keyed by name.
        $this->assertSame(['Rated' => true, 'Unrated' => true], $scenario->legitimacyByName());
    }
}
