<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\UsState;

class UsCitiesExpandedSeeder extends Seeder
{
    private const ZIP_URL = 'https://www2.census.gov/geo/docs/maps-data/data/gazetteer/2024_Gazetteer/2024_Gaz_place_national.zip';
    private const ZIP_PATH = '/tmp/census_places.zip';
    private const EXTRACT_PATH = '/tmp/census_places/';
    private const FILE_PATH = '/tmp/census_places/2024_Gaz_place_national.txt';

    public function run()
    {
        $this->command->info('Importing expanded U.S. cities from Census Gazetteer data...');

        if (!file_exists(self::FILE_PATH)) {
            $this->command->info('Census file not found. Attempting download...');
            $this->downloadAndExtract();
        }

        if (!file_exists(self::FILE_PATH)) {
            $this->command->error('Census places file not available. Skipping expanded import.');
            $this->command->info('You can manually place the file at: ' . self::FILE_PATH);
            return;
        }

        $states = UsState::pluck('id', 'abbreviation')->toArray();

        if (empty($states)) {
            $this->command->error('No states found in database. Run UsStatesSeeder first.');
            return;
        }

        $before = DB::table('us_cities')->count();
        $this->command->info("Cities before import: {$before}");

        $handle = fopen(self::FILE_PATH, 'r');
        if (!$handle) {
            $this->command->error('Could not open census file for reading.');
            return;
        }

        fgetcsv($handle, 0, "\t");

        $batch = [];
        $processed = 0;
        $skippedState = 0;
        $now = now();

        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            if (count($row) < 4) continue;

            $stateAbbr = trim($row[0]);
            $geoid     = trim($row[1]);
            $name      = trim($row[3]);

            $cleanName = preg_replace(
                '/\s+(city|town|village|CDP|borough|municipality|census designated place|unified government|metro government|consolidated government|urban county)$/i',
                '',
                $name
            );
            $cleanName = trim($cleanName);

            if (!isset($states[$stateAbbr])) {
                $skippedState++;
                continue;
            }

            $batch[] = [
                'name'       => $cleanName,
                'fips_code'  => $geoid,
                'state_id'   => $states[$stateAbbr],
                'county_id'  => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $processed++;

            if (count($batch) >= 500) {
                DB::table('us_cities')->insertOrIgnore($batch);
                $batch = [];
                if ($processed % 5000 === 0) {
                    $this->command->info("Processed {$processed} records...");
                }
            }
        }

        if (!empty($batch)) {
            DB::table('us_cities')->insertOrIgnore($batch);
        }

        fclose($handle);

        $after = DB::table('us_cities')->count();
        $added = $after - $before;

        $this->command->info("Census import complete.");
        $this->command->info("  Processed: {$processed} records");
        $this->command->info("  Skipped (unknown state): {$skippedState}");
        $this->command->info("  Added to DB: {$added} new cities");
        $this->command->info("  Total cities now: {$after}");
    }

    private function downloadAndExtract(): void
    {
        @mkdir(self::EXTRACT_PATH, 0755, true);

        $this->command->info('Downloading: ' . self::ZIP_URL);

        $context = stream_context_create([
            'http' => [
                'timeout'    => 60,
                'user_agent' => 'Mozilla/5.0 (compatible; CensusImporter/1.0)',
            ],
        ]);

        $zipContent = @file_get_contents(self::ZIP_URL, false, $context);
        if ($zipContent === false) {
            $this->command->error('Download failed. Check network connectivity.');
            return;
        }

        file_put_contents(self::ZIP_PATH, $zipContent);
        $this->command->info('Download complete (' . round(strlen($zipContent) / 1024) . ' KB). Extracting...');

        $zip = new \ZipArchive();
        if ($zip->open(self::ZIP_PATH) === true) {
            $zip->extractTo(self::EXTRACT_PATH);
            $zip->close();
            $this->command->info('Extraction complete.');
        } else {
            $this->command->error('Failed to extract zip archive.');
        }

        @unlink(self::ZIP_PATH);
    }
}
