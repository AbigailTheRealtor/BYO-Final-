<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\UsState;

class UsCountiesExpandedSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Importing expanded U.S. counties from Census data...');
        
        $filePath = '/tmp/census_counties/2024_Gaz_counties_national.txt';
        
        if (!file_exists($filePath)) {
            $this->command->info('Downloading Census counties gazetteer file...');
            $this->downloadCensusData();
        }
        
        if (!file_exists($filePath)) {
            $this->command->error('Census counties file not found and download failed.');
            return;
        }
        
        $states = UsState::pluck('id', 'abbreviation')->toArray();
        
        if (empty($states)) {
            $this->command->error('No states found in database. Run UsStatesSeeder first.');
            return;
        }
        
        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle, 0, "\t");
        
        $counties = [];
        $count = 0;
        $skipped = 0;
        
        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            if (count($row) < 4) continue;
            
            $stateAbbr = trim($row[0]);
            $geoid = trim($row[1]);
            $ansicode = trim($row[2]);
            $name = trim($row[3]);
            
            if (!isset($states[$stateAbbr])) {
                $skipped++;
                continue;
            }
            
            $counties[] = [
                'name' => $name,
                'fips_code' => $geoid,
                'state_id' => $states[$stateAbbr],
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            $count++;
        }
        
        if (!empty($counties)) {
            DB::table('us_counties')->insertOrIgnore($counties);
        }
        
        fclose($handle);
        
        $this->command->info("Imported {$count} counties. Skipped {$skipped} (unknown states).");
        
        $totalCounties = DB::table('us_counties')->count();
        $this->command->info("Total counties in database: {$totalCounties}");
    }
    
    private function downloadCensusData()
    {
        $zipUrl = 'https://www2.census.gov/geo/docs/maps-data/data/gazetteer/2024_Gazetteer/2024_Gaz_counties_national.zip';
        $zipPath = '/tmp/census_counties.zip';
        $extractPath = '/tmp/census_counties/';
        
        @mkdir($extractPath, 0755, true);
        
        $zipContent = @file_get_contents($zipUrl);
        if ($zipContent === false) {
            $this->command->error('Failed to download Census counties data.');
            return;
        }
        
        file_put_contents($zipPath, $zipContent);
        
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($extractPath);
            $zip->close();
            $this->command->info('Census counties data downloaded and extracted.');
        } else {
            $this->command->error('Failed to extract Census counties zip file.');
        }
    }
}
