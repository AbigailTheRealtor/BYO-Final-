<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Database\Seeders\FlCitiesPatchSeeder;
use Database\Seeders\UsCitiesExpandedSeeder;
use Database\Seeders\FixCityCountyMappings;

class ImportCensusCities extends Command
{
    protected $signature = 'cities:import-census {--dry-run : Report how many rows would be added/updated without committing any changes}';

    protected $description = 'Import comprehensive U.S. city data from Census Gazetteer and fix county mappings.';

    public function handle()
    {
        if ($this->option('dry-run')) {
            return $this->dryRun();
        }

        return $this->liveRun();
    }

    private function liveRun(): int
    {
        $before = DB::table('us_cities')->count();
        $this->info("Starting city import. Current city count: {$before}");
        $this->newLine();

        $this->info('Step 1/3: Florida patch seeder (hardcoded critical cities)...');
        $this->callSeeder(FlCitiesPatchSeeder::class);
        $afterPatch = DB::table('us_cities')->count();
        $this->info("After FL patch: {$afterPatch} cities (+" . ($afterPatch - $before) . ")");
        $this->newLine();

        $this->info('Step 2/3: Census Gazetteer import (nationwide)...');
        $this->callSeeder(UsCitiesExpandedSeeder::class);
        $afterCensus = DB::table('us_cities')->count();
        $this->info("After Census import: {$afterCensus} cities (+" . ($afterCensus - $afterPatch) . ")");
        $this->newLine();

        $this->info('Step 3/3: Fixing city-to-county mappings...');
        $this->callSeeder(FixCityCountyMappings::class);
        $this->newLine();

        $after      = DB::table('us_cities')->count();
        $withCounty = DB::table('us_cities')->whereNotNull('county_id')->count();

        $this->info('=== Import Complete ===');
        $this->info("Cities before:   {$before}");
        $this->info("Cities after:    {$after}");
        $this->info("Net added:       " . ($after - $before));
        $this->info("With county_id:  {$withCounty} / {$after}");
        $this->newLine();

        $this->runAcceptanceCheck();

        return 0;
    }

    private function dryRun(): int
    {
        $this->info('[DRY RUN] Simulating import inside a rolled-back transaction — no data will be committed.');
        $this->newLine();

        $before = DB::table('us_cities')->count();
        $beforeCounty = DB::table('us_cities')->whereNotNull('county_id')->count();
        $this->info("Current state: {$before} cities, {$beforeCounty} with county_id.");
        $this->newLine();

        $afterPatch  = null;
        $afterCensus = null;
        $afterCounty = null;

        try {
            DB::beginTransaction();

            $this->info('[DRY RUN] Step 1/3: Florida patch seeder...');
            $this->callSeeder(FlCitiesPatchSeeder::class);
            $afterPatch = DB::table('us_cities')->count();

            $this->info('[DRY RUN] Step 2/3: Census Gazetteer import...');
            $this->callSeeder(UsCitiesExpandedSeeder::class);
            $afterCensus = DB::table('us_cities')->count();

            $this->info('[DRY RUN] Step 3/3: County mapping...');
            $this->callSeeder(FixCityCountyMappings::class);
            $afterCounty = DB::table('us_cities')->whereNotNull('county_id')->count();

            DB::rollBack();
            $this->newLine();
            $this->info('[DRY RUN] Transaction rolled back — database unchanged.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Dry run failed: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('=== Dry Run Results ===');
        $this->info("Cities before:              {$before}");
        $this->info("After FL patch (would be):  {$afterPatch} (+" . ($afterPatch - $before) . ")");
        $this->info("After Census import:         {$afterCensus} (+" . ($afterCensus - $afterPatch) . ")");
        $this->info("Net cities added:            " . ($afterCensus - $before));
        $this->info("County mappings (before):    {$beforeCounty}");
        $this->info("County mappings (after):     {$afterCounty} (+" . ($afterCounty - $beforeCounty) . ")");
        $this->info('Run without --dry-run to commit these changes.');

        return 0;
    }

    private function callSeeder(string $seederClass): void
    {
        $seeder = new $seederClass();
        $seeder->setCommand($this);
        $seeder->run();
    }

    private function runAcceptanceCheck(): void
    {
        $this->info('Running acceptance check on 15 test cities...');

        $testCities = [
            ['name' => 'Treasure Island',   'state' => 'FL'],
            ['name' => 'Dunedin',            'state' => 'FL'],
            ['name' => 'Safety Harbor',      'state' => 'FL'],
            ['name' => 'Seminole',           'state' => 'FL'],
            ['name' => 'Madeira Beach',      'state' => 'FL'],
            ['name' => 'Indian Rocks Beach', 'state' => 'FL'],
            ['name' => 'Largo',              'state' => 'FL'],
            ['name' => 'St. Petersburg',     'state' => 'FL'],
            ['name' => 'Tampa',              'state' => 'FL'],
            ['name' => 'Clearwater',         'state' => 'FL'],
            ['name' => 'New York',           'state' => 'NY'],
            ['name' => 'Los Angeles',        'state' => 'CA'],
            ['name' => 'Chicago',            'state' => 'IL'],
            ['name' => 'Dallas',             'state' => 'TX'],
            ['name' => 'Atlanta',            'state' => 'GA'],
        ];

        $rows    = [];
        $allPass = true;
        foreach ($testCities as $test) {
            $stateId = DB::table('us_states')->where('abbreviation', $test['state'])->value('id');
            $city    = DB::table('us_cities')
                ->where('name', 'ILIKE', $test['name'])
                ->where('state_id', $stateId)
                ->first();

            $pass      = $city !== null;
            $hasCounty = $city && $city->county_id;
            if (!$pass) $allPass = false;

            $rows[] = [
                $test['name'] . ', ' . $test['state'],
                $pass ? 'PASS' : 'FAIL',
                $hasCounty ? 'YES' : 'NO',
            ];
        }

        $this->table(['City', 'In DB', 'Has County'], $rows);

        if ($allPass) {
            $this->info('All 15 test cities PASSED.');
        } else {
            $this->warn('Some test cities are missing. Consider re-running after Census download completes.');
        }
    }
}
