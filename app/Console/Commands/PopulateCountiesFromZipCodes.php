<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Populates us_counties from us_zip_codes.
 *
 * Strategy:
 *   1. Pull DISTINCT county + state_abbrev from us_zip_codes.
 *   2. Construct canonical county names using state-specific suffix rules:
 *        LA  → "<bare> Parish"
 *        VA  → "<bare> City" if bare already ends with " City", else "<bare> County"
 *        AK  → SKIPPED (already fully populated with proper Census names/types)
 *        All other states → "<bare> County"
 *   3. INSERT only rows whose (name, state_id) pair does not already exist.
 *   4. Fully idempotent — re-running inserts 0 rows.
 *
 * Usage:
 *   php artisan counties:populate-from-zips
 *   php artisan counties:populate-from-zips --dry-run
 */
class PopulateCountiesFromZipCodes extends Command
{
    protected $signature   = 'counties:populate-from-zips {--dry-run : Count rows to insert without writing anything}';
    protected $description = 'Populate us_counties nationwide from us_zip_codes (safe, additive, idempotent)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $before = DB::table('us_counties')->count();
        $this->info("us_counties before: {$before} rows");

        // ----------------------------------------------------------------
        // Build candidate list from ZIP source.
        // AK is excluded — its county-equivalents are already fully and
        // correctly named in us_counties (Borough, Census Area, Municipality,
        // City and Borough) and the ZIP bare names are mangled/shortened.
        // ----------------------------------------------------------------
        $candidates = DB::table('us_zip_codes as z')
            ->join('us_states as s', DB::raw('LOWER(s.abbreviation)'), '=', DB::raw('LOWER(z.state_abbrev)'))
            ->selectRaw("TRIM(z.county) AS bare, s.id AS state_id, s.abbreviation AS abbr")
            ->whereRaw("TRIM(z.county) <> ''")
            ->whereNotIn('s.abbreviation', ['AK']) // AK already complete
            ->distinct()
            ->get();

        $this->info("Distinct county/state combos from ZIP source (excl. AK): " . count($candidates));

        if ($dryRun) {
            $toInsert = $this->countNewRows($candidates);
            $this->line("<fg=yellow>[dry-run] Would insert approximately {$toInsert} new county rows.</>");
            return 0;
        }

        // ----------------------------------------------------------------
        // Insert missing rows one by one (allows per-row canonical naming).
        // Chunk to avoid memory issues.
        // ----------------------------------------------------------------
        $inserted = 0;
        $now      = now();

        foreach ($candidates->chunk(500) as $chunk) {
            foreach ($chunk as $row) {
                $canonical = $this->canonicalName($row->bare, $row->abbr);

                $exists = DB::table('us_counties')
                    ->where('state_id', $row->state_id)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($canonical))])
                    ->exists();

                if (! $exists) {
                    DB::table('us_counties')->insert([
                        'name'       => $canonical,
                        'fips_code'  => null,
                        'state_id'   => $row->state_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $inserted++;
                }
            }
        }

        $after = DB::table('us_counties')->count();
        $this->info("us_counties after:  {$after} rows");
        $this->line("<fg=green>✔ Inserted {$inserted} new county rows.</>");

        // ----------------------------------------------------------------
        // Verification: key states and FL counties
        // ----------------------------------------------------------------
        $this->newLine();
        $this->info('State coverage verification:');
        $statesMissingCounties = DB::table('us_states as s')
            ->leftJoin('us_counties as uc', 'uc.state_id', '=', 's.id')
            ->whereNotIn('s.abbreviation', ['AS', 'PR', 'GU', 'MP', 'VI']) // territories expected to have none
            ->selectRaw('s.abbreviation, COUNT(uc.id) AS county_count')
            ->groupBy('s.id', 's.abbreviation')
            ->having(DB::raw('COUNT(uc.id)'), '=', 0)
            ->get();

        if ($statesMissingCounties->isEmpty()) {
            $this->line('<fg=green>✔ All 50 states + DC have at least one county.</>');
        } else {
            foreach ($statesMissingCounties as $s) {
                $this->warn("  ⚠ {$s->abbreviation} has 0 counties");
            }
        }

        $this->newLine();
        $this->info('Florida county spot check:');
        $flTargets = ['Pinellas County', 'Hillsborough County', 'Miami-Dade County', 'Broward County', 'Orange County'];
        foreach ($flTargets as $name) {
            $exists = DB::table('us_counties')->where('state_id', 10)->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->exists();
            $icon   = $exists ? '<fg=green>✔</>' : '<fg=red>✗</>';
            $this->line("  {$icon} {$name}");
        }

        return 0;
    }

    /**
     * Construct the canonical county name for a given bare name and state abbreviation.
     */
    protected function canonicalName(string $bare, string $abbr): string
    {
        if ($abbr === 'LA') {
            return trim($bare) . ' Parish';
        }

        if ($abbr === 'VA' && str_ends_with(trim($bare), ' City')) {
            // VA independent cities already carry the " City" suffix in ZIP data
            return trim($bare);
        }

        return trim($bare) . ' County';
    }

    /**
     * Count how many rows would be inserted without writing (dry-run helper).
     */
    protected function countNewRows($candidates): int
    {
        $count = 0;
        foreach ($candidates as $row) {
            $canonical = $this->canonicalName($row->bare, $row->abbr);
            $exists = DB::table('us_counties')
                ->where('state_id', $row->state_id)
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($canonical))])
                ->exists();
            if (! $exists) {
                $count++;
            }
        }
        return $count;
    }
}
