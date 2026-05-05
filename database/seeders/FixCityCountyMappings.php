<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixCityCountyMappings extends Seeder
{
    private const CENSUS_URL  = 'https://www2.census.gov/geo/docs/reference/codes2020/national_place2020.txt';
    private const CENSUS_FILE = '/tmp/national_places_2020.txt';

    public function run()
    {
        $this->command->info('Fixing city-to-county mappings...');

        $censusMap = $this->buildCensusMap();
        $usingCensus = !empty($censusMap);

        if ($usingCensus) {
            $this->command->info('Using Census 2020 national places data for county mapping.');
        } else {
            $this->command->warn('Census data unavailable — falling back to us_zip_codes county data.');
        }

        $stateAbbrToId = DB::table('us_states')->pluck('id', 'abbreviation')->toArray();

        $countyCache = [];
        $counties = DB::table('us_counties')->get();
        foreach ($counties as $county) {
            $normalized = strtolower(preg_replace('/\s+county$/i', '', trim($county->name)));
            $countyCache[$county->state_id][$normalized] = $county->id;
        }

        $zipCountyMap = [];
        if (!$usingCensus) {
            $zipRows = DB::table('us_zip_codes')
                ->whereNotNull('county')
                ->where('county', '!=', '')
                ->whereNotNull('state_abbrev')
                ->select('city', 'state_abbrev', 'county')
                ->get();

            foreach ($zipRows as $zip) {
                $stateId = $stateAbbrToId[strtoupper(trim($zip->state_abbrev))] ?? null;
                if (!$stateId) continue;
                $cityKey   = strtolower(trim($zip->city));
                $countyNorm = strtolower(preg_replace('/\s+county$/i', '', trim($zip->county)));
                $zipCountyMap[$stateId][$cityKey][] = $countyNorm;
            }
        }

        $cities = DB::table('us_cities')
            ->join('us_states', 'us_cities.state_id', '=', 'us_states.id')
            ->select('us_cities.id', 'us_cities.name', 'us_cities.county_id', 'us_cities.state_id', 'us_states.abbreviation as state_abbr')
            ->get();

        $this->command->info("Processing " . $cities->count() . " cities...");

        $updated     = 0;
        $alreadySet  = 0;
        $noMatch     = 0;
        $noCountyRow = 0;

        $bar = $this->command->getOutput()->createProgressBar($cities->count());
        $bar->start();

        foreach ($cities as $city) {
            $bar->advance();

            $cityKey = strtolower(trim($city->name));
            $stateId = $city->state_id;
            $stateAbbr = strtoupper($city->state_abbr);

            $countyNorm = null;

            if ($usingCensus) {
                $countyNorm = $censusMap[$stateAbbr][$cityKey] ?? null;
            }

            if ($countyNorm === null && !$usingCensus) {
                if (isset($zipCountyMap[$stateId][$cityKey])) {
                    $counts = array_count_values($zipCountyMap[$stateId][$cityKey]);
                    arsort($counts);
                    $countyNorm = array_key_first($counts);
                }
            }

            if ($countyNorm === null) {
                $noMatch++;
                continue;
            }

            $countyId = $countyCache[$stateId][$countyNorm] ?? null;

            if (!$countyId) {
                $noCountyRow++;
                continue;
            }

            if ((int) $city->county_id === (int) $countyId) {
                $alreadySet++;
                continue;
            }

            DB::table('us_cities')
                ->where('id', $city->id)
                ->update(['county_id' => $countyId, 'updated_at' => now()]);

            $updated++;
        }

        $bar->finish();
        $this->command->newLine(2);

        $this->command->info('=== City-to-County Mapping Results ===');
        $this->command->info("Strategy:             " . ($usingCensus ? 'Census 2020 national places' : 'us_zip_codes fallback'));
        $this->command->info("Updated:              {$updated}");
        $this->command->info("Already correct:      {$alreadySet}");
        $this->command->info("No source match:      {$noMatch}");
        $this->command->info("County not in DB:     {$noCountyRow}");
        $this->command->info("Total cities:         " . $cities->count());
    }

    private function buildCensusMap(): array
    {
        if (!file_exists(self::CENSUS_FILE)) {
            $this->command->info('Downloading Census 2020 national places file...');
            $this->downloadCensusFile();
        }

        if (!file_exists(self::CENSUS_FILE)) {
            return [];
        }

        $map    = [];
        $handle = fopen(self::CENSUS_FILE, 'r');
        if (!$handle) return [];

        fgetcsv($handle, 0, '|');

        while (($row = fgetcsv($handle, 0, '|')) !== false) {
            if (count($row) < 9) continue;

            $stateAbbr   = strtoupper(trim($row[0]));
            $placeName   = trim($row[4]);
            $countiesStr = trim($row[8]);

            $cityName = preg_replace(
                '/\s+(city|town|CDP|village|borough|municipality|unified government|metro government|consolidated government|urban county|census designated place|incorporated place)$/i',
                '',
                $placeName
            );
            $cityKey = strtolower(trim($cityName));

            $counties = explode('~~~', $countiesStr);
            $primaryCounty = strtolower(preg_replace('/\s+county$/i', '', trim($counties[0])));

            if ($cityKey && $primaryCounty) {
                $map[$stateAbbr][$cityKey] = $primaryCounty;
            }
        }

        fclose($handle);

        $stateCount = count($map);
        $placeCount = array_sum(array_map('count', $map));
        $this->command->info("Census data loaded: {$placeCount} places across {$stateCount} states.");

        return $map;
    }

    private function downloadCensusFile(): void
    {
        $context = stream_context_create([
            'http' => [
                'timeout'    => 60,
                'user_agent' => 'Mozilla/5.0 (compatible; CensusImporter/1.0)',
            ],
        ]);

        $content = @file_get_contents(self::CENSUS_URL, false, $context);
        if ($content === false) {
            $this->command->warn('Failed to download Census 2020 places file. Will use fallback.');
            return;
        }

        file_put_contents(self::CENSUS_FILE, $content);
        $this->command->info('Census 2020 places file downloaded (' . round(strlen($content) / 1024) . ' KB).');
    }
}
