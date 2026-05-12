<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Artisan command: cities:populate-from-zips
 *
 * Populates us_cities from the us_zip_codes table without deleting or
 * duplicating any existing rows.
 *
 * Usage:
 *   php artisan cities:populate-from-zips
 *   php artisan cities:populate-from-zips --dry-run
 */
class PopulateCitiesFromZipCodes extends Command
{
    protected $signature   = 'cities:populate-from-zips {--dry-run : Show counts only, do not insert}';
    protected $description = 'Populate us_cities with every distinct city/state from us_zip_codes (safe, non-destructive)';

    public function handle(): int
    {
        $before = DB::table('us_cities')->count();
        $this->info("us_cities before: {$before} rows");

        // Count how many rows would be inserted
        $wouldInsert = DB::selectOne("
            SELECT COUNT(*) AS n
            FROM (
                SELECT DISTINCT LOWER(TRIM(z.city)) AS ck, s.id AS sid
                FROM  us_zip_codes z
                JOIN  us_states    s ON LOWER(s.abbreviation) = LOWER(TRIM(z.state_abbrev))
                WHERE TRIM(z.city) <> ''
            ) src
            WHERE NOT EXISTS (
                SELECT 1
                FROM   us_cities uc
                WHERE  LOWER(TRIM(uc.name)) = src.ck
                  AND  uc.state_id           = src.sid
            )
        ")->n;

        $this->info("Rows to insert: {$wouldInsert}");

        if ($this->option('dry-run')) {
            $this->info('[dry-run] No rows written.');
            return 0;
        }

        if ($wouldInsert === 0) {
            $this->info('Nothing to insert — us_cities is already fully populated.');
            return 0;
        }

        $this->info('Inserting…');

        DB::statement("
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
        ");

        $after    = DB::table('us_cities')->count();
        $inserted = $after - $before;

        $this->info("us_cities after:  {$after} rows (from ZIP source)");
        $this->line("<fg=green>✔ Inserted {$inserted} new city/state records.</>");

        // ---------------------------------------------------------------
        // Supplemental: cities that exist but whose USPS ZIP records list
        // a different primary city name (e.g. Saint Petersburg instead of
        // the actual municipality name). Hard-coded with known state/county.
        // state_id 10 = Florida, county_id 368 = Pinellas County,
        //                        county_id 327 = Collier County.
        // state_id 33 = New York.  "New York" row is an autocomplete alias
        //   so that typing "New York" surfaces "New York City" alongside it;
        //   county_id 723 = New York County (Manhattan).
        // ---------------------------------------------------------------
        $supplemental = [
            ['name' => 'Dunedin',        'state_id' => 10, 'county_id' => 368],
            ['name' => 'Safety Harbor',  'state_id' => 10, 'county_id' => 368],
            ['name' => 'St. Pete Beach', 'state_id' => 10, 'county_id' => 368],
            ['name' => 'Madeira Beach',  'state_id' => 10, 'county_id' => 368], // Pinellas – absent from USPS ZIP source
            ['name' => 'Naples',         'state_id' => 10, 'county_id' => 327], // Collier – absent from USPS ZIP source
            ['name' => 'New York',       'state_id' => 33, 'county_id' => 723], // NY autocomplete alias (stored as "New York City")
        ];

        $suppInserted = 0;
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
                $suppInserted++;
            }
        }

        if ($suppInserted > 0) {
            $this->line("<fg=green>✔ Inserted {$suppInserted} supplemental city record(s) (absent from USPS ZIP data).</>");
        }

        $finalTotal = DB::table('us_cities')->count();
        $this->info("us_cities final:  {$finalTotal} rows");

        // Verify the five target cities
        $this->newLine();
        $this->info('Target city verification:');
        $targets = ['Treasure Island', 'Dunedin', 'Safety Harbor', 'Indian Rocks Beach', 'St. Pete Beach'];
        foreach ($targets as $city) {
            $row = DB::table('us_cities as uc')
                ->join('us_states as s', 'uc.state_id', '=', 's.id')
                ->where('s.abbreviation', 'FL')
                ->whereRaw("LOWER(TRIM(uc.name)) = ?", [strtolower(trim($city))])
                ->select('uc.id', 'uc.name', 'uc.county_id', 's.abbreviation')
                ->first();

            if ($row) {
                $this->line("  <fg=green>✔ {$row->name}, {$row->abbreviation} (id={$row->id}, county_id=" . ($row->county_id ?? 'null') . ")</>");
            } else {
                $this->line("  <fg=red>✘ {$city}, FL — not found</>");
            }
        }

        return 0;
    }
}
