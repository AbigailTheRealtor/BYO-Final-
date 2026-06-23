<?php

namespace App\Services\LocationDna;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * LocationDnaPoiCostReporter — Aggregates per-run stats from location_dna_poi_run_stats
 * and produces a cost-impact summary.
 *
 * Output keys:
 *   calls_made                 — Total fresh Google Places API calls recorded.
 *   calls_avoided_tile         — Calls skipped due to tile cache hits.
 *   calls_avoided_grouping     — Calls skipped due to category grouping.
 *   cache_hit_rate_pct         — Tile-cache hit rate as a percentage (0–100).
 *   estimated_saving_usd       — Estimated dollar savings at $0.032/call.
 *   listing_ids_with_reused_data — Array of listing IDs that had at least one tile cache hit.
 */
class LocationDnaPoiCostReporter
{
    private const GOOGLE_PLACES_COST_PER_CALL_USD = 0.032;

    /**
     * Generate a cost report.
     *
     * @param  \DateTimeInterface|string|null  $since  Only include runs on or after this timestamp.
     * @return array{
     *   calls_made: int,
     *   calls_avoided_tile: int,
     *   calls_avoided_grouping: int,
     *   cache_hit_rate_pct: float,
     *   estimated_saving_usd: float,
     *   listing_ids_with_reused_data: array
     * }
     */
    public function report($since = null): array
    {
        try {
            $query = DB::table('location_dna_poi_run_stats');

            if ($since !== null) {
                $sinceStr = ($since instanceof \DateTimeInterface)
                    ? $since->format('Y-m-d H:i:s')
                    : (string) $since;

                $query->where('run_at', '>=', $sinceStr);
            }

            $rows = $query->get();

            if ($rows->isEmpty()) {
                return $this->emptyReport();
            }

            $callsMade              = (int) $rows->sum('categories_fetched_fresh');
            $callsAvoidedTile       = (int) $rows->sum('categories_from_tile_cache');
            $callsAvoidedGrouping   = (int) $rows->sum('categories_grouped');

            $totalConsidered = $callsMade + $callsAvoidedTile;
            $hitRatePct = $totalConsidered > 0
                ? round(($callsAvoidedTile / $totalConsidered) * 100, 2)
                : 0.0;

            $totalCallsAvoided    = $callsAvoidedTile + $callsAvoidedGrouping;
            $estimatedSavingUsd   = round($totalCallsAvoided * self::GOOGLE_PLACES_COST_PER_CALL_USD, 4);

            $reusedListingIds = $rows
                ->filter(fn($row) => (int) $row->categories_from_tile_cache > 0)
                ->unique(fn($row) => $row->listing_type . ':' . $row->listing_id)
                ->map(fn($row) => [
                    'listing_type' => $row->listing_type,
                    'listing_id'   => (int) $row->listing_id,
                ])
                ->values()
                ->toArray();

            return [
                'calls_made'                  => $callsMade,
                'calls_avoided_tile'          => $callsAvoidedTile,
                'calls_avoided_grouping'      => $callsAvoidedGrouping,
                'cache_hit_rate_pct'          => $hitRatePct,
                'estimated_saving_usd'        => $estimatedSavingUsd,
                'listing_ids_with_reused_data' => $reusedListingIds,
            ];

        } catch (Throwable $e) {
            return array_merge($this->emptyReport(), ['error' => $e->getMessage()]);
        }
    }

    private function emptyReport(): array
    {
        return [
            'calls_made'                  => 0,
            'calls_avoided_tile'          => 0,
            'calls_avoided_grouping'      => 0,
            'cache_hit_rate_pct'          => 0.0,
            'estimated_saving_usd'        => 0.0,
            'listing_ids_with_reused_data' => [],
        ];
    }
}
