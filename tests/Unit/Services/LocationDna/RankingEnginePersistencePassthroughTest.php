<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationDnaRankingEngine;
use App\Services\LocationDna\PoiCandidate;
use Tests\TestCase;

/**
 * RankingEnginePersistencePassthroughTest — Phase 1, Deliverable 2.
 *
 * WHY THIS EXISTS
 * ---------------
 * `PoiCandidateGoldenMasterParityTest` proved the five scoring accessors are sufficient to
 * reproduce every frozen ranking row — that is sufficiency for SCORING. It said so, and it
 * said explicitly what it did *not* prove:
 *
 *   > "Sufficiency for SCORING is not sufficiency for PERSISTENCE. That is a separate batch
 *   >  with its own harness."
 *
 * This is that harness.
 *
 * THE RISK IT GUARDS
 * ------------------
 * `rankCandidates()` returns `array_merge($place, ['_ranking' => ...])` — the ENTIRE input
 * array flows through, and all three production call sites read non-scoring keys back off
 * that merged output:
 *
 *   1. rescoreFromPersistedRows  — reads `_row_id` to re-associate a scored result with its
 *                                  database row. A lost `_row_id` means `firstWhere()` returns
 *                                  null, the row is skipped, and the POI is silently NOT
 *                                  rescored. No exception. No log. Just stale scores.
 *   2. nearby-search persistence — reads `vicinity`, `name`, `geometry`, `rating`,
 *                                  `user_ratings_total`, `types`. A lost `vicinity` persists a
 *                                  NULL address against a real POI.
 *   3. top_rated_dining          — same reads as (2).
 *
 * `PoiCandidate::toRankingArray()` emits ONLY the five scoring keys. So the naive flip — adapt
 * to `PoiCandidate`, project back through `toRankingArray()`, hand that to the engine — is
 * silently destructive. `the_naive_projection_would_destroy_persistence_fields()` below pins
 * that down as an executable fact rather than a comment.
 *
 * The engine's contract, which every test here asserts, is therefore:
 *
 *   OUTPUT ⊇ INPUT. Every key handed in comes back out, byte-identical, plus `_ranking`.
 *
 * These tests are written against the POST-flip API (the engine consuming `PoiCandidate`).
 * Before the flip they fail; that failure is the evidence the harness is real.
 */
class RankingEnginePersistencePassthroughTest extends TestCase
{
    private const SOURCE_LAT = 27.9506;
    private const SOURCE_LNG = -82.4572;

    private function engine(): LocationDnaRankingEngine
    {
        return new LocationDnaRankingEngine();
    }

    /**
     * The exact candidate shape built by `rescoreFromPersistedRows()` — Google-shaped, plus
     * the `_row_id` the persistence layer depends on.
     */
    private function rescoreShapedCandidate(int $rowId, string $name, float $lat, float $lng): array
    {
        return [
            'geometry'           => ['location' => ['lat' => $lat, 'lng' => $lng]],
            'types'              => ['grocery_or_supermarket', 'store'],
            'rating'             => 4.4,
            'user_ratings_total' => 812,
            'name'               => $name,
            '_row_id'            => $rowId,
        ];
    }

    /**
     * The shape a live Nearby Search result actually has: the five scoring keys plus the
     * non-scoring keys the persistence layer reads (`vicinity`) and carries (`place_id`).
     */
    private function nearbyShapedCandidate(string $name, float $lat, float $lng, string $vicinity): array
    {
        return [
            'place_id'           => 'ChIJ_' . $name,
            'name'               => $name,
            'vicinity'           => $vicinity,
            'geometry'           => ['location' => ['lat' => $lat, 'lng' => $lng]],
            'types'              => ['restaurant', 'food', 'point_of_interest'],
            'rating'             => 4.7,
            'user_ratings_total' => 1503,
            'business_status'    => 'OPERATIONAL',
        ];
    }

