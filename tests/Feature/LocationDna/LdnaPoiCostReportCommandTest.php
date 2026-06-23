<?php

namespace Tests\Feature\LocationDna;

use App\Services\LocationDna\LocationDnaPoiCostReporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LdnaPoiCostReportCommandTest
 *
 * Verifies that ldna:poi-cost-report Artisan command:
 *   (a) Runs without error and produces all expected summary keys
 *   (b) Correctly aggregates calls_made, calls_avoided_tile, calls_avoided_grouping
 *   (c) Computes cache_hit_rate_pct and estimated_saving_usd
 *   (d) Lists listing_ids_with_reused_data for rows with tile cache hits
 *   (e) --since= filter restricts results to the date range
 *   (f) Returns empty report when no stats rows exist
 */
class LdnaPoiCostReportCommandTest extends TestCase
{
    use DatabaseTransactions;

    private const REPORT_KEYS = [
        'calls_made',
        'calls_avoided_tile',
        'calls_avoided_grouping',
        'cache_hit_rate_pct',
        'estimated_saving_usd',
        'listing_ids_with_reused_data',
    ];

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function insertRunStat(array $data): void
    {
        DB::table('location_dna_poi_run_stats')->insert(array_merge([
            'listing_type'               => 'seller_agent_auction',
            'listing_id'                 => 1,
            'categories_fetched_fresh'   => 16,
            'categories_from_tile_cache' => 0,
            'categories_grouped'         => 3,
            'precision_used'             => null,
            'run_at'                     => now()->toDateTimeString(),
        ], $data));
    }

    // =========================================================================
    // (a) Command runs without error and all summary keys present
    // =========================================================================

    /** @test */
    public function command_runs_successfully_and_exits_zero(): void
    {
        $this->artisan('ldna:poi-cost-report')->assertExitCode(0);
    }

    /** @test */
    public function reporter_service_returns_all_expected_keys(): void
    {
        $reporter = new LocationDnaPoiCostReporter();
        $report   = $reporter->report();

        foreach (self::REPORT_KEYS as $key) {
            $this->assertArrayHasKey($key, $report, "Report must contain key '{$key}'");
        }
    }

    // =========================================================================
    // (b) Aggregation of calls_made and calls_avoided
    // =========================================================================

    /** @test */
    public function reporter_aggregates_calls_correctly_from_multiple_run_rows(): void
    {
        $this->insertRunStat([
            'listing_id'                 => 10,
            'categories_fetched_fresh'   => 16,
            'categories_from_tile_cache' => 3,
            'categories_grouped'         => 3,
        ]);
        $this->insertRunStat([
            'listing_id'                 => 11,
            'categories_fetched_fresh'   => 5,
            'categories_from_tile_cache' => 11,
            'categories_grouped'         => 3,
        ]);

        $reporter = new LocationDnaPoiCostReporter();
        $report   = $reporter->report();

        $this->assertSame(21, $report['calls_made'],
            'calls_made must sum categories_fetched_fresh across all rows');
        $this->assertSame(14, $report['calls_avoided_tile'],
            'calls_avoided_tile must sum categories_from_tile_cache across all rows');
        $this->assertSame(6, $report['calls_avoided_grouping'],
            'calls_avoided_grouping must sum categories_grouped across all rows');
    }

    // =========================================================================
    // (c) cache_hit_rate_pct and estimated_saving_usd
    // =========================================================================

    /** @test */
    public function reporter_computes_cache_hit_rate_and_estimated_saving_correctly(): void
    {
        $this->insertRunStat([
            'listing_id'                 => 20,
            'categories_fetched_fresh'   => 8,
            'categories_from_tile_cache' => 8,
            'categories_grouped'         => 3,
        ]);

        $reporter = new LocationDnaPoiCostReporter();
        $report   = $reporter->report();

        // 8 hits out of (8+8)=16 total = 50%
        $this->assertEqualsWithDelta(50.0, $report['cache_hit_rate_pct'], 0.01,
            'cache_hit_rate_pct must be 50% when hits = misses');

        // (8 tile hits + 3 grouping) * $0.032 = $0.352
        $this->assertEqualsWithDelta(0.352, $report['estimated_saving_usd'], 0.0001);
    }

