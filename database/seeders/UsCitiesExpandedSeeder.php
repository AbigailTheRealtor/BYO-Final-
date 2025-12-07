<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\UsState;

class UsCitiesExpandedSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Importing expanded U.S. cities from Census data...');
        
        $filePath = '/tmp/census_places/2024_Gaz_place_national.txt';
        
        if (!file_exists($filePath)) {
            $this->command->info('Downloading Census places gazetteer file...');
            $this->downloadCensusData();
        }
        
        if (!file_exists($filePath)) {
            $this->command->error('Census places file not found and download failed.');
            return;
        }
        
        $states = UsState::pluck('id', 'abbreviation')->toArray();
        
        if (empty($states)) {
            $this->command->error('No states found in database. Run UsStatesSeeder first.');
            return;
        }
        
        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle, 0, "\t");
        
        $cities = [];
        $count = 0;
        $skipped = 0;
        
        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            if (count($row) < 4) continue;
            
            $stateAbbr = trim($row[0]);
            $geoid = trim($row[1]);
            $ansicode = trim($row[2]);
            $name = trim($row[3]);
            
            $cleanName = preg_replace('/ (city|town|village|CDP|borough|municipality|census designated place)$/i', '', $name);
            
            if (!isset($states[$stateAbbr])) {
                $skipped++;
                continue;
            }
            
            $cities[] = [
                'name' => $cleanName,
                'fips_code' => $geoid,
                'state_id' => $states[$stateAbbr],
                'county_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            $count++;
            
            if (count($cities) >= 1000) {
                DB::table('us_cities')->insertOrIgnore($cities);
                $cities = [];
                $this->command->info("Processed {$count} cities...");
            }
        }
        
        if (!empty($cities)) {
            DB::table('us_cities')->insertOrIgnore($cities);
        }
        
        fclose($handle);
        
        $this->command->info("Imported {$count} cities. Skipped {$skipped} (unknown states).");
        
        $totalCities = DB::table('us_cities')->count();
        $this->command->info("Total cities in database: {$totalCities}");
    }
    
    private function downloadCensusData()
    {
        $zipUrl = 'https://www2.census.gov/geo/docs/maps-data/data/gazetteer/2024_Gazetteer/2024_Gaz_place_national.zip';
        $zipPath = '/tmp/census_places.zip';
        $extractPath = '/tmp/census_places/';
        
        @mkdir($extractPath, 0755, true);
        
        $zipContent = @file_get_contents($zipUrl);
        if ($zipContent === false) {
            $this->command->error('Failed to download Census places data.');
            return;
        }
        
        file_put_contents($zipPath, $zipContent);
        
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();
            $this->command->info('Census places data downloaded and extracted.');
        } else {
            $this->command->error('Failed to extract Census places zip file.');
        }
    }
}