    // =========================================================================
    // §1 — The general contract: output ⊇ input
    // =========================================================================

    /** @test */
    public function every_input_key_survives_ranking_byte_identically(): void
    {
        $source = $this->nearbyShapedCandidate('Bern\'s Steak House', 27.9420, -82.4650, '1208 S Howard Ave');

        $ranked = $this->engine()->rankCandidates(
            'restaurant',
            PoiCandidate::fromGooglePlaces([$source]),
            self::SOURCE_LAT,
            self::SOURCE_LNG,
        );

        $this->assertCount(1, $ranked);

        foreach ($source as $key => $value) {
            $this->assertArrayHasKey($key, $ranked[0], "Ranking dropped the input key '{$key}'.");
            $this->assertSame($value, $ranked[0][$key], "Ranking mutated the input key '{$key}'.");
        }

        $this->assertArrayHasKey('_ranking', $ranked[0], 'The engine must attach its scores.');
    }

    /** @test */
    public function the_ranking_block_carries_exactly_the_five_persisted_score_fields(): void
    {
        $ranked = $this->engine()->rankCandidates(
            'restaurant',
            PoiCandidate::fromGooglePlaces([$this->nearbyShapedCandidate('A', 27.95, -82.46, 'x')]),
            self::SOURCE_LAT,
            self::SOURCE_LNG,
        );

        $this->assertSame(
            [
                'category_match_score',
                'review_confidence_score',
                'consumer_relevance_score',
                'ranking_score',
                'ranking_reasons_json',
            ],
            array_keys($ranked[0]['_ranking']),
        );
    }

    // =========================================================================
    // §2 — Call site 1: rescoreFromPersistedRows() and `_row_id`
    // =========================================================================

    /** @test */
    public function call_site_1_row_id_survives_and_still_re_associates_every_row(): void
    {
        $candidates = [
            $this->rescoreShapedCandidate(101, 'Publix',    27.9600, -82.4600),
            $this->rescoreShapedCandidate(102, 'Winn-Dixie', 27.9900, -82.5000),
            $this->rescoreShapedCandidate(103, 'Aldi',       28.0400, -82.5600),
        ];

        $ranked = $this->engine()->rankCandidates(
            'grocery_store',
            PoiCandidate::fromGooglePlaces($candidates),
            self::SOURCE_LAT,
            self::SOURCE_LNG,
        );

        $this->assertCount(3, $ranked);

        // Reproduce the production lookup verbatim: $categoryRows->firstWhere('id', $c['_row_id']).
        // Ranking REORDERS, so a positional assumption would be wrong — the id must ride along.
        $recovered = [];
        foreach ($ranked as $candidate) {
            $this->assertArrayHasKey(
                '_row_id',
                $candidate,
                'Lost _row_id: rescoring would silently skip this row and leave stale scores.',
            );
            $recovered[] = $candidate['_row_id'];
        }

        sort($recovered);
        $this->assertSame([101, 102, 103], $recovered, 'Every persisted row must be recoverable after ranking.');
    }

    /** @test */
    public function call_site_1_row_id_stays_bound_to_its_own_candidate_after_reordering(): void
    {
        // The failure this catches is worse than loss: a _row_id that survives but attaches to
        // the WRONG candidate would write one POI's scores onto another POI's row.
        $candidates = [
            $this->rescoreShapedCandidate(101, 'Far',  28.2000, -82.8000), // farthest -> ranks last
            $this->rescoreShapedCandidate(102, 'Near', 27.9510, -82.4575), // nearest  -> ranks first
        ];

        $ranked = $this->engine()->rankCandidates(
            'grocery_store',
            PoiCandidate::fromGooglePlaces($candidates),
            self::SOURCE_LAT,
            self::SOURCE_LNG,
        );

        foreach ($ranked as $candidate) {
            $expectedId = $candidate['name'] === 'Near' ? 102 : 101;
            $this->assertSame(
                $expectedId,
                $candidate['_row_id'],
                "_row_id {$candidate['_row_id']} is attached to the wrong candidate ({$candidate['name']}).",
            );
        }
    }