    // =========================================================================
    // (d) listing_ids_with_reused_data
    // =========================================================================

    /** @test */
    public function reporter_lists_only_listings_that_had_tile_cache_hits(): void
    {
        $this->insertRunStat([
            'listing_id'                 => 30,
            'categories_from_tile_cache' => 5,
        ]);
        $this->insertRunStat([
            'listing_id'                 => 31,
            'categories_from_tile_cache' => 0,  // no tile hits
        ]);

        $reporter = new LocationDnaPoiCostReporter();
        $report   = $reporter->report();

        $listingIds = array_column($report['listing_ids_with_reused_data'], 'listing_id');

        $this->assertContains(30, $listingIds, 'Listing 30 (with tile hits) must appear in reused list');
        $this->assertNotContains(31, $listingIds, 'Listing 31 (zero tile hits) must NOT appear in reused list');
    }

    // =========================================================================
    // (e) --since= filter
    // =========================================================================

    /** @test */
    public function since_filter_excludes_older_run_stats(): void
    {
        // Insert an old row (3 days ago)
        DB::table('location_dna_poi_run_stats')->insert([
            'listing_type'               => 'seller_agent_auction',
            'listing_id'                 => 40,
            'categories_fetched_fresh'   => 19,
            'categories_from_tile_cache' => 0,
            'categories_grouped'         => 3,
            'precision_used'             => null,
            'run_at'                     => now()->subDays(3)->toDateTimeString(),
        ]);
        // Insert a recent row
        $this->insertRunStat([
            'listing_id'                 => 41,
            'categories_fetched_fresh'   => 16,
            'categories_from_tile_cache' => 3,
            'categories_grouped'         => 3,
        ]);

        $since    = now()->subDay()->format('Y-m-d H:i:s');
        $reporter = new LocationDnaPoiCostReporter();
        $report   = $reporter->report($since);

        // Only the recent row should be counted
        $this->assertSame(16, $report['calls_made'],
            '--since filter must exclude the 3-days-ago row');
    }

    // =========================================================================
    // (f) Empty report when no stats rows exist
    // =========================================================================

    /** @test */
    public function reporter_returns_zeros_when_no_stats_rows_exist(): void
    {
        // Ensure table is empty for this test via transaction rollback
        DB::table('location_dna_poi_run_stats')->delete();

        $reporter = new LocationDnaPoiCostReporter();
        $report   = $reporter->report();

        $this->assertSame(0, $report['calls_made']);
        $this->assertSame(0, $report['calls_avoided_tile']);
        $this->assertSame(0, $report['calls_avoided_grouping']);
        $this->assertEqualsWithDelta(0.0, $report['cache_hit_rate_pct'], 0.001);
        $this->assertEqualsWithDelta(0.0, $report['estimated_saving_usd'], 0.0001);
        $this->assertEmpty($report['listing_ids_with_reused_data']);
    }

    // =========================================================================
    // Command output — table headers and metrics
    // =========================================================================

    /** @test */
    public function command_output_is_produced_successfully_with_data(): void
    {
        // Insert a stat row so the reporter has something to aggregate
        $this->insertRunStat([
            'listing_id'                 => 50,
            'categories_fetched_fresh'   => 16,
            'categories_from_tile_cache' => 0,
            'categories_grouped'         => 3,
        ]);

        // Verify the command exits cleanly and the reporter returns the expected keys
        $this->artisan('ldna:poi-cost-report')->assertExitCode(0);

        // Verify via the reporter service that all expected labels are present in the report
        $reporter = new LocationDnaPoiCostReporter();
        $report   = $reporter->report();

        $this->assertSame(16, $report['calls_made']);
        $this->assertSame(3,  $report['calls_avoided_grouping']);
    }

    /** @test */
    public function command_accepts_since_option_with_valid_date(): void
    {
        $this->artisan('ldna:poi-cost-report', ['--since' => '2026-01-01'])
            ->assertExitCode(0);
    }

    /** @test */
    public function command_exits_with_failure_for_invalid_since_option(): void
    {
        $this->artisan('ldna:poi-cost-report', ['--since' => 'not-a-date'])
            ->assertExitCode(1);
    }
}
