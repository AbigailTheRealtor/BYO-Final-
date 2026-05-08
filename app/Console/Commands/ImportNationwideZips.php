<?php

namespace App\Console\Commands;

use App\Services\CityNameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportNationwideZips extends Command
{
    protected $signature = 'zips:import-nationwide
                            {--dry-run : Report changes before any rows are committed}
                            {--source= : Path to GeoNames US.zip or US.txt file (downloads if omitted)}';

    protected $description = 'Expand nationwide ZIP coverage and populate us_zip_code_cities alias table from GeoNames data.';

    private const GEONAMES_URL = 'https://download.geonames.org/export/zip/US.zip';

    public function handle(): int
    {
        $isDryRun   = $this->option('dry-run');
        $sourcePath = $this->option('source');

        if ($isDryRun) {
            $this->info('[DRY RUN] Simulating import inside a rolled-back transaction — no data will be committed.');
        }

        $txtPath = $this->resolveSourceFile($sourcePath);
        if (!$txtPath) {
            return 1;
        }

        $beforeZips    = DB::table('us_zip_codes')->count();
        $beforeAliases = DB::table('us_zip_code_cities')->count();

        $this->info("Current state: {$beforeZips} ZIP rows in us_zip_codes, {$beforeAliases} rows in us_zip_code_cities.");
        $this->newLine();

        if ($isDryRun) {
            return $this->dryRun($txtPath, $beforeZips, $beforeAliases);
        }

        return $this->liveRun($txtPath, $beforeZips, $beforeAliases);
    }

    // -------------------------------------------------------------------------
    // Source file resolution
    // -------------------------------------------------------------------------

    private function resolveSourceFile(?string $sourcePath): ?string
    {
        if ($sourcePath) {
            if (str_ends_with(strtolower($sourcePath), '.zip')) {
                return $this->extractZipFile($sourcePath);
            }
            if (file_exists($sourcePath)) {
                $this->info("Using provided source: {$sourcePath}");
                return $sourcePath;
            }
            $this->error("Source file not found: {$sourcePath}");
            return null;
        }

        $this->info('No --source provided. Downloading from GeoNames...');
        return $this->downloadAndExtract();
    }

    private function downloadAndExtract(): ?string
    {
        $zipPath     = storage_path('app/us_zip_geonames.zip');
        $extractPath = storage_path('app/us_zip_geonames');

        $this->info('Downloading ' . self::GEONAMES_URL . ' ...');

        $ch = curl_init(self::GEONAMES_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            $this->error("Download failed (HTTP {$status}): {$error}");
            return null;
        }

        file_put_contents($zipPath, $body);
        $this->info('Download complete (' . round(strlen($body) / 1024) . ' KB). Extracting...');

        return $this->extractZipFile($zipPath, $extractPath);
    }

    private function extractZipFile(string $zipPath, ?string $extractPath = null): ?string
    {
        $extractPath = $extractPath ?? dirname($zipPath) . '/us_zip_extracted_' . time();

        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->error("Failed to open ZIP archive: {$zipPath}");
            return null;
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $txtPath = $extractPath . '/US.txt';
        if (!file_exists($txtPath)) {
            $this->error("US.txt not found in archive at {$extractPath}");
            return null;
        }

        $this->info("Extracted to: {$txtPath}");
        return $txtPath;
    }

    // -------------------------------------------------------------------------
    // Streaming parser
    // -------------------------------------------------------------------------

    /**
     * Open the GeoNames US.txt file and stream rows through a callback.
     *
     * Streams line-by-line — the entire file is never held in memory at once.
     * This is important for large source files (US.txt is ~41K rows).
     *
     * The callback receives:
     *   string $zip, string $city, string $stateName, string $stateAbbr,
     *   string $county, float|null $lat, float|null $lng
     *
     * GeoNames US.txt format (tab-delimited):
     *   country_code, postal_code, place_name, admin_name1, admin_code1,
     *   admin_name2, admin_code2, admin_name3, admin_code3,
     *   latitude, longitude, accuracy
     *
     * Non-5-digit postal codes are skipped at parse time (format filter).
     * Note: US territories (PR, VI, GU) do have 5-digit ZIPs and pass this
     * filter — they are excluded at the valid-states lookup step in the caller,
     * which only accepts abbreviations present in us_states.
     *
     * @return array{parsed: int, malformed: int}
     */
    private function streamGeoNamesFile(string $txtPath, callable $callback): array
    {
        $handle = fopen($txtPath, 'r');
        if (!$handle) {
            $this->error("Cannot open file: {$txtPath}");
            return ['parsed' => 0, 'malformed' => 0];
        }

        $parsed    = 0;
        $malformed = 0;

        while (($line = fgets($handle)) !== false) {
            $parts = explode("\t", rtrim($line, "\r\n"));

            if (count($parts) < 6) {
                $malformed++;
                continue;
            }

            $zip       = trim($parts[1]);
            $city      = trim($parts[2]);
            $stateName = trim($parts[3]);
            $stateAbbr = trim($parts[4]);
            $county    = trim($parts[5]);
            $lat       = isset($parts[9])  ? (float) $parts[9]  : null;
            $lng       = isset($parts[10]) ? (float) $parts[10] : null;

            if (!preg_match('/^\d{5}$/', $zip)) {
                $malformed++;
                continue;
            }

            if (strlen($stateAbbr) !== 2) {
                $malformed++;
                continue;
            }

            $callback($zip, $city, $stateName, $stateAbbr, $county, $lat, $lng);
            $parsed++;
        }

        fclose($handle);

        if ($malformed > 0) {
            $this->warn("Skipped {$malformed} non-5-digit/malformed rows.");
        }

        return ['parsed' => $parsed, 'malformed' => $malformed];
    }

    // -------------------------------------------------------------------------
    // Dry run
    // -------------------------------------------------------------------------

    private function dryRun(string $txtPath, int $beforeZips, int $beforeAliases): int
    {
        $newZips       = 0;
        $newAliases    = 0;
        $conflicts     = 0;
        $missingStates = 0;

        try {
            DB::beginTransaction();

            // Keyed: zip_code => normalized_city_key  (so expansions don't create phantom aliases)
            $existingZips = DB::table('us_zip_codes')
                ->pluck('city', 'zip_code')
                ->map(fn($city) => CityNameNormalizer::normalize($city))
                ->toArray();

            // Set: "zip|normalized_city|state" — dedup key for aliases
            $existingAliases = DB::table('us_zip_code_cities')
                ->get(['zip_code', 'city', 'state_abbrev'])
                ->mapWithKeys(fn($r) => [
                    $r->zip_code . '|' . CityNameNormalizer::normalize($r->city) . '|' . $r->state_abbrev => true
                ])
                ->toArray();

            $validStates = DB::table('us_states')->pluck('id', 'abbreviation')->toArray();

            $stats = $this->streamGeoNamesFile($txtPath, function (
                string $zip, string $city, string $stateName, string $stateAbbr,
                string $county, ?float $lat, ?float $lng
            ) use (
                &$existingZips, &$existingAliases, &$validStates,
                &$newZips, &$newAliases, &$conflicts, &$missingStates
            ) {
                if (!isset($validStates[$stateAbbr])) {
                    $missingStates++;
                    return;
                }

                $normalizedIncoming = CityNameNormalizer::normalize($city);

                if (!array_key_exists($zip, $existingZips)) {
                    DB::table('us_zip_codes')->insert([
                        'zip_code'     => $zip,
                        'city'         => $city,
                        'state_abbrev' => $stateAbbr,
                        'state_name'   => $stateName,
                        'county'       => $county ?: null,
                        'latitude'     => $lat,
                        'longitude'    => $lng,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                    $existingZips[$zip] = $normalizedIncoming;
                    $newZips++;
                } elseif ($existingZips[$zip] !== $normalizedIncoming) {
                    $aliasKey = $zip . '|' . $normalizedIncoming . '|' . $stateAbbr;
                    if (!isset($existingAliases[$aliasKey])) {
                        DB::table('us_zip_code_cities')->insertOrIgnore([
                            'zip_code'     => $zip,
                            'city'         => $city,
                            'state_abbrev' => $stateAbbr,
                            'county'       => $county ?: null,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);
                        $existingAliases[$aliasKey] = true;
                        $newAliases++;
                    } else {
                        $conflicts++;
                    }
                }
            });

            $afterZips    = DB::table('us_zip_codes')->count();
            $afterAliases = DB::table('us_zip_code_cities')->count();

            DB::rollBack();

            $this->info('[DRY RUN] Transaction rolled back — database unchanged.');
            $this->newLine();
            $this->info('=== Dry Run Report ===');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Rows parsed from source',          $stats['parsed']],
                    ['ZIPs before',                      $beforeZips],
                    ['ZIPs after (would be)',            $afterZips],
                    ['New ZIPs to add',                  $newZips],
                    ['Aliases before',                   $beforeAliases],
                    ['Aliases after (would be)',         $afterAliases],
                    ['New alias mappings to add',        $newAliases],
                    ['Already-present aliases (deduped)', $conflicts],
                    ['Rows skipped (unknown state/territory)', $missingStates],
                ]
            );
            $this->info('Run without --dry-run to commit these changes.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Dry run failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    // -------------------------------------------------------------------------
    // Live run
    // -------------------------------------------------------------------------

    private function liveRun(string $txtPath, int $beforeZips, int $beforeAliases): int
    {
        $this->info('Starting live import...');

        // Keyed: zip_code => normalized_city_key
        $existingZips = DB::table('us_zip_codes')
            ->pluck('city', 'zip_code')
            ->map(fn($city) => CityNameNormalizer::normalize($city))
            ->toArray();

        $validStates = DB::table('us_states')->pluck('id', 'abbreviation')->toArray();

        // Set: "zip|normalized_city|state" — normalized dedup keys for aliases
        $existingAliases = DB::table('us_zip_code_cities')
            ->get(['zip_code', 'city', 'state_abbrev'])
            ->mapWithKeys(fn($r) => [
                $r->zip_code . '|' . CityNameNormalizer::normalize($r->city) . '|' . $r->state_abbrev => true
            ])
            ->toArray();

        $newZipsBatch    = [];
        $newAliasesBatch = [];
        $progressZips    = 0;
        $progressAliases = 0;
        $skipped         = 0;
        $missingStates   = 0;
        $batchSize       = 500;
        $now             = now();

        $flush = function () use (&$newZipsBatch, &$newAliasesBatch, &$progressZips, &$progressAliases) {
            if (!empty($newZipsBatch)) {
                DB::table('us_zip_codes')->insertOrIgnore($newZipsBatch);
                $progressZips += count($newZipsBatch);
                $newZipsBatch  = [];
            }
            if (!empty($newAliasesBatch)) {
                DB::table('us_zip_code_cities')->insertOrIgnore($newAliasesBatch);
                $progressAliases += count($newAliasesBatch);
                $newAliasesBatch  = [];
            }
        };

        $stats = $this->streamGeoNamesFile($txtPath, function (
            string $zip, string $city, string $stateName, string $stateAbbr,
            string $county, ?float $lat, ?float $lng
        ) use (
            &$existingZips, &$existingAliases, &$validStates,
            &$newZipsBatch, &$newAliasesBatch,
            &$skipped, &$missingStates, $batchSize, $now, $flush,
            &$progressZips, &$progressAliases
        ) {
            if (!isset($validStates[$stateAbbr])) {
                $missingStates++;
                return;
            }

            $normalizedIncoming = CityNameNormalizer::normalize($city);

            if (!array_key_exists($zip, $existingZips)) {
                $newZipsBatch[] = [
                    'zip_code'     => $zip,
                    'city'         => $city,
                    'state_abbrev' => $stateAbbr,
                    'state_name'   => $stateName,
                    'county'       => $county ?: null,
                    'latitude'     => $lat,
                    'longitude'    => $lng,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
                $existingZips[$zip] = $normalizedIncoming;
            } elseif ($existingZips[$zip] !== $normalizedIncoming) {
                $aliasKey = $zip . '|' . $normalizedIncoming . '|' . $stateAbbr;
                if (!isset($existingAliases[$aliasKey])) {
                    $newAliasesBatch[] = [
                        'zip_code'     => $zip,
                        'city'         => $city,
                        'state_abbrev' => $stateAbbr,
                        'county'       => $county ?: null,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                    $existingAliases[$aliasKey] = true;
                } else {
                    $skipped++;
                }
            }

            if (count($newZipsBatch) + count($newAliasesBatch) >= $batchSize) {
                $flush();
                $this->info("Progress: {$progressZips} new ZIPs, {$progressAliases} new aliases written so far...");
            }
        });

        $flush();

        // Use DB counts as authoritative — insertOrIgnore silently skips duplicates
        $afterZips        = DB::table('us_zip_codes')->count();
        $afterAliases     = DB::table('us_zip_code_cities')->count();
        $actualNewZips    = $afterZips    - $beforeZips;
        $actualNewAliases = $afterAliases - $beforeAliases;

        $this->newLine();
        $this->info('=== Import Complete ===');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Rows parsed from source',               $stats['parsed']],
                ['ZIPs before',                           $beforeZips],
                ['ZIPs after',                            $afterZips],
                ['New ZIPs added (actual)',                $actualNewZips],
                ['Aliases before',                        $beforeAliases],
                ['Aliases after',                         $afterAliases],
                ['New aliases added (actual)',             $actualNewAliases],
                ['Rows skipped (unknown state/territory)', $missingStates],
                ['Alias dedupes skipped (in-memory)',      $skipped],
            ]
        );
        $this->newLine();

        $this->runAcceptanceCheck();

        return 0;
    }

    // -------------------------------------------------------------------------
    // Acceptance check
    // -------------------------------------------------------------------------

    private function runAcceptanceCheck(): void
    {
        $this->info('Running spot-check on key ZIPs and cities...');

        $zipSpots = [
            ['zip' => '33706', 'label' => '33706 (TI/St Pete shared)'],
            ['zip' => '33707', 'label' => '33707 (Gulfport/St Pete shared)'],
            ['zip' => '10001', 'label' => '10001 (NYC/Manhattan)'],
            ['zip' => '90210', 'label' => '90210 (Beverly Hills)'],
            ['zip' => '60601', 'label' => '60601 (Chicago)'],
            ['zip' => '30301', 'label' => '30301 (Atlanta)'],
            ['zip' => '99501', 'label' => '99501 (Anchorage)'],
        ];

        $zipRows = [];
        foreach ($zipSpots as $spot) {
            $row     = DB::table('us_zip_codes')->where('zip_code', $spot['zip'])->first();
            $aliases = DB::table('us_zip_code_cities')->where('zip_code', $spot['zip'])->pluck('city')->toArray();
            $zipRows[] = [
                $spot['label'],
                $row ? $row->city . ', ' . $row->state_abbrev : 'NOT FOUND',
                $aliases ? implode('; ', $aliases) : '—',
            ];
        }
        $this->table(['ZIP / Label', 'Primary City', 'Aliases'], $zipRows);

        // City reverse-lookup spot checks — including abbreviation variants
        $citySpots = [
            ['city' => 'Treasure Island',  'state' => 'FL'],
            ['city' => 'Dunedin',          'state' => 'FL'],
            ['city' => 'Madeira Beach',    'state' => 'FL'],
            ['city' => 'St. Petersburg',   'state' => 'FL'],   // abbreviation variant
            ['city' => 'Saint Petersburg', 'state' => 'FL'],   // full form
            ['city' => 'Ft. Worth',        'state' => 'TX'],   // abbreviation variant
            ['city' => 'Los Angeles',      'state' => 'CA'],
            ['city' => 'New Orleans',      'state' => 'LA'],
            ['city' => 'Anchorage',        'state' => 'AK'],
            ['city' => 'New York',         'state' => 'NY'],
        ];

        $this->newLine();
        $this->info('City → ZIP reverse lookups (primary + alias, abbreviation-aware):');
        $cityRows = [];
        foreach ($citySpots as $spot) {
            $zips = \App\Models\UsZipCode::getZipCodesForCity($spot['city'], $spot['state']);
            $cityRows[] = [
                $spot['city'] . ', ' . $spot['state'],
                count($zips) > 0 ? implode(', ', array_slice($zips, 0, 5)) . (count($zips) > 5 ? '...' : '') : 'NONE',
                count($zips),
            ];
        }
        $this->table(['City', 'ZIP Codes (first 5)', 'Total'], $cityRows);
    }
}
