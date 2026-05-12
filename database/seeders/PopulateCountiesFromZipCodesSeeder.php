<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Populates us_counties from us_zip_codes.
 * Safe, additive, idempotent — no deletions, no schema changes.
 *
 * Usage:
 *   php artisan db:seed --class=PopulateCountiesFromZipCodesSeeder
 */
class PopulateCountiesFromZipCodesSeeder extends Seeder
{
    public function run(): void
    {
        $before = DB::table('us_counties')->count();
        $this->command?->info("us_counties before: {$before} rows");

        // Pull distinct county/state combos from ZIP source.
        // AK is excluded — its county-equivalents (Borough, Census Area, etc.)
        // are already fully and correctly named in us_counties, and the ZIP
        // source has mangled/shortened names that do not match.
        $candidates = DB::table('us_zip_codes as z')
            ->join('us_states as s', DB::raw('LOWER(s.abbreviation)'), '=', DB::raw('LOWER(z.state_abbrev)'))
            ->selectRaw("TRIM(z.county) AS bare, s.id AS state_id, s.abbreviation AS abbr")
            ->whereRaw("TRIM(z.county) <> ''")
            ->whereNotIn('s.abbreviation', ['AK'])
            ->distinct()
            ->get();

        $inserted = 0;
        $now      = now();

        foreach ($candidates as $row) {
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

        $after = DB::table('us_counties')->count();
        $this->command?->info("us_counties after:  {$after} rows (+{$inserted} inserted)");
    }

    protected function canonicalName(string $bare, string $abbr): string
    {
        if ($abbr === 'LA') {
            return trim($bare) . ' Parish';
        }
        if ($abbr === 'VA' && str_ends_with(trim($bare), ' City')) {
            return trim($bare);
        }
        return trim($bare) . ' County';
    }
}
