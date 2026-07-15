<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\PoiBaselineDiffHarness;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\LocationDna\FixtureNearbyPoiFetcher;
use Tests\TestCase;

/**
 * PoiBaselineDiffHarnessTest — Phase 1, Deliverable 6 (Batch 4).
 *
 * Exercises the dual-run / baseline-diff harness and pins its guarantees:
 *   - self-diff over the frozen corpus reports zero divergence,
 *   - the fetcher-driven dual-run path works,
 *   - membership and order divergences are actually detected,
 *   - the diff is rank-order + membership ONLY (no `ranking_score` anywhere — erratum E-48),
 *   - the harness persists nothing,
 *   - the frozen fixture digest is unmoved.
 *
 * It touches no network and mutates no fixture. The `LocationDnaRankingEngineGoldenMasterTest`
 * remains the independent tripwire that the engine and fixture are byte-frozen.
 */
class PoiBaselineDiffHarnessTest extends TestCase
{
    use DatabaseTransactions;

    private const FIXTURE = 'tests/Fixtures/LocationDna/ranking-golden-master.json';
    private const FROZEN_DIGEST = 'cfc0c05c9e172cc8af6dec55cdf3ee198a0fea6257fecdb247e4ec9f01753df9';

    private const SOURCE_LAT = 27.9506;
    private const SOURCE_LNG = -82.4572;

    private function harness(): PoiBaselineDiffHarness
    {
        return new PoiBaselineDiffHarness();
    }

    /** @return array<int, array> The golden-master fixture groups. */
    private function fixtureGroups(): array
    {
        $decoded = json_decode(file_get_contents(base_path(self::FIXTURE)), true);
        $this->assertIsArray($decoded, 'The golden-master fixture is not valid JSON.');

        return $decoded['groups'];
    }

