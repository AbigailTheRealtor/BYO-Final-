<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UsZipCodesSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Downloading US ZIP codes data...');
        
        $url = 'https://raw.githubusercontent.com/millbj92/US-Zip-Codes-JSON/master/USCities.json';
        
        $response = Http::timeout(120)->get($url);
        
        if (!$response->successful()) {
            $this->command->error('Failed to download ZIP code database. Trying alternative source...');
            $this->loadFromCsvSource();
            return;
        }
        
        $this->command->info('Processing ZIP codes...');
        
        DB::table('us_zip_codes')->truncate();
        
        $cities = json_decode($response->body(), true);
        
        $stateAbbrevs = $this->getStateAbbreviations();
        
        $batch = [];
        $count = 0;
        $batchSize = 500;
        $seen = [];
        
        foreach ($cities as $city) {
            $zipCode = $city['zip_code'] ?? '';
            
            if (empty($zipCode) || isset($seen[$zipCode])) continue;
            $seen[$zipCode] = true;
            
            $stateName = $city['state'] ?? '';
            $stateAbbrev = $stateAbbrevs[$stateName] ?? $stateName;
            
            if (strlen($stateAbbrev) > 2) {
                continue;
            }
            
            $batch[] = [
                'zip_code' => $zipCode,
                'city' => $city['city'] ?? '',
                'state_abbrev' => $stateAbbrev,
                'state_name' => $stateName,
                'county' => $city['county'] ?? null,
                'latitude' => $city['latitude'] ?? null,
                'longitude' => $city['longitude'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            if (count($batch) >= $batchSize) {
                try {
                    DB::table('us_zip_codes')->insert($batch);
                    $count += count($batch);
                    $this->command->info("Inserted $count ZIP codes...");
                } catch (\Exception $e) {
                    $this->command->warn("Skipping batch due to duplicate: " . $e->getMessage());
                }
                $batch = [];
            }
        }
        
        if (!empty($batch)) {
            try {
                DB::table('us_zip_codes')->insert($batch);
                $count += count($batch);
            } catch (\Exception $e) {
                $this->command->warn("Skipping final batch: " . $e->getMessage());
            }
        }
        
        $this->command->info("Successfully loaded $count ZIP codes.");
    }
    
    protected function loadFromCsvSource()
    {
        $this->command->info('Loading from alternative CSV source...');
        
        $url = 'http://download.geonames.org/export/zip/US.zip';
        $zipPath = storage_path('app/us_zip.zip');
        $extractPath = storage_path('app/us_zip');
        $txtPath = $extractPath . '/US.txt';
        
        $response = Http::timeout(120)->get($url);
        
        if (!$response->successful()) {
            $this->command->error('Failed to download from GeoNames.');
            return;
        }
        
        file_put_contents($zipPath, $response->body());
        
        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }
        
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            $this->command->error('Failed to extract ZIP file.');
            return;
        }
        
        if (!file_exists($txtPath)) {
            $this->command->error('US.txt not found in archive.');
            return;
        }
        
        DB::table('us_zip_codes')->truncate();
        
        $handle = fopen($txtPath, 'r');
        $batch = [];
        $count = 0;
        $batchSize = 500;
        $seen = [];
        
        while (($line = fgets($handle)) !== false) {
            $parts = explode("\t", $line);
            
            if (count($parts) < 5) continue;
            
            $zipCode = trim($parts[1]);
            
            if (isset($seen[$zipCode])) continue;
            $seen[$zipCode] = true;
            
            $batch[] = [
                'zip_code' => $zipCode,
                'city' => trim($parts[2]),
                'state_abbrev' => trim($parts[4]),
                'state_name' => trim($parts[3]),
                'county' => trim($parts[5] ?? ''),
                'latitude' => floatval($parts[9] ?? 0),
                'longitude' => floatval($parts[10] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            if (count($batch) >= $batchSize) {
                try {
                    DB::table('us_zip_codes')->insert($batch);
                    $count += count($batch);
                } catch (\Exception $e) {
                    $this->command->warn("Skipping batch: " . $e->getMessage());
                }
                $batch = [];
            }
        }
        
        if (!empty($batch)) {
            try {
                DB::table('us_zip_codes')->insert($batch);
                $count += count($batch);
            } catch (\Exception $e) {
                $this->command->warn("Skipping final batch.");
            }
        }
        
        fclose($handle);
        $this->command->info("Loaded $count ZIP codes from GeoNames.");
    }
    
    protected function getStateAbbreviations()
    {
        return [
            'Alabama' => 'AL', 'Alaska' => 'AK', 'Arizona' => 'AZ', 'Arkansas' => 'AR',
            'California' => 'CA', 'Colorado' => 'CO', 'Connecticut' => 'CT', 'Delaware' => 'DE',
            'Florida' => 'FL', 'Georgia' => 'GA', 'Hawaii' => 'HI', 'Idaho' => 'ID',
            'Illinois' => 'IL', 'Indiana' => 'IN', 'Iowa' => 'IA', 'Kansas' => 'KS',
            'Kentucky' => 'KY', 'Louisiana' => 'LA', 'Maine' => 'ME', 'Maryland' => 'MD',
            'Massachusetts' => 'MA', 'Michigan' => 'MI', 'Minnesota' => 'MN', 'Mississippi' => 'MS',
            'Missouri' => 'MO', 'Montana' => 'MT', 'Nebraska' => 'NE', 'Nevada' => 'NV',
            'New Hampshire' => 'NH', 'New Jersey' => 'NJ', 'New Mexico' => 'NM', 'New York' => 'NY',
            'North Carolina' => 'NC', 'North Dakota' => 'ND', 'Ohio' => 'OH', 'Oklahoma' => 'OK',
            'Oregon' => 'OR', 'Pennsylvania' => 'PA', 'Rhode Island' => 'RI', 'South Carolina' => 'SC',
            'South Dakota' => 'SD', 'Tennessee' => 'TN', 'Texas' => 'TX', 'Utah' => 'UT',
            'Vermont' => 'VT', 'Virginia' => 'VA', 'Washington' => 'WA', 'West Virginia' => 'WV',
            'Wisconsin' => 'WI', 'Wyoming' => 'WY', 'District of Columbia' => 'DC',
            'Puerto Rico' => 'PR', 'Virgin Islands' => 'VI', 'Guam' => 'GU',
        ];
    }
}
