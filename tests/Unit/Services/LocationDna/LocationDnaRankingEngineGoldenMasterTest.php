<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationDnaRankingEngine;
use App\Services\LocationDna\PoiCandidate;
use Tests\TestCase;

/**
 * Phase 1 — golden-master parity harness for LocationDnaRankingEngine.
 *
 * WHAT THIS PROTECTS
 * ------------------
 * Phase 1 routes the production POI path through `PoiLookupAdapterInterface` and
 * extracts a `PoiCandidate` value object, which means refactoring
 * `LocationDnaPoiDistanceService` — **1,584 LOC, launch-critical** — and stripping the
 * raw-Google-JSON reads (`geometry`, `types`, `rating`, `user_ratings_total`) out of
 * the ranking engine. That is the single dangerous change in the phase.
 *
 * This harness turns "review it carefully and hope" into "byte-identical or the build
 * fails". It freezes the engine's INPUTS and the outputs it produces TODAY. Any change
 * to scoring, rounding, sort order, or reason text fails immediately, on the exact
 * group that changed.
 *
 * WHAT IT DOES NOT DO — read this before trusting it
 * --------------------------------------------------
 * It asserts the engine is **deterministic under refactor**. It does NOT reproduce the
 * values persisted historically in `property_location_pois`, and it must not be read as
 * doing so. 15 of the 995 scored rows — every one of them `top_rated_dining` — do not
 * match their persisted score, for a structural reason recorded as erratum E-48:
 *
 *   `ranking_score` is **set-relative.** `rankCandidates()` normalises the distance
 *   component by `max($distances)` across the candidate set it is given. Persistence
 *   keeps the top 10 of most categories but only the top 3 of `top_rated_dining`, so
 *   re-scoring the persisted rows re-normalises against a truncated set and shifts the
 *   distance score. The historical value was computed over the full fetched set, which
 *   was never persisted and cannot be recovered.
 *
 * The consequence for Phase 1 is larger than this test: a replacement provider that
 * returns a different candidate set will change `ranking_score` **even for POIs that
 * are byte-identical**, because the score is a property of the set, not of the POI.
 * The baseline-diff harness (Gate 3) must compare rank ORDER and membership, not raw
 * scores, or it will report differences that are arithmetic rather than semantic.
 *
 * REGENERATING THE FIXTURE
 * ------------------------
 * The fixture is derived from `property_location_pois WHERE ranking_score IS NOT NULL`,
 * grouped by (listing_type, listing_id, poi_category), candidates ordered by
 * (rank, id), groups sorted by key. Regenerate ONLY when the engine's behaviour is
 * intentionally changed, and say so in the commit message — a regenerated fixture is a
 * silenced alarm, not a passing test.
 *
 * The fixture is self-contained: this test touches no database and no network, so it
 * runs anywhere, including a CI box with no PostGIS and no credentials.
 */
class LocationDnaRankingEngineGoldenMasterTest extends TestCase
{
    private const FIXTURE = 'tests/Fixtures/LocationDna/ranking-golden-master.json';

    /** json_encode flags used to produce the frozen hash. Changing these invalidates it. */
    private const CANONICAL_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $path = base_path(self::FIXTURE);
        $this->assertFileExists($path, 'The golden-master fixture is missing.');

        $decoded = json_decode(file_get_contents($path), true);
        $this->assertIsArray($decoded, 'The golden-master fixture is not valid JSON.');

