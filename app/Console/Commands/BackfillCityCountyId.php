<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfills county_id in us_cities where it is currently NULL.
 *
 * Strategy:
 *   1. Build a DISTINCT ON lookup of ZIP city→canonical county name (fast, no LATERAL).
 *   2. JOIN to us_counties for the same state.
 *   3. UPDATE us_cities.county_id only where it is NULL.
 *   Fully idempotent — re-running touches 0 rows.
 *
 * Usage:
 *   php artisan cities:backfill-county-id
 *   php artisan cities:backfill-county-id --dry-run
 */
class BackfillCityCountyId extends Command
{
    protected $signature   = 'cities:backfill-county-id {--dry-run : Count matches without writing}';
    protected $description = 'Backfill county_id in us_cities where NULL, matched via us_zip_codes (safe, idempotent)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $nullBefore = DB::table('us_cities')->whereNull('county_id')->count();
        $this->info("us_cities with county_id = NULL before: {$nullBefore}");

        // ------------------------------------------------------------------
        // Pre-compute city→canonical_county_name from us_zip_codes using
        // DISTINCT ON — one row per (city_lower, state_lower) combination.
        // This avoids a correlated LATERAL subquery over 25K rows.
        // ------------------------------------------------------------------
        $zipMap = "
            SELECT DISTINCT ON (LOWER(TRIM(z.city)), LOWER(z.state_abbrev))
                LOWER(TRIM(z.city))        AS city_lower,
                LOWER(z.state_abbrev)      AS state_lower,
                CASE
                    WHEN z.state_abbrev = 'LA'
                        THEN TRIM(z.county) || ' Parish'
                    WHEN z.state_abbrev = 'AK'
                        THEN TRIM(z.county) || ' Borough'
                    WHEN z.state_abbrev = 'VA'
                     AND TRIM(z.county) ILIKE '% City'
                        THEN TRIM(z.county)
                    ELSE TRIM(z.county) || ' County'
                END AS canonical_county_name
            FROM us_zip_codes z
            WHERE TRIM(z.county) <> ''
            ORDER BY LOWER(TRIM(z.city)), LOWER(z.state_abbrev)
        ";

        if ($dryRun) {
            $countSql = "
                SELECT COUNT(*) AS matches
                FROM us_cities uc
                JOIN us_states s ON s.id = uc.state_id
                JOIN ({$zipMap}) zip_map
                  ON  zip_map.city_lower  = LOWER(TRIM(uc.name))
                  AND zip_map.state_lower = LOWER(s.abbreviation)
                JOIN us_counties co
                  ON  co.state_id             = uc.state_id
                  AND LOWER(TRIM(co.name))    = LOWER(zip_map.canonical_county_name)
                WHERE uc.county_id IS NULL
            ";
            $result = DB::selectOne($countSql);
            $this->line("<fg=yellow>[dry-run] Would update approximately {$result->matches} city rows.</>");
            return 0;
        }

        // ------------------------------------------------------------------
        // Live UPDATE
        // ------------------------------------------------------------------
        $updateSql = "
            UPDATE us_cities
            SET    county_id  = sq.county_id,
                   updated_at = NOW()
            FROM (
                SELECT
                    uc.id  AS city_id,
                    co.id  AS county_id
                FROM us_cities uc
                JOIN us_states s ON s.id = uc.state_id
                JOIN ({$zipMap}) zip_map
                  ON  zip_map.city_lower  = LOWER(TRIM(uc.name))
                  AND zip_map.state_lower = LOWER(s.abbreviation)
                JOIN us_counties co
                  ON  co.state_id             = uc.state_id
                  AND LOWER(TRIM(co.name))    = LOWER(zip_map.canonical_county_name)
                WHERE uc.county_id IS NULL
            ) sq
            WHERE us_cities.id = sq.city_id
        ";

        DB::statement($updateSql);

        $nullAfter  = DB::table('us_cities')->whereNull('county_id')->count();
        $withCounty = DB::table('us_cities')->whereNotNull('county_id')->count();
        $updated    = $nullBefore - $nullAfter;

        $this->info("us_cities with county_id = NULL after:  {$nullAfter}");
        $this->info("us_cities with county_id populated:     {$withCounty}");
        $this->line("<fg=green>✔ Updated county_id for {$updated} cities.</>");

        // ------------------------------------------------------------------
        // Verification: Florida coastal target cities
        // ------------------------------------------------------------------
        $this->newLine();
        $this->info('Florida target city county verification:');
        $targets = [
            'Treasure Island'    => 'Pinellas County',
            'Dunedin'            => 'Pinellas County',
            'Safety Harbor'      => 'Pinellas County',
            'Indian Rocks Beach' => 'Pinellas County',
            'St. Pete Beach'     => 'Pinellas County',
        ];

        foreach ($targets as $city => $expectedCounty) {
            $row = DB::table('us_cities as uc')
                ->join('us_states as s', 's.id', '=', 'uc.state_id')
                ->leftJoin('us_counties as co', 'co.id', '=', 'uc.county_id')
                ->where('s.abbreviation', 'FL')
                ->whereRaw('LOWER(TRIM(uc.name)) = ?', [strtolower($city)])
                ->select('uc.name', 'co.name as county_name', 'uc.county_id')
                ->first();

            if (! $row) {
                $this->warn("  ⚠  {$city} — city not found in us_cities");
                continue;
            }

            $matched = $row->county_name && strtolower(trim($row->county_name)) === strtolower($expectedCounty);
            $icon    = $matched ? '<fg=green>✔</>' : '<fg=red>✗</>';
            $actual  = $row->county_name ?? 'NULL';
            $this->line("  {$icon} {$city} → {$actual} (expected: {$expectedCounty})");
        }

        return 0;
    }
}