    // =========================================================================
    // §3 — Call sites 2 & 3: every field the persistence layer reads
    // =========================================================================

    /**
     * @test
     * @dataProvider persistenceCallSites
     */
    public function persistence_call_sites_can_read_every_field_they_write(string $category): void
    {
        $source = $this->nearbyShapedCandidate('Ulele', 27.9650, -82.4640, '1810 N Highland Ave');

        $ranked = $this->engine()->rankCandidates(
            $category,
            PoiCandidate::fromGooglePlaces([$source]),
            self::SOURCE_LAT,
            self::SOURCE_LNG,
        );

        $place = $ranked[0];

        // These are the exact reads createPoiRow() is fed at both call sites.
        $this->assertSame('Ulele',               $place['name']                            ?? null);
        $this->assertSame('1810 N Highland Ave', $place['vicinity']                        ?? null, 'A lost vicinity persists a NULL address.');
        $this->assertSame(27.9650,               $place['geometry']['location']['lat']     ?? null);
        $this->assertSame(-82.4640,              $place['geometry']['location']['lng']     ?? null);
        $this->assertSame(4.7,                   $place['rating']                          ?? null);
        $this->assertSame(1503,                  $place['user_ratings_total']              ?? null);
        $this->assertSame(['restaurant', 'food', 'point_of_interest'], $place['types']     ?? null);
        $this->assertNotEmpty($place['_ranking']['ranking_reasons_json']);
    }

    public static function persistenceCallSites(): array
    {
        return [
            'call site 2 — nearby search persistence' => ['restaurant'],
            'call site 3 — top_rated_dining'          => ['top_rated_dining'],
        ];
    }

    /** @test */
    public function the_hospital_reorder_still_sees_the_types_it_filters_on(): void
    {
        // prioritizeLegitimateHospitalCandidates() runs on the engine's OUTPUT and reads
        // $place['types']. If types did not survive ranking, the allowlist would match nothing
        // and a med-spa could outrank a real hospital.
        $hospital = $this->nearbyShapedCandidate('General Hospital', 27.9600, -82.4600, '1 Care Way');
        $hospital['types'] = ['hospital', 'health', 'point_of_interest'];

        $ranked = $this->engine()->rankCandidates(
            'hospital',
            PoiCandidate::fromGooglePlaces([$hospital]),
            self::SOURCE_LAT,
            self::SOURCE_LNG,
        );

        $this->assertContains('hospital', $ranked[0]['types'] ?? []);
    }

    // =========================================================================
    // §4 — The trap, pinned down as an executable fact
    // =========================================================================

    /** @test */
    public function the_naive_projection_would_destroy_persistence_fields(): void
    {
        // This is the flip that LOOKS right and is not: adapt to PoiCandidate, project back
        // through toRankingArray(), hand that to the engine. Scoring is unaffected — the parity
        // harness proved that — but _row_id and vicinity are gone, and nothing raises.
        $source = $this->rescoreShapedCandidate(101, 'Publix', 27.9600, -82.4600);
        $source['vicinity'] = '123 Main St';

        $projected = PoiCandidate::fromGooglePlace($source)->toRankingArray();

        $this->assertArrayNotHasKey('_row_id',  $projected, 'toRankingArray() is expected to drop _row_id.');
        $this->assertArrayNotHasKey('vicinity', $projected, 'toRankingArray() is expected to drop vicinity.');

        // raw() is the passthrough that keeps them. This is what the engine must merge.
        $raw = PoiCandidate::fromGooglePlace($source)->raw();

        $this->assertSame(101,           $raw['_row_id']);
        $this->assertSame('123 Main St', $raw['vicinity']);
        $this->assertSame($source,       $raw, 'raw() must be the untouched provider payload.');
    }
}