        $this->fixture = $decoded;
    }

    private function engine(): LocationDnaRankingEngine
    {
        return new LocationDnaRankingEngine();
    }

    /** Re-shape one engine result exactly as the fixture stores it. Key order is load-bearing. */
    private function shape(array $ranked): array
    {
        $rows = [];

        foreach ($ranked as $index => $candidate) {
            $rows[] = ['rank' => $index + 1, 'name' => $candidate['name']] + $candidate['_ranking'];
        }

        return $rows;
    }

    /** @test */
    public function the_fixture_covers_the_corpus_it_claims_to_cover(): void
    {
        // Fail closed. A truncated or empty fixture would make every assertion below
        // pass over nothing and report a safety it never verified (erratum E-41).
        $meta   = $this->fixture['_meta'];
        $groups = $this->fixture['groups'];

        $this->assertCount($meta['groups'], $groups, 'Fixture group count disagrees with its own metadata.');
        $this->assertSame(103, count($groups), 'Expected 103 (listing x category) groups.');

        $rows = array_sum(array_map(fn ($g) => count($g['expected']), $groups));
        $this->assertSame(995, $rows, 'Expected 995 scored POI rows.');
        $this->assertSame($meta['scored_rows'], $rows);

        $this->assertSame(20, count(array_unique(array_column($groups, 'category'))));

        // SIX listings, not thirteen. `property_location_pois` holds 1,090 rows across 13
        // listings, but only 995 rows across 6 listings carry a ranking_score. The other
        // 95 rows — 8 listings, one row per category, every score column NULL — were
        // written by a path that never ran the ranking engine, so there is nothing for a
        // golden master to freeze. Coverage of the SCORED corpus is 995/995 = 100%;
        // coverage of the table is 995/1,090. State both, conflate neither.
        $this->assertSame(6, count(array_unique(array_map(
            fn ($g) => $g['listing_type'] . '|' . $g['listing_id'],
            $groups,
        ))));
        $this->assertSame(95, $meta['unscored_rows_excluded']);
        $this->assertSame(1090, $meta['source_rows_total']);

        // Every group must carry as many expected rows as it has candidates.
        foreach ($groups as $group) {
            $this->assertSame(
                count($group['candidates']),
                count($group['expected']),
                "Group {$group['key']} has a candidate/expected length mismatch.",
            );
        }
    }

    /** @test */
    public function every_group_reproduces_its_frozen_output_byte_for_byte(): void
    {
        $engine     = $this->engine();
        $divergent  = [];

        foreach ($this->fixture['groups'] as $group) {
            $actual = $this->shape($engine->rankCandidates(
                $group['category'],
                PoiCandidate::fromGooglePlaces($group['candidates']),
                $group['source_lat'],
                $group['source_lng'],
            ));

            $actualJson   = json_encode($actual, self::CANONICAL_FLAGS);
            $expectedJson = json_encode($group['expected'], self::CANONICAL_FLAGS);

            if ($actualJson !== $expectedJson) {
                $divergent[] = $group['key'];
            }
        }

        $this->assertSame(
            [],
            $divergent,
            "The ranking engine no longer reproduces its frozen output for:\n  "
            . implode("\n  ", $divergent)
            . "\n\nIf this change is intentional, regenerate the fixture and say so explicitly. "
            . 'If it is not, a provider refactor has altered scoring.',
        );
    }

    /** @test */
    public function the_whole_corpus_hashes_to_the_frozen_digest(): void
    {
        // One assertion covering all 995 rows at once: cheap to run, impossible to
        // satisfy by accident, and the thing CI actually reports.
        $engine = $this->engine();
        $all    = [];

        foreach ($this->fixture['groups'] as $group) {
            $all[] = $this->shape($engine->rankCandidates(
                $group['category'],
                PoiCandidate::fromGooglePlaces($group['candidates']),
                $group['source_lat'],
                $group['source_lng'],
            ));
        }

        $this->assertSame(
            $this->fixture['_meta']['golden_hash_sha256'],
            hash('sha256', json_encode($all, self::CANONICAL_FLAGS)),
            'The golden-master digest changed. Some scoring behaviour is not what it was.',
        );
    }

    /** @test */
    public function rank_order_is_strictly_descending_by_ranking_score(): void
    {
        // The engine's contract: rank 1 is the highest scoring. Six production consumers
        // read property_location_pois ordered by `rank` (erratum E-1), so an inverted
        // sort would silently reorder the agent panel rather than fail loudly.
        foreach ($this->fixture['groups'] as $group) {
            $scores = array_column($group['expected'], 'ranking_score');
            $sorted = $scores;
            rsort($sorted);

            $this->assertSame($sorted, $scores, "Group {$group['key']} is not ordered by ranking_score desc.");
            $this->assertSame(range(1, count($scores)), array_column($group['expected'], 'rank'));
        }
    }

    /** @test */
    public function ranking_score_is_set_relative_and_therefore_changes_when_the_candidate_set_is_truncated(): void
    {
        // Erratum E-48, pinned as an executable fact rather than a comment.
        //
        // This is WHY the golden master freezes whole candidate sets, and why Gate 3's
        // baseline-diff must compare rank order and membership rather than raw scores:
        // swap in a provider that returns a different set and identical POIs score
        // differently, because distance is normalised by the set's own maximum.
        $engine = $this->engine();

        $group = collect($this->fixture['groups'])
            ->first(fn ($g) => count($g['candidates']) >= 4);

        $this->assertNotNull($group, 'Expected at least one group with 4+ candidates.');

        $full      = $engine->rankCandidates($group['category'], PoiCandidate::fromGooglePlaces($group['candidates']), $group['source_lat'], $group['source_lng']);
        $truncated = $engine->rankCandidates($group['category'], PoiCandidate::fromGooglePlaces(array_slice($group['candidates'], 0, 3)), $group['source_lat'], $group['source_lng']);

        $fullByName = [];
        foreach ($full as $candidate) {
            $fullByName[$candidate['name']] = $candidate['_ranking']['ranking_score'];
        }

        $changed = 0;
        foreach ($truncated as $candidate) {
            if ($fullByName[$candidate['name']] !== $candidate['_ranking']['ranking_score']) {
                $changed++;
            }
        }

        $this->assertGreaterThan(
            0,
            $changed,
            'Truncating the candidate set did not change any score. If the engine has been made '
            . 'set-independent, that is a real improvement — update E-48 and delete this test.',
        );
    }

    /** @test */
    public function the_engine_remains_pure_computation(): void
    {
        // The harness is only trustworthy while the engine is deterministic and offline.
        // The moment it reads a DB row or an env var, a passing golden master proves nothing.
        $source = file_get_contents(app_path('Services/LocationDna/LocationDnaRankingEngine.php'));
        $source = preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $source);

        foreach (['DB::', 'Http::', 'Cache::', 'env(', 'config(', 'new Client', 'file_get_contents'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "LocationDnaRankingEngine performs I/O ({$forbidden}). The golden master no longer proves determinism.",
            );
        }
    }
}