    /** One raw, Google-shaped candidate row (the shape the ranking engine consumes). */
    private function rawRow(
        string $name,
        float $lat,
        float $lng = self::SOURCE_LNG,
        float $rating = 4.5,
        int $reviews = 100,
        array $types = ['restaurant'],
    ): array {
        return [
            'name'               => $name,
            'geometry'           => ['location' => ['lat' => $lat, 'lng' => $lng]],
            'types'              => $types,
            'rating'             => $rating,
            'user_ratings_total' => $reviews,
        ];
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

    // =========================================================================
    // 5. Self-diff proof
    // =========================================================================

    /** @test */
    public function self_diff_over_frozen_corpus_reports_zero_divergence(): void
    {
        $groups = $this->fixtureGroups();
        $this->assertSame(103, count($groups), 'Expected the frozen 103-group corpus.');

        $report = $this->harness()->diffCorpus($groups);

        $this->assertSame(103, $report['total_groups']);
        $this->assertSame(
            0,
            $report['divergent_groups'],
            'Self-diff must be empty: live ranking of the frozen candidates must reproduce the '
            . 'frozen baseline order for every group. Divergent: ' . implode(', ', $report['divergent_keys']),
        );
        $this->assertSame(103, $report['identical_groups']);

        foreach ($report['groups'] as $group) {
            $this->assertTrue($group['identical'], "Group {$group['key']} diverged from the frozen baseline.");
            $this->assertSame([], $group['membership']['only_in_baseline']);
            $this->assertSame([], $group['membership']['only_in_candidate']);
            $this->assertTrue($group['order']['identical']);
        }
    }

    // =========================================================================
    // 6. Fetcher-double (dual-run driver) proof
    // =========================================================================

    /** @test */
    public function dual_run_via_fetcher_double_reports_zero_divergence(): void
    {
        // Drive the adapter-facing seam (`diffFetchers`) with a fixture-backed
        // NearbyPoiFetcherInterface on BOTH sides — the Gate-3 shape, self-diffed.
        foreach ($this->fixtureGroups() as $group) {
            $fetcher = new FixtureNearbyPoiFetcher($group['candidates']);

            $diff = $this->harness()->diffFetchers(
                (string) $group['category'],
                $fetcher,
                $fetcher,
                ['google_type' => null, 'keyword' => null],
                (float) $group['source_lat'],
                (float) $group['source_lng'],
            );

            $this->assertTrue($diff['identical'], "diffFetchers diverged for {$group['key']}.");
            $this->assertSame([], $diff['membership']['only_in_baseline']);
            $this->assertSame([], $diff['membership']['only_in_candidate']);
            $this->assertTrue($diff['order']['identical']);
        }
    }

    // =========================================================================
    // Membership divergence detection
    // =========================================================================

    /** @test */
    public function membership_difference_is_detected(): void
    {
        // Candidate side drops 'Bravo'. Membership diff is set-based, so it is detected
        // regardless of how the engine reorders the survivors.
        $baselineRaw = [
            $this->rawRow('Alpha',  27.9515),
            $this->rawRow('Bravo',  27.9600),
            $this->rawRow('Charlie', 27.9700),
        ];
        $candidateRaw = [
            $this->rawRow('Alpha',  27.9515),
            $this->rawRow('Charlie', 27.9700),
        ];

        $diff = $this->harness()->diffRanked('restaurant', $baselineRaw, $candidateRaw, self::SOURCE_LAT, self::SOURCE_LNG);

        $this->assertFalse($diff['identical']);
        $this->assertSame(['Bravo'], $diff['membership']['only_in_baseline']);
        $this->assertSame([], $diff['membership']['only_in_candidate']);
        $this->assertSame(2, $diff['membership']['common']);
    }

    // =========================================================================
    // Order divergence detection
    // =========================================================================

    /** @test */
    public function order_difference_is_detected(): void
    {
        // Same membership, but the candidate side ranks by distance to [Near, Mid, Far]
        // (identical rating/reviews/types → distance is the only differentiator), while the
        // baseline order is the reverse. Membership matches; order must diverge.
        $candidateRaw = [
            $this->rawRow('Near', 27.9510),
            $this->rawRow('Mid',  27.9600),
            $this->rawRow('Far',  27.9800),
        ];

        $diff = $this->harness()->diffAgainstBaselineOrder(
            'restaurant',
            $candidateRaw,
            ['Far', 'Mid', 'Near'], // baseline order = reverse of the distance ranking
            self::SOURCE_LAT,
            self::SOURCE_LNG,
        );

        $this->assertFalse($diff['identical']);
        $this->assertSame([], $diff['membership']['only_in_baseline']);
        $this->assertSame([], $diff['membership']['only_in_candidate']);
        $this->assertSame(3, $diff['membership']['common']);
        $this->assertFalse($diff['order']['identical']);
        $this->assertNotEmpty($diff['order']['disagreements']);
        // Position 1: baseline 'Far' vs candidate 'Near' (nearest ranks first).
        $this->assertSame(['position' => 1, 'baseline' => 'Far', 'candidate' => 'Near'], $diff['order']['disagreements'][0]);
    }

    // =========================================================================
    // 9. E-48 compliance proof — no score anywhere in the diff
    // =========================================================================

    /** @test */
    public function diff_structure_contains_no_ranking_score_or_any_score_key(): void
    {
        $report = $this->harness()->diffCorpus($this->fixtureGroups());

        $keys = [];
        $this->collectKeys($report, $keys);
        $keys = array_unique($keys);

        $this->assertNotContains('ranking_score', $keys, 'The diff must never surface ranking_score (E-48).');
        foreach ($keys as $key) {
            $this->assertStringNotContainsStringIgnoringCase(
                'score',
                $key,
                "The diff structure must carry no score-bearing key; found '{$key}'.",
            );
        }
    }

    // =========================================================================
    // 8. No-persistence proof
    // =========================================================================

    /** @test */
    public function harness_performs_no_persistence(): void
    {
        $before = PropertyLocationPoi::count();

        $this->harness()->diffCorpus($this->fixtureGroups());

        $after = PropertyLocationPoi::count();

        $this->assertSame($before, $after, 'The harness must write nothing to property_location_pois.');
        $this->assertSame(0, $after, 'No POI rows should exist — the harness is pure and seeds nothing.');
    }

    // =========================================================================
    // 7. Frozen-fixture proof
    // =========================================================================

    /** @test */
    public function the_frozen_fixture_digest_is_unmoved(): void
    {
        $decoded = json_decode(file_get_contents(base_path(self::FIXTURE)), true);

        $this->assertSame(
            self::FROZEN_DIGEST,
            $decoded['_meta']['golden_hash_sha256'],
            'The frozen baseline digest changed — the harness must never touch the fixture.',
        );
    }
}
