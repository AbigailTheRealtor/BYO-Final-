<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds "Treasure Island, FL" to the city autocomplete dataset.
 * Safety net for deployments seeded before FlCitiesPatchSeeder was added to
 * DatabaseSeeder. Fresh installs receive this data via `php artisan db:seed`.
 * All IDs resolved from natural keys — no hardcoded numeric PKs.
 */
class SeedTreasureIslandCityData extends Migration
{
    public function up()
    {
        $now = now();

        $flStateId = DB::table('us_states')
            ->where('abbreviation', 'FL')
            ->value('id');

        if (!$flStateId) {
            return;
        }

        $pinellasCountyId = DB::table('us_counties')
            ->where('state_id', $flStateId)
            ->where('name', 'Pinellas County')
            ->value('id');

        $exists = DB::table('us_cities')
            ->where('state_id', $flStateId)
            ->whereRaw('LOWER(name) = ?', ['treasure island'])
            ->exists();

        if (!$exists) {
            DB::table('us_cities')->insertOrIgnore([
                'name'       => 'Treasure Island',
                'fips_code'  => null,
                'state_id'   => $flStateId,
                'county_id'  => $pinellasCountyId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } elseif ($pinellasCountyId) {
            DB::table('us_cities')
                ->where('state_id', $flStateId)
                ->whereRaw('LOWER(name) = ?', ['treasure island'])
                ->whereNull('county_id')
                ->update(['county_id' => $pinellasCountyId, 'updated_at' => $now]);
        }

        // ZIP 33706 primary city in us_zip_codes is "Saint Petersburg" (USPS default).
        // Add Treasure Island as an alias so autoPopulateZipCodesFromCity() fallback resolves it.
        $aliasExists = DB::table('us_zip_code_cities')
            ->where('zip_code', '33706')
            ->whereRaw('LOWER(city) = ?', ['treasure island'])
            ->exists();

        if (!$aliasExists) {
            DB::table('us_zip_code_cities')->insertOrIgnore([
                'zip_code'     => '33706',
                'city'         => 'Treasure Island',
                'state_abbrev' => 'FL',
                'county'       => 'Pinellas',
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down()
    {
        $flStateId = DB::table('us_states')
            ->where('abbreviation', 'FL')
            ->value('id');

        if ($flStateId) {
            DB::table('us_cities')
                ->where('state_id', $flStateId)
                ->whereRaw('LOWER(name) = ?', ['treasure island'])
                ->whereNull('fips_code')
                ->delete();
        }

        DB::table('us_zip_code_cities')
            ->where('zip_code', '33706')
            ->whereRaw('LOWER(city) = ?', ['treasure island'])
            ->delete();
    }
}
