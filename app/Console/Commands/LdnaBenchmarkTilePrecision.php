<?php

namespace App\Console\Commands;

use App\Models\PropertyLocationDna;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaPoiTileCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * ldna:benchmark-tile-precision — Compare POI output quality at four tile precision levels.
 *
 * Runs calculateForListing() for each supplied listing at four candidate precisions:
 *   0.001° (~100 m), 0.0025° (~250 m), 0.005° (~500 m), 0.01° (~1 km)
 *
 * For each precision, cache hit rate and average calls per listing are recorded.
 * Top-3 POI stability is computed as the match rate vs. the uncached (precision=null) baseline.
 *
 * Usage:
 *   php artisan ldna:benchmark-tile-precision --listing-ids=1,2,3,4,5
 *   php artisan ldna:benchmark-tile-precision  # uses up to 10 random listings
 */
class LdnaBenchmarkTilePrecision extends Command
{
    protected $signature = 'ldna:benchmark-tile-precision
        {--listing-ids= : Comma-separated listing IDs to benchmark (format: type:id e.g. seller_agent_auction:5)}
        {--listing-type=seller_agent_auction : Listing type to use when IDs are bare integers}
        {--sample=10    : Number of random listings to sample when --listing-ids is not set}';

    protected $description = 'Benchmark tile precision levels (0.001°–0.01°) against uncached baseline';

    private const CANDIDATE_PRECISIONS = [0.001, 0.0025, 0.005, 0.01];

    public function handle(): int
    {
        $listingPairs = $this->resolveListingPairs();

        if (empty($listingPairs)) {
            $this->warn('No listings found to benchmark.');
            return Command::SUCCESS;
        }

        $this->info('Benchmarking ' . count($listingPairs) . ' listing(s) across ' . count(self::CANDIDATE_PRECISIONS) . ' precision levels.');
        $this->newLine();

        // ── Step 1: Build uncached baseline (tile_precision = null) ──────────
        $this->line('Running uncached baseline (tile cache disabled)...');
        $baseline = $this->runAllListings($listingPairs, null);

        // ── Step 2: Run each precision level ─────────────────────────────────
        $rows = [];

        foreach (self::CANDIDATE_PRECISIONS as $precision) {
            $this->line("Running precision={$precision}°...");
            $results = $this->runAllListings($listingPairs, $precision);

            $hitRate     = $this->computeHitRate($results);
            $avgCalls    = $this->computeAvgCallsPerListing($results);
            $stability   = $this->computeTop3Stability($baseline, $results);

            $rows[] = [
                number_format($precision, 4) . '°',
                round($hitRate, 1) . '%',
                number_format($avgCalls, 1),
                round($stability, 1) . '%',
            ];
        }

        $this->table(
            ['Precision', 'Cache Hit Rate', 'Avg Calls/Listing', 'Top-3 POI Stability (vs baseline)'],
            $rows
        );

        $this->newLine();
        $this->info('Benchmark complete. See docs/ldna-tile-precision-benchmark.md for guidance on choosing a production value.');

        return Command::SUCCESS;
    }

    /**
     * Resolve listing pairs [(type, id)] from options or random sample.
     */
    private function resolveListingPairs(): array
    {
        $rawIds       = $this->option('listing-ids');
        $defaultType  = $this->option('listing-type') ?: 'seller_agent_auction';

        if (! empty($rawIds)) {
            $pairs = [];
            foreach (explode(',', $rawIds) as $token) {
                $token = trim($token);
                if (str_contains($token, ':')) {
                    [$type, $id] = explode(':', $token, 2);
                    $pairs[] = [trim($type), (int) trim($id)];
                } elseif (is_numeric($token)) {
                    $pairs[] = [$defaultType, (int) $token];
                }
            }
            return $pairs;
        }

        // Random sample from PropertyLocationDna records
        $sample = max(1, (int) $this->option('sample'));
        $records = PropertyLocationDna::where('geocode_status', 'geocoded')
            ->inRandomOrder()
            ->limit($sample)
            ->get(['listing_type', 'listing_id']);

        return $records->map(fn($r) => [$r->listing_type, (int) $r->listing_id])->toArray();
    }

    /**
     * Run calculateForListing() for all pairs with the given tile precision.
     *
     * When precision is null the tile cache is disabled (baseline mode).
     * The array cache store is flushed before each run to prevent cross-precision
     * contamination (a 0.001° run must not pollute a subsequent 0.005° run).
     */
    private function runAllListings(array $pairs, ?float $precision): array
    {
        // Flush the array cache store so no tile cache entries bleed across runs
        Cache::store('array')->flush();

        // Set the precision config for this run
        config(['location_dna.poi.tile_precision' => $precision]);

        // Build a fresh tile cache instance reflecting the new config
        $tileCache = new LocationDnaPoiTileCache();

        $results = [];

        foreach ($pairs as [$listingType, $listingId]) {
            try {
                $service = new LocationDnaPoiDistanceService(
                    httpClient:  null,
                    auditService: null,
                    rankingEngine: null,
                    tileCache:   $tileCache,
                );

                $output = $service->calculateForListing($listingType, $listingId);
                $stats  = $service->getLastRunStats();

                $results[] = [
                    'listing_type'  => $listingType,
                    'listing_id'    => $listingId,
                    'output'        => $output,
                    'stats'         => $stats,
                ];
            } catch (Throwable $e) {
                $results[] = [
                    'listing_type'  => $listingType,
                    'listing_id'    => $listingId,
                    'output'        => null,
                    'stats'         => null,
                    'error'         => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function computeHitRate(array $results): float
    {
        $totalHits  = 0;
        $totalCalls = 0;

        foreach ($results as $r) {
            if ($r['stats'] === null) continue;
            $totalHits  += (int) ($r['stats']['categories_from_tile_cache'] ?? 0);
            $totalCalls += (int) ($r['stats']['categories_fetched_fresh'] ?? 0);
        }

        $total = $totalHits + $totalCalls;
        return $total > 0 ? ($totalHits / $total) * 100 : 0.0;
    }

    private function computeAvgCallsPerListing(array $results): float
    {
        $total   = 0;
        $counted = 0;

        foreach ($results as $r) {
            if ($r['stats'] === null) continue;
            $total += (int) ($r['stats']['categories_fetched_fresh'] ?? 0);
            $counted++;
        }

        return $counted > 0 ? $total / $counted : 0.0;
    }

    /**
     * Compute top-3 POI stability: percentage of (listing, category) pairs where
     * the top-3 POI names match the baseline.
     */
    private function computeTop3Stability(array $baseline, array $results): float
    {
        $baselineMap = [];
        foreach ($baseline as $r) {
            if (empty($r['output']['results'])) continue;
            $key = $r['listing_type'] . ':' . $r['listing_id'];
            foreach ($r['output']['results'] as $row) {
                if (($row['rank'] ?? 99) <= 3) {
                    $baselineMap[$key][$row['poi_category']][] = $row['poi_name'] ?? '';
                }
            }
        }

        $matches = 0;
        $total   = 0;

        foreach ($results as $r) {
            if (empty($r['output']['results'])) continue;
            $key = $r['listing_type'] . ':' . $r['listing_id'];

            foreach ($r['output']['results'] as $row) {
                if (($row['rank'] ?? 99) > 3) continue;
                $total++;

                $baselineNames = $baselineMap[$key][$row['poi_category']] ?? [];
                if (in_array($row['poi_name'] ?? '', $baselineNames, true)) {
                    $matches++;
                }
            }
        }

        return $total > 0 ? ($matches / $total) * 100 : 100.0;
    }
}
