<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FlCitiesPatchSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Running Florida cities patch seeder...');

        $now = now();

        $flStateId = DB::table('us_states')->where('abbreviation', 'FL')->value('id');
        if (!$flStateId) {
            $this->command->error('Florida state not found in us_states table.');
            return;
        }

        $countyIds = DB::table('us_counties')
            ->where('state_id', $flStateId)
            ->pluck('id', 'name')
            ->toArray();

        $patchCities = [
            ['name' => 'Treasure Island',       'county' => 'Pinellas County'],
            ['name' => 'Dunedin',               'county' => 'Pinellas County'],
            ['name' => 'Safety Harbor',         'county' => 'Pinellas County'],
            ['name' => 'Seminole',              'county' => 'Pinellas County'],
            ['name' => 'Madeira Beach',         'county' => 'Pinellas County'],
            ['name' => 'Indian Rocks Beach',    'county' => 'Pinellas County'],
            ['name' => 'Redington Beach',       'county' => 'Pinellas County'],
            ['name' => 'Belleair',              'county' => 'Pinellas County'],
            ['name' => 'Belleair Beach',        'county' => 'Pinellas County'],
            ['name' => 'Indian Shores',         'county' => 'Pinellas County'],
            ['name' => 'Redington Shores',      'county' => 'Pinellas County'],
            ['name' => 'North Redington Beach', 'county' => 'Pinellas County'],
            ['name' => 'Tarpon Springs',        'county' => 'Pinellas County'],
            ['name' => 'Oldsmar',               'county' => 'Pinellas County'],
            ['name' => 'Ozona',                 'county' => 'Pinellas County'],
            ['name' => 'Palm Harbor',           'county' => 'Pinellas County'],
            ['name' => 'Clearwater Beach',      'county' => 'Pinellas County'],
            ['name' => 'St. Pete Beach',        'county' => 'Pinellas County'],
            ['name' => 'Gulfport',              'county' => 'Pinellas County'],
            ['name' => 'Kenneth City',          'county' => 'Pinellas County'],
            ['name' => 'New Port Richey',       'county' => 'Pasco County'],
            ['name' => 'Port Richey',           'county' => 'Pasco County'],
            ['name' => 'Holiday',               'county' => 'Pasco County'],
            ['name' => 'Land O Lakes',          'county' => 'Pasco County'],
            ['name' => 'Zephyrhills',           'county' => 'Pasco County'],
            ['name' => 'Dade City',             'county' => 'Pasco County'],
            ['name' => 'Wesley Chapel',         'county' => 'Pasco County'],
            ['name' => 'New Port Richey East',  'county' => 'Pasco County'],
            ['name' => 'Brandon',               'county' => 'Hillsborough County'],
            ['name' => 'Riverview',             'county' => 'Hillsborough County'],
            ['name' => 'Ruskin',                'county' => 'Hillsborough County'],
            ['name' => 'Plant City',            'county' => 'Hillsborough County'],
            ['name' => 'Temple Terrace',        'county' => 'Hillsborough County'],
            ['name' => 'Apollo Beach',          'county' => 'Hillsborough County'],
            ['name' => 'Sun City Center',       'county' => 'Hillsborough County'],
            ['name' => 'Lutz',                  'county' => 'Hillsborough County'],
            ['name' => 'Odessa',                'county' => 'Hillsborough County'],
            ['name' => 'Valrico',               'county' => 'Hillsborough County'],
            ['name' => 'Gibsonton',             'county' => 'Hillsborough County'],
            ['name' => 'Wimauma',               'county' => 'Hillsborough County'],
            ['name' => 'Lithia',                'county' => 'Hillsborough County'],
            ['name' => 'Seffner',               'county' => 'Hillsborough County'],
            ['name' => 'Mango',                 'county' => 'Hillsborough County'],
            ['name' => 'Palmetto',              'county' => 'Manatee County'],
            ['name' => 'Bradenton Beach',       'county' => 'Manatee County'],
            ['name' => 'Ellenton',              'county' => 'Manatee County'],
        ];

        $added = 0;
        $countyUpdated = 0;
        $skipped = 0;

        foreach ($patchCities as $entry) {
            $countyId = $countyIds[$entry['county']] ?? null;

            $existing = DB::table('us_cities')
                ->where('name', $entry['name'])
                ->where('state_id', $flStateId)
                ->first();

            if ($existing) {
                if ($countyId && $existing->county_id !== $countyId) {
                    DB::table('us_cities')
                        ->where('id', $existing->id)
                        ->update(['county_id' => $countyId, 'updated_at' => $now]);
                    $countyUpdated++;
                } else {
                    $skipped++;
                }
                continue;
            }

            DB::table('us_cities')->insertOrIgnore([
                'name'       => $entry['name'],
                'fips_code'  => null,
                'state_id'   => $flStateId,
                'county_id'  => $countyId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $added++;
        }

        $this->command->info("Florida patch complete — added: {$added}, county updated: {$countyUpdated}, already correct: {$skipped}.");

        $this->addMissingZipCodes();
    }

    private function addMissingZipCodes(): void
    {
        $this->command->info('Adding missing Florida zip code entries for county fallback...');

        $zips = [
            ['zip_code' => '33706', 'city' => 'Treasure Island',    'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '34698', 'city' => 'Dunedin',             'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '34695', 'city' => 'Safety Harbor',       'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '33708', 'city' => 'Madeira Beach',       'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '33707', 'city' => 'Gulfport',            'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '34689', 'city' => 'Tarpon Springs',      'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '33786', 'city' => 'Belleair Beach',      'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '33756', 'city' => 'Belleair',            'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '33785', 'city' => 'Indian Rocks Beach',  'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '34683', 'city' => 'Palm Harbor',         'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '33703', 'city' => 'St. Pete Beach',      'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '33740', 'city' => 'Redington Beach',     'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pinellas'],
            ['zip_code' => '34668', 'city' => 'Port Richey',         'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pasco'],
            ['zip_code' => '34652', 'city' => 'New Port Richey',     'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pasco'],
            ['zip_code' => '34690', 'city' => 'Holiday',             'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pasco'],
            ['zip_code' => '34637', 'city' => 'Land O Lakes',        'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pasco'],
            ['zip_code' => '34654', 'city' => 'Wesley Chapel',       'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Pasco'],
            ['zip_code' => '34221', 'city' => 'Palmetto',            'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Manatee'],
            ['zip_code' => '34222', 'city' => 'Ellenton',            'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Manatee'],
            ['zip_code' => '34209', 'city' => 'Bradenton Beach',     'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Manatee'],
            ['zip_code' => '33510', 'city' => 'Brandon',             'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Hillsborough'],
            ['zip_code' => '33594', 'city' => 'Valrico',             'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Hillsborough'],
            ['zip_code' => '33556', 'city' => 'Odessa',              'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Hillsborough'],
            ['zip_code' => '33549', 'city' => 'Lutz',                'state_abbrev' => 'FL', 'state_name' => 'Florida', 'county' => 'Hillsborough'],
        ];

        $zipAdded = 0;
        $now = now();
        foreach ($zips as $zip) {
            $exists = DB::table('us_zip_codes')->where('zip_code', $zip['zip_code'])->exists();
            if (!$exists) {
                DB::table('us_zip_codes')->insertOrIgnore(array_merge($zip, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $zipAdded++;
            }
        }

        $this->command->info("Zip code patch complete — added {$zipAdded} missing zip entries.");
    }
}
