<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixCityCountyMappings extends Seeder
{
    public function run()
    {
        $censusFile = '/tmp/national_places_2020.txt';
        
        if (!file_exists($censusFile)) {
            $this->command->error("Census file not found at: $censusFile");
            $this->command->info("Please run: curl -s 'https://www2.census.gov/geo/docs/reference/codes2020/national_place2020.txt' -o /tmp/national_places_2020.txt");
            return;
        }

        $this->command->info("Loading Census data...");
        
        $stateCache = [];
        $states = DB::table('us_states')->get();
        foreach ($states as $state) {
            $stateCache[$state->abbreviation] = $state->id;
        }

        $countyCache = [];
        $counties = DB::table('us_counties')->get();
        foreach ($counties as $county) {
            $countyName = strtolower(trim($county->name));
            $countyCache[$county->state_id][$countyName] = $county->id;
        }

        $censusData = [];
        $handle = fopen($censusFile, 'r');
        $header = fgetcsv($handle, 0, '|');
        
        while (($row = fgetcsv($handle, 0, '|')) !== false) {
            if (count($row) < 9) continue;
            
            $stateAbbr = $row[0];
            $placeName = $row[4];
            $countiesStr = $row[8];
            
            $counties = explode('~~~', $countiesStr);
            $primaryCounty = trim($counties[0]);
            
            $cityName = preg_replace('/\s+(city|town|CDP|village|borough|municipality)$/i', '', $placeName);
            $cityName = strtolower(trim($cityName));
            
            if (!isset($censusData[$stateAbbr])) {
                $censusData[$stateAbbr] = [];
            }
            $censusData[$stateAbbr][$cityName] = strtolower($primaryCounty);
        }
        fclose($handle);
        
        $this->command->info("Loaded " . count($censusData) . " states from Census data");

        $updated = 0;
        $notFound = 0;
        $alreadyCorrect = 0;
        $countyNotFound = 0;

        $cities = DB::table('us_cities')
            ->join('us_states', 'us_cities.state_id', '=', 'us_states.id')
            ->select('us_cities.*', 'us_states.abbreviation as state_abbr')
            ->get();

        $this->command->info("Processing " . count($cities) . " cities...");

        $bar = $this->command->getOutput()->createProgressBar(count($cities));

        foreach ($cities as $city) {
            $bar->advance();
            
            $cityNameClean = preg_replace('/,\s*[A-Z]{2}$/i', '', $city->name);
            $cityNameClean = strtolower(trim($cityNameClean));
            
            $stateAbbr = $city->state_abbr;
            
            if (!isset($censusData[$stateAbbr]) || !isset($censusData[$stateAbbr][$cityNameClean])) {
                $notFound++;
                continue;
            }
            
            $correctCountyName = $censusData[$stateAbbr][$cityNameClean];
            $stateId = $stateCache[$stateAbbr] ?? null;
            
            if (!$stateId || !isset($countyCache[$stateId])) {
                $countyNotFound++;
                continue;
            }
            
            $correctCountyId = $countyCache[$stateId][$correctCountyName] ?? null;
            
            if (!$correctCountyId) {
                $countyNotFound++;
                continue;
            }
            
            if ($city->county_id == $correctCountyId) {
                $alreadyCorrect++;
                continue;
            }
            
            DB::table('us_cities')
                ->where('id', $city->id)
                ->update(['county_id' => $correctCountyId]);
            
            $updated++;
        }

        $bar->finish();
        $this->command->newLine(2);
        
        $this->command->info("=== City-to-County Mapping Fix Complete ===");
        $this->command->info("Updated: $updated");
        $this->command->info("Already correct: $alreadyCorrect");
        $this->command->info("City not in Census data: $notFound");
        $this->command->info("County not found in DB: $countyNotFound");
    }
}
