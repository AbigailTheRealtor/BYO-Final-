<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PopulateCitiesFromZipCodesSeeder
 *
 * Inserts every distinct city/state combination from us_zip_codes into
 * us_cities — without deleting or duplicating any existing rows.
 *
 * Run directly:
 *   php artisan db:seed --class=PopulateCitiesFromZipCodesSeeder
 *
 * Or via the dedicated Artisan command (recommended, shows progress):
 *   php artisan cities:populate-from-zips
 *
 * Safety guarantees:
 *   - No existing us_cities rows are deleted or modified.
 *   - Deduplication is performed by (LOWER(TRIM(name)), state_id) before insert.
 *   - Only city/state combos that resolve to a known us_states row are inserted
 *     (territories like PR, GU that have no us_states entry are skipped).
 *   - county_id is populated via a LEFT JOIN on us_counties (NULL when no match).
 *   - fips_code is left NULL for new rows (Census FIPS not available from ZIPs).
 *   - No schema changes.
 */
class PopulateCitiesFromZipCodesSeeder extends Seeder
{
    public function run(): void
    {
        $before = DB::table('us_cities')->count();
        $this->command?->info("us_cities before: {$before} rows");

        // Single INSERT … SELECT with NOT EXISTS guard.
        // DISTINCT ON (city_lower, state_id) picks one representative ZIP row
        // per unique city/state pair, then LEFT JOINs counties for county_id.
        $sql = "
            INSERT INTO us_cities (name, fips_code, state_id, county_id, created_at, updated_at)
            SELECT
                src.city_name,
                NULL            AS fips_code,
                src.state_id,
                (
                    SELECT c.id
                    FROM   us_counties c
                    WHERE  LOWER(TRIM(c.name)) = LOWER(TRIM(src.county_name))
                      AND  c.state_id = src.state_id
                    LIMIT  1
                )               AS county_id,
                NOW()           AS created_at,
                NOW()           AS updated_at
            FROM (
                SELECT DISTINCT ON (LOWER(TRIM(z.city)), s.id)
                    TRIM(z.city)         AS city_name,
                    s.id                 AS state_id,
                    TRIM(z.county)       AS county_name
                FROM  us_zip_codes z
                JOIN  us_states    s ON LOWER(s.abbreviation) = LOWER(TRIM(z.state_abbrev))
                WHERE TRIM(z.city) <> ''
                ORDER BY LOWER(TRIM(z.city)), s.id
            ) src
            WHERE NOT EXISTS (
                SELECT 1
                FROM   us_cities uc
                WHERE  LOWER(TRIM(uc.name)) = LOWER(TRIM(src.city_name))
                  AND  uc.state_id           = src.state_id
            )
        ";

        DB::statement($sql);

        $after    = DB::table('us_cities')->count();
        $inserted = $after - $before;

        $this->command?->info("us_cities after:  {$after} rows (+{$inserted} from ZIP source)");

        // Supplemental: municipalities whose USPS ZIP records use a different
        // primary city name (e.g. "Saint Petersburg" instead of the actual
        // city name). state_id 10 = Florida, county_id 368 = Pinellas County.
        $supplemental = [
            ['name' => 'Dunedin',        'state_id' => 10, 'county_id' => 368],
            ['name' => 'Safety Harbor',  'state_id' => 10, 'county_id' => 368],
            ['name' => 'St. Pete Beach', 'state_id' => 10, 'county_id' => 368],
        ];

        foreach ($supplemental as $city) {
            $exists = DB::table('us_cities')
                ->whereRaw("LOWER(TRIM(name)) = ?", [strtolower(trim($city['name']))])
                ->where('state_id', $city['state_id'])
                ->exists();

            if (! $exists) {
                DB::table('us_cities')->insert([
                    'name'       => $city['name'],
                    'fips_code'  => null,
                    'state_id'   => $city['state_id'],
                    'county_id'  => $city['county_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $finalTotal = DB::table('us_cities')->count();
        $this->command?->info("us_cities final:  {$finalTotal} rows");
    }
}
