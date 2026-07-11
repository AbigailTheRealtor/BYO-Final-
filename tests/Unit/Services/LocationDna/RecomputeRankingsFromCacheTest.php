<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * RecomputeRankingsFromCacheTest — Phase 1, Deliverable 2. Production call site 1, end to end.
 *
 * WHY THIS EXISTS SEPARATELY FROM THE PASSTHROUGH HARNESS
 * ------------------------------------------------------
 * `RankingEnginePersistencePassthroughTest` proves the ENGINE returns `_row_id` for a
 * call-site-1-shaped input. It cannot prove that `recomputeRankingsFromCache()` — the real
 * service method — actually finds the row that `_row_id` points at and writes to it.
 *
 * The distinction matters because the failure is silent. The production loop is:
 *
 *     $row = $categoryRows->firstWhere('id', $rankedCandidate['_row_id'] ?? null);
 *     if ($row === null) { continue; }        // <-- lost _row_id lands HERE
 *
 * A dropped `_row_id` makes `firstWhere()` return null for EVERY candidate, every row is
 * skipped, the method returns 0, and the persisted scores silently go stale. No exception is
 * raised, no log line is written, and the ranking engine's own output is byte-perfect
 * throughout — the golden master cannot see it. Only an assertion that rows were genuinely
 * UPDATED can.
 *
 * This test exercises the real method against real persisted rows. It performs no network I/O:
 * `recomputeRankingsFromCache()` reads stored candidates only and never calls Google.
 */
class RecomputeRankingsFromCacheTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent';
    private const LISTING_ID   = 987654;

    private const SOURCE_LAT = 27.9000;
    private const SOURCE_LNG = -82.5000;

    /**
     * Persist one candidate row in the shape recomputeRankingsFromCache() reads back.
     *
     * Scores are seeded to deliberately WRONG sentinel values so that "the row was updated"
     * is a claim the test can actually verify, rather than one that passes by coincidence.
     */
    private function seedPoi(
        string $name,
        float $lat,
        array $types,
        float $rating,
        int $reviews,
        int $seedRank,
    ): PropertyLocationPoi {
        return PropertyLocationPoi::create([
            'listing_type'         => self::LISTING_TYPE,
            'listing_id'           => self::LISTING_ID,
            'poi_category'         => 'grocery_store',
            'poi_subtype'          => 'grocery_store',
            'poi_name'             => $name,
            'poi_address'          => '1 Test Way',
            'poi_lat'              => $lat,
            'poi_lng'              => self::SOURCE_LNG,
            'source_lat'           => self::SOURCE_LAT,
            'source_lng'           => self::SOURCE_LNG,
            'distance_miles'       => 0.5,
            'rating'               => $rating,
            'user_ratings_total'   => $reviews,
            'types_json'           => $types,
            'data_source'          => 'google_places',
            'status'               => 'found',
            'calculated_at'        => now(),

            // Sentinels: if the row is skipped, these survive untouched and the test fails.
            // Seed ranks must differ — the table carries a UNIQUE index on
            // (listing_type, listing_id, poi_category, rank).
            'rank'                 => $seedRank,
            'ranking_score'        => -1.0,
            'category_match_score' => -1.0,
            'pois_scoring_version' => 'STALE_VERSION',
        ]);
    }

    /** The two candidates are chosen so ranking REORDERS them: the farther one is far better. */
    private function seedContrastingPair(): array
    {
        // Near but mediocre — would win on distance alone. Seeded at rank 98 (i.e. "ahead"),
        // so a skipped rescore leaves the WRONG order in place and the test notices.
        $near = $this->seedPoi('Corner Mart', 27.9015, ['supermarket'], 3.4, 8, 98);

        // Farther but high quality — must win on the grocery_store profile.
        $far = $this->seedPoi('Publix Super Market', 27.9058, ['supermarket', 'grocery_or_supermarket'], 4.7, 600, 99);

        return [$near, $far];
    }

    private function service(): LocationDnaPoiDistanceService
    {
        return app(LocationDnaPoiDistanceService::class);
    }

    // =========================================================================
    // §1 — No row is silently skipped
    // =========================================================================

    /** @test */
    public function every_persisted_row_is_rescored_and_none_is_silently_skipped(): void
    {
        $this->seedContrastingPair();

        $updated = $this->service()->recomputeRankingsFromCache(self::LISTING_TYPE, self::LISTING_ID);

        // The count IS the skip detector: a lost _row_id makes firstWhere() miss every row,
        // the loop `continue`s past all of them, and this returns 0 without raising.
        $this->assertSame(
            2,
            $updated,
            'recomputeRankingsFromCache() did not update every row. A lost _row_id makes '
            . 'firstWhere() return null and the loop skips the row silently.',
        );
    }

    /** @test */
    public function the_ranking_fields_of_each_row_are_actually_written(): void
    {
        [$near, $far] = $this->seedContrastingPair();

        $this->service()->recomputeRankingsFromCache(self::LISTING_TYPE, self::LISTING_ID);

        foreach ([$near, $far] as $row) {
            $row->refresh();

            $this->assertNotSame(-1.0, $row->ranking_score,        "ranking_score was never written for {$row->poi_name}.");
            $this->assertNotSame(-1.0, $row->category_match_score, "category_match_score was never written for {$row->poi_name}.");
            $this->assertNotSame(98,   $row->rank,                 "rank was never written for {$row->poi_name}.");
            $this->assertNotSame(99,   $row->rank,                 "rank was never written for {$row->poi_name}.");
            $this->assertNotSame('STALE_VERSION', $row->pois_scoring_version, "scoring version was not re-stamped for {$row->poi_name}.");

            $this->assertNotEmpty($row->ranking_reasons_json, "ranking_reasons_json is empty for {$row->poi_name}.");
            $this->assertGreaterThan(0.0, $row->ranking_score);
        }
    }

    // =========================================================================
    // §2 — The scores land on the RIGHT row (_row_id stayed bound to its candidate)
    // =========================================================================

    /** @test */
    public function scores_are_written_to_the_row_they_belong_to_after_reordering(): void
    {
        // This is the failure worse than loss: a surviving but MIS-bound _row_id would write
        // one POI's scores onto another POI's row. Ranking reorders these two, so a positional
        // bug cannot hide.
        [$near, $far] = $this->seedContrastingPair();

        $this->service()->recomputeRankingsFromCache(self::LISTING_TYPE, self::LISTING_ID);

        $near->refresh();
        $far->refresh();

        $this->assertSame(1, $far->rank,  'The high-quality Publix must rank 1 under the grocery_store profile.');
        $this->assertSame(2, $near->rank, 'The nearby mediocre Corner Mart must rank 2.');

        $this->assertGreaterThan(
            $near->ranking_score,
            $far->ranking_score,
            'The better candidate must carry the higher score — if these are inverted, _row_id '
            . 'bound each score to the wrong row.',
        );

        // The rows kept their own identities: names were never overwritten by the merge.
        $this->assertSame('Publix Super Market', $far->poi_name);
        $this->assertSame('Corner Mart',         $near->poi_name);
    }

    // =========================================================================
    // §3 — Derived categories are still left intact (guards the untouched branch)
    // =========================================================================

    /** @test */
    public function derived_categories_are_left_untouched(): void
    {
        $this->seedContrastingPair();

        $derived = PropertyLocationPoi::create([
            'listing_type'         => self::LISTING_TYPE,
            'listing_id'           => self::LISTING_ID,
            'poi_category'         => 'top_rated_dining', // not in CATEGORIES — skipped by design
            'poi_name'             => 'Bern\'s Steak House',
            'poi_lat'              => 27.9058,
            'poi_lng'              => self::SOURCE_LNG,
            'source_lat'           => self::SOURCE_LAT,
            'source_lng'           => self::SOURCE_LNG,
            'rating'               => 4.8,
            'user_ratings_total'   => 800,
            'types_json'           => ['restaurant'],
            'data_source'          => 'google_places',
            'status'               => 'found',
            'calculated_at'        => now(),
            'rank'                 => 99,
            'ranking_score'        => -1.0,
            'pois_scoring_version' => 'STALE_VERSION',
        ]);

        $updated = $this->service()->recomputeRankingsFromCache(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertSame(2, $updated, 'Only the two standard-category rows should be rescored.');

        $derived->refresh();
        $this->assertSame(99, $derived->rank, 'Derived category rows must be left intact.');
        $this->assertSame(-1.0, $derived->ranking_score);
        $this->assertSame('STALE_VERSION', $derived->pois_scoring_version);
    }
}
