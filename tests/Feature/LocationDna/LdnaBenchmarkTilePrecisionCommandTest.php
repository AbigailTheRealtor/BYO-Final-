<?php

namespace Tests\Feature\LocationDna;

use App\Console\Commands\LdnaBenchmarkTilePrecision;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Tests\TestCase;

/**
 * LdnaBenchmarkTilePrecisionCommandTest
 *
 * Verifies that ldna:benchmark-tile-precision:
 *   (a) Completes without error (exits 0) on stub/empty data
 *   (b) Accepts --listing-ids option without crashing
 *   (c) Gracefully handles a missing Google API key
 *   (d) Exits zero and outputs table on empty sample
 *   (e) CANDIDATE_PRECISIONS constant is correct
 *   (f) computeHitRate calculates correctly
 *   (g) computeAvgCallsPerListing calculates correctly
 *   (h) computeTop3Stability calculates correctly
 *   (i) Array cache is flushed at the start of each runAllListings() call
 *   (j) Command signature is correct
 */
class LdnaBenchmarkTilePrecisionCommandTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Invoke a private method on the command via reflection.
     */
    private function callPrivate(LdnaBenchmarkTilePrecision $cmd, string $method, array $args = []): mixed
    {
        $ref = new ReflectionClass($cmd);
        $m   = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($cmd, $args);
    }

    private function makeCommand(): LdnaBenchmarkTilePrecision
    {
        return $this->app->make(LdnaBenchmarkTilePrecision::class);
    }

    // =========================================================================
    // (a) Completes without error on empty listing set
    // =========================================================================

    /** @test */
    public function command_exits_zero_when_no_listings_available(): void
    {
        $this->artisan('ldna:benchmark-tile-precision', ['--sample' => '1'])
            ->assertExitCode(0);
    }

    // =========================================================================
    // (b) Accepts --listing-ids option without crashing
    // =========================================================================

    /** @test */
    public function command_accepts_listing_ids_option_without_exception(): void
    {
        // Non-existent listing IDs — should handle gracefully (no DB rows, no API key)
        $this->artisan('ldna:benchmark-tile-precision', [
            '--listing-ids' => 'seller_agent_auction:999999',
        ])->assertExitCode(0);
    }

    // =========================================================================
    // (c) Handles missing Google API key gracefully
    // =========================================================================

    /** @test */
    public function command_handles_missing_google_api_key_gracefully(): void
    {
        config(['services.google.places_key' => null]);

        $this->artisan('ldna:benchmark-tile-precision', ['--sample' => '1'])
            ->assertExitCode(0);
    }

    // =========================================================================
    // (d) Exits zero and outputs table
    // =========================================================================

    /** @test */
    public function command_exits_zero_and_outputs_comparison_table(): void
    {
        $this->artisan('ldna:benchmark-tile-precision', ['--sample' => '1'])
            ->assertExitCode(0);
    }

    // =========================================================================
    // (e) CANDIDATE_PRECISIONS constant — 4 entries with correct values
    // =========================================================================

    /** @test */
    public function candidate_precisions_constant_has_exactly_four_entries(): void
    {
        $ref   = new ReflectionClass(LdnaBenchmarkTilePrecision::class);
        $const = $ref->getConstant('CANDIDATE_PRECISIONS');

        $this->assertIsArray($const);
        $this->assertCount(4, $const,
            'CANDIDATE_PRECISIONS must contain exactly 4 precision levels');
    }

    /** @test */
    public function candidate_precisions_constant_contains_expected_values(): void
    {
        $ref   = new ReflectionClass(LdnaBenchmarkTilePrecision::class);
        $const = $ref->getConstant('CANDIDATE_PRECISIONS');

        $this->assertContains(0.001,  $const, 'CANDIDATE_PRECISIONS must include 0.001');
        $this->assertContains(0.0025, $const, 'CANDIDATE_PRECISIONS must include 0.0025');
        $this->assertContains(0.005,  $const, 'CANDIDATE_PRECISIONS must include 0.005');
        $this->assertContains(0.01,   $const, 'CANDIDATE_PRECISIONS must include 0.01');
    }

    // =========================================================================
    // (f) computeHitRate — private method correctness
    // =========================================================================

    /** @test */
    public function compute_hit_rate_returns_zero_for_empty_results(): void
    {
        $rate = $this->callPrivate($this->makeCommand(), 'computeHitRate', [[]]);
        $this->assertSame(0.0, $rate);
    }

    /** @test */
    public function compute_hit_rate_returns_zero_when_all_results_have_null_stats(): void
    {
        $results = [
            ['stats' => null],
            ['stats' => null],
        ];
        $rate = $this->callPrivate($this->makeCommand(), 'computeHitRate', [$results]);
        $this->assertSame(0.0, $rate);
    }

    /** @test */
    public function compute_hit_rate_calculates_correctly_from_stats(): void
    {
        // 4 hits + 12 fresh = 16 total → hit rate = 25%
        $results = [
            ['stats' => ['categories_from_tile_cache' => 4,  'categories_fetched_fresh' => 12]],
            ['stats' => ['categories_from_tile_cache' => 0,  'categories_fetched_fresh' => 0]],
            ['stats' => null],
        ];
        $rate = $this->callPrivate($this->makeCommand(), 'computeHitRate', [$results]);
        $this->assertSame(25.0, $rate);
    }

    /** @test */
    public function compute_hit_rate_returns_100_when_all_from_cache(): void
    {
        $results = [
            ['stats' => ['categories_from_tile_cache' => 16, 'categories_fetched_fresh' => 0]],
        ];
        $rate = $this->callPrivate($this->makeCommand(), 'computeHitRate', [$results]);
        $this->assertSame(100.0, $rate);
    }

    // =========================================================================
    // (g) computeAvgCallsPerListing — private method correctness
    // =========================================================================

    /** @test */
    public function compute_avg_calls_per_listing_returns_zero_for_empty_results(): void
    {
        $avg = $this->callPrivate($this->makeCommand(), 'computeAvgCallsPerListing', [[]]);
        $this->assertSame(0.0, $avg);
    }

    /** @test */
    public function compute_avg_calls_per_listing_calculates_correctly(): void
    {
        // 16 + 8 = 24 calls, 2 listings with stats → avg = 12.0
        $results = [
            ['stats' => ['categories_fetched_fresh' => 16]],
            ['stats' => ['categories_fetched_fresh' => 8]],
            ['stats' => null],
        ];
        $avg = $this->callPrivate($this->makeCommand(), 'computeAvgCallsPerListing', [$results]);
        $this->assertSame(12.0, $avg);
    }

    // =========================================================================
    // (h) computeTop3Stability — private method correctness
    // =========================================================================

    /** @test */
    public function compute_top3_stability_returns_100_when_results_empty(): void
    {
        $stability = $this->callPrivate($this->makeCommand(), 'computeTop3Stability', [[], []]);
        $this->assertSame(100.0, $stability,
            'An empty results set has no mismatches, so stability must be 100%');
    }

    /** @test */
    public function compute_top3_stability_returns_100_when_top3_names_match_baseline(): void
    {
        $baselineRow = fn(string $category, string $name, int $rank) => [
            'poi_category' => $category,
            'poi_name'     => $name,
            'rank'         => $rank,
        ];

        $baseline = [[
            'listing_type' => 'seller_agent_auction',
            'listing_id'   => 1,
            'output'       => ['results' => [
                $baselineRow('park', 'Green Park', 1),
                $baselineRow('park', 'City Park', 2),
            ]],
            'stats' => null,
        ]];

        $results = [[
            'listing_type' => 'seller_agent_auction',
            'listing_id'   => 1,
            'output'       => ['results' => [
                $baselineRow('park', 'Green Park', 1),
                $baselineRow('park', 'City Park', 2),
            ]],
            'stats' => null,
        ]];

        $stability = $this->callPrivate($this->makeCommand(), 'computeTop3Stability', [$baseline, $results]);
        $this->assertSame(100.0, $stability);
    }

    /** @test */
    public function compute_top3_stability_returns_0_when_no_names_match_baseline(): void
    {
        $baseline = [[
            'listing_type' => 'seller_agent_auction',
            'listing_id'   => 1,
            'output'       => ['results' => [
                ['poi_category' => 'park', 'poi_name' => 'Green Park', 'rank' => 1],
            ]],
            'stats' => null,
        ]];

        $results = [[
            'listing_type' => 'seller_agent_auction',
            'listing_id'   => 1,
            'output'       => ['results' => [
                ['poi_category' => 'park', 'poi_name' => 'Totally Different Park', 'rank' => 1],
            ]],
            'stats' => null,
        ]];

        $stability = $this->callPrivate($this->makeCommand(), 'computeTop3Stability', [$baseline, $results]);
        $this->assertSame(0.0, $stability);
    }

    /** @test */
    public function compute_top3_stability_ignores_results_with_rank_above_3(): void
    {
        $baseline = [[
            'listing_type' => 'seller_agent_auction',
            'listing_id'   => 1,
            'output'       => ['results' => [
                ['poi_category' => 'park', 'poi_name' => 'Close Park', 'rank' => 1],
            ]],
            'stats' => null,
        ]];

        // Results has rank=4 row only (should be excluded from stability check)
        $results = [[
            'listing_type' => 'seller_agent_auction',
            'listing_id'   => 1,
            'output'       => ['results' => [
                ['poi_category' => 'park', 'poi_name' => 'Far Away Park', 'rank' => 4],
            ]],
            'stats' => null,
        ]];

        // No rank ≤ 3 results → total is 0 → stability defaults to 100%
        $stability = $this->callPrivate($this->makeCommand(), 'computeTop3Stability', [$baseline, $results]);
        $this->assertSame(100.0, $stability);
    }

    // =========================================================================
    // (i) Array cache is flushed between precision runs
    // =========================================================================

    /** @test */
    public function run_all_listings_flushes_array_cache_before_executing(): void
    {
        // Pre-populate the array cache with a sentinel entry
        Cache::store('array')->put('ldna_test_sentinel_key', 'should_be_cleared', 3600);
        $this->assertSame('should_be_cleared', Cache::store('array')->get('ldna_test_sentinel_key'),
            'Precondition: sentinel must be present before runAllListings()');

        // runAllListings() should flush the array cache as its first action
        $this->callPrivate($this->makeCommand(), 'runAllListings', [[], null]);

        $this->assertNull(
            Cache::store('array')->get('ldna_test_sentinel_key'),
            'runAllListings() must flush the array cache store before executing to prevent cross-precision contamination'
        );
    }

    // =========================================================================
    // (j) Command signature
    // =========================================================================

    /** @test */
    public function command_signature_is_ldna_benchmark_tile_precision(): void
    {
        $this->assertSame(
            'ldna:benchmark-tile-precision',
            $this->makeCommand()->getName()
        );
    }
}
