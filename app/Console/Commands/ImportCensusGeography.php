<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Phase 1d-1 — import the authoritative Census geography corpus into the `census_*` tables.
 *
 * SCOPE, AND WHAT THIS DELIBERATELY DOES NOT DO
 * ---------------------------------------------
 * This command populates six tables and one metadata table. It reads nothing else and writes
 * nothing else. In particular the `us_*` reference tables — `us_states`, `us_counties`,
 * `us_cities`, `us_zip_codes` — are never opened, because the shipped
 * `geography_source=eloquent` repository still serves every consumer-facing surface from them.
 * Phase 1d-2 introduces the reader that prefers these tables; until then this data is inert.
 *
 * FAIL LOUDLY, NOT PARTIALLY
 * --------------------------
 * Every dataset is parsed, cross-checked and written inside ONE transaction, and the
 * referential integrity of the result is re-verified against the database itself before that
 * transaction commits. A single orphan anywhere aborts the whole import and leaves the tables
 * exactly as they were. This is the opposite of how `us_cities` was populated — that table
 * accumulated silently, which is why establishing its provenance later took a forensic audit.
 *
 * THE ONE APPROVED EXCEPTION
 * --------------------------
 * `tab20_zcta520_county20_natl.txt` carries county-only records whose ZCTA columns are blank.
 * They are valid published rows describing a county with no ZCTA part, NOT corruption and NOT
 * orphans. They are skipped and counted in `census_geography_meta.rejected_count`, which is
 * what that column means: rows deliberately not imported, visible rather than silent.
 *
 * IDEMPOTENCE
 * -----------
 * A second run over unchanged sources converges to byte-identical table contents. The importer
 * upserts by GEOID and then prunes any row the sources no longer contain, so the tables are a
 * projection of the files rather than an accumulation of every run. `--skip-unchanged` adds the
 * cheap short-circuit on top: when every recorded sha256 still matches, nothing is re-parsed.
 * It is opt-in precisely so that the expensive, genuinely convergent path is what runs by
 * default — a skip that never touches the data would prove nothing about idempotence.
 */
class ImportCensusGeography extends Command
{
    protected $signature = 'census:import-geography
        {--path= : Directory holding the six Census source files (default: storage/app/census/{vintage})}
        {--vintage=2020 : Census vintage. Only 2020 is supported — see below.}
        {--skip-unchanged : Short-circuit when every recorded sha256 still matches the source files}';

    protected $description = 'Import authoritative Census geography (states, counties, places, ZCTAs and their relationships) into the census_* tables.';

    /**
     * The only supported vintage.
     *
     * ZCTAs are published on the decennial census alone, and the ZCTA-to-county relationship
     * file exists at 2020 only. Mixing a 2020 ZCTA corpus with, say, 2025 places would break
     * referential integrity across the schema, so the vintage is pinned rather than trusted.
     */
    private const SUPPORTED_VINTAGE = '2020';

    private const CODES_BASE = 'https://www2.census.gov/geo/docs/reference/codes2020/';

    /**
     * dataset => [file(s) it is derived from, published source URL].
     *
     * `zctas` and `zcta_counties` share one file: the relationship file is the only national
     * publication that enumerates 2020 ZCTAs, so the ZCTA list is derived from its distinct
     * non-blank GEOIDs rather than from a separate roster.
     */
    private const DATASETS = [
        'states' => [
            'files' => ['national_state2020.txt'],
            'url'   => self::CODES_BASE . 'national_state2020.txt',
        ],
        'counties' => [
            'files' => ['national_county2020.txt'],
            'url'   => self::CODES_BASE . 'national_county2020.txt',
        ],
        'places' => [
            'files' => ['national_place2020.txt', '2020_Gaz_place_national.txt'],
            'url'   => self::CODES_BASE . 'national_place2020.txt'
                . "\n" . 'https://www2.census.gov/geo/docs/maps-data/data/gazetteer/2020_Gazetteer/2020_Gaz_place_national.zip',
        ],
        'place_counties' => [
            'files' => ['national_place_by_county2020.txt'],
            'url'   => self::CODES_BASE . 'national_place_by_county2020.txt',
        ],
        'zctas' => [
            'files' => ['tab20_zcta520_county20_natl.txt'],
            'url'   => 'https://www2.census.gov/geo/docs/maps-data/data/rel2020/zcta520/tab20_zcta520_county20_natl.txt',
        ],
        'zcta_counties' => [
            'files' => ['tab20_zcta520_county20_natl.txt'],
            'url'   => 'https://www2.census.gov/geo/docs/maps-data/data/rel2020/zcta520/tab20_zcta520_county20_natl.txt',
        ],
    ];

    /**
     * Gazetteer LSAD code => the class word the published name ends with.
     *
     * Census publishes place names WITH their legal suffix ("Abbeville city", "Abanda CDP"),
     * while every label stored in this application is bare. Stripping by guesswork is unsafe —
     * "Kansas City city" and "Oklahoma City city" both end in the word "City" — so the numeric
     * LSAD code drives it instead. A code that is absent from this map, or a name that does not
     * actually end with the mapped word, leaves the name UNSTRIPPED rather than mangled.
     */
    private const PLACE_LSAD_SUFFIXES = [
        '21' => 'borough',
        '25' => 'city',
        '37' => 'municipality',
        '43' => 'town',
        '47' => 'village',
        '53' => 'city and borough',
        '55' => 'comunidad',
        '57' => 'CDP',
        '62' => 'zona urbana',
    ];

    /**
     * County class words, longest first.
     *
     * Order is load-bearing: "Juneau City and Borough" must match "City and Borough" before it
     * can match "Borough". Lowercase "city" is last and is real — Virginia's independent cities
     * are published as "Alexandria city".
     */
    private const COUNTY_CLASS_SUFFIXES = [
        'City and Borough',
        'Census Area',
        'Municipality',
        'Municipio',
        'Borough',
        'County',
        'Parish',
        'District',
        'Island',
        'city',
    ];

    public function handle(): int
    {
        $vintage = (string) $this->option('vintage');

        if ($vintage !== self::SUPPORTED_VINTAGE) {
            $this->error(
                "Unsupported vintage '{$vintage}'. Only " . self::SUPPORTED_VINTAGE . ' is supported: the '
                . 'ZCTA-to-county relationship file is published at 2020 alone, and mixing vintages '
                . 'would break referential integrity across census_*.'
            );

            return 1;
        }

        $dir = rtrim((string) ($this->option('path') ?: storage_path('app/census/' . $vintage)), '/');

        try {
            $sources = $this->resolveSources($dir);

            if ($this->option('skip-unchanged') && $this->everyDatasetUnchanged($sources, $vintage)) {
                $this->info('Every dataset matches its recorded sha256 and row count. Nothing to do.');

                return 0;
            }

            $parsed = $this->parseAll($sources);
            $this->assertReferentialIntegrity($parsed);

            DB::transaction(function () use ($parsed, $sources, $vintage) {
                $this->writeAll($parsed);

                // Re-verify against the DATABASE, not against the arrays we just built. The
                // in-memory check above can only prove the files agree with each other; this
                // proves what actually landed agrees too, and it runs while a rollback is
                // still possible.
                $this->assertDatabaseIntegrity();

                $this->recordMetadata($parsed, $sources, $vintage);
            });

            $this->report($parsed);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sources
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Locate every required file and hash it. Missing files abort before a single row is read.
     *
     * @return array<string, array{path: array<string,string>, url: string, sha256: string}>
     */
    private function resolveSources(string $dir): array
    {
        if (! is_dir($dir)) {
            throw new RuntimeException("Census source directory does not exist: {$dir}");
        }

        $resolved = [];

        foreach (self::DATASETS as $dataset => $spec) {
            $paths  = [];
            $hashes = [];

            foreach ($spec['files'] as $file) {
                $path = $dir . '/' . $file;

                if (! is_file($path)) {
                    throw new RuntimeException(
                        "Missing Census source file for dataset '{$dataset}': {$path}"
                    );
                }

                $paths[$file] = $path;
                $hashes[]     = hash_file('sha256', $path);
            }

            $resolved[$dataset] = [
                'path' => $paths,
                'url'  => $spec['url'],
                // For a single-file dataset this is simply that file's digest. For `places`,
                // which is built from two files, it is the digest of both digests — so a change
                // to EITHER source is visible in one recorded value.
                'sha256' => count($hashes) === 1 ? $hashes[0] : hash('sha256', implode(':', $hashes)),
            ];
        }

        return $resolved;
    }

    /**
     * True when every dataset's recorded sha256 still matches AND the table still holds the
     * recorded number of rows. The row count is part of the test on purpose: a matching hash
     * with an empty table means someone truncated the table, and that is not "unchanged".
     *
     * @param array<string, array{path: array<string,string>, url: string, sha256: string}> $sources
     */
    private function everyDatasetUnchanged(array $sources, string $vintage): bool
    {
        foreach ($sources as $dataset => $source) {
            $meta = DB::table('census_geography_meta')
                ->where('dataset', $dataset)
                ->where('vintage', $vintage)
                ->first();

            if ($meta === null || $meta->sha256 !== $source['sha256']) {
                return false;
            }

            if (DB::table($this->tableFor($dataset))->count() !== (int) $meta->row_count) {
                return false;
            }
        }

        return true;
    }

    private function tableFor(string $dataset): string
    {
        return 'census_' . $dataset;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Parsing
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, array{path: array<string,string>, url: string, sha256: string}> $sources
     * @return array<string, array{rows: array<int, array<string, mixed>>, rejected: int}>
     */
    private function parseAll(array $sources): array
    {
        $zcta = $this->parseZctaRelationships(
            $sources['zcta_counties']['path']['tab20_zcta520_county20_natl.txt']
        );

        return [
            'states'   => $this->parseStates($sources['states']['path']['national_state2020.txt']),
            'counties' => $this->parseCounties($sources['counties']['path']['national_county2020.txt']),
            'places'   => $this->parsePlaces(
                $sources['places']['path']['national_place2020.txt'],
                $sources['places']['path']['2020_Gaz_place_national.txt']
            ),
            'place_counties' => $this->parsePlaceCounties(
                $sources['place_counties']['path']['national_place_by_county2020.txt']
            ),
            'zctas'         => $zcta['zctas'],
            'zcta_counties' => $zcta['zcta_counties'],
        ];
    }

    /**
     * Stream a delimited Census file as header-keyed rows.
     *
     * Uses explode() rather than fgetcsv(): these files have no quoting convention, and
     * fgetcsv's default enclosure would corrupt any name containing a double quote. Values are
     * trimmed because the Gazetteer pads every line with trailing spaces.
     *
     * @return \Generator<int, array<string, string>>
     */
    private function readDelimited(string $path, string $delimiter): \Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open Census source file: {$path}");
        }

        try {
            $headerLine = fgets($handle);

            if ($headerLine === false) {
                throw new RuntimeException("Census source file is empty: {$path}");
            }

            // The 2020 ZCTA relationship file is published with a UTF-8 BOM, which would
            // otherwise become part of the first column's name and make every lookup miss.
            $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine) ?? $headerLine;
            $header     = array_map('trim', explode($delimiter, rtrim($headerLine, "\r\n")));

            $lineNumber = 1;

            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $line = rtrim($line, "\r\n");

                if (trim($line) === '') {
                    continue;
                }

                $values = array_map('trim', explode($delimiter, $line));

                if (count($values) !== count($header)) {
                    throw new RuntimeException(sprintf(
                        '%s line %d: expected %d columns, found %d. Refusing to guess which column is which.',
                        basename($path),
                        $lineNumber,
                        count($header),
                        count($values)
                    ));
                }

                yield array_combine($header, $values);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Zero-pad and validate a fixed-width numeric Census identifier.
     *
     * This is where leading zeros are defended. The published files already pad, but a source
     * that has been through a spreadsheet will not, and `1` silently standing in for `01` is
     * the single most damaging thing that can happen to this schema.
     */
    private function fixedWidthCode(string $value, int $width, string $label, string $file, int $line): string
    {
        $padded = str_pad($value, $width, '0', STR_PAD_LEFT);

        if (! preg_match('/^\d{' . $width . '}$/', $padded)) {
            throw new RuntimeException(sprintf(
                '%s line %d: %s is %s, which is not a %d-digit code.',
                $file,
                $line,
                $label,
                var_export($value, true),
                $width
            ));
        }

        return $padded;
    }

    /** @return array{rows: array<int, array<string, mixed>>, rejected: int} */
    private function parseStates(string $path): array
    {
        $rows = [];
        $line = 1;

        foreach ($this->readDelimited($path, '|') as $record) {
            $line++;
            $geoid = $this->fixedWidthCode($record['STATEFP'], 2, 'STATEFP', basename($path), $line);
            $usps  = strtoupper($record['STATE']);

            if (! preg_match('/^[A-Z]{2}$/', $usps)) {
                throw new RuntimeException(sprintf(
                    '%s line %d: STATE is %s, which is not a two-letter USPS abbreviation.',
                    basename($path),
                    $line,
                    var_export($record['STATE'], true)
                ));
            }

            $rows[$geoid] = [
                'geoid'   => $geoid,
                'usps'    => $usps,
                'name'    => $record['STATE_NAME'],
                'statens' => $record['STATENS'] !== '' ? $record['STATENS'] : null,
            ];
        }

        return ['rows' => array_values($rows), 'rejected' => 0];
    }

    /** @return array{rows: array<int, array<string, mixed>>, rejected: int} */
    private function parseCounties(string $path): array
    {
        $rows = [];
        $line = 1;

        foreach ($this->readDelimited($path, '|') as $record) {
            $line++;
            $state    = $this->fixedWidthCode($record['STATEFP'], 2, 'STATEFP', basename($path), $line);
            $countyfp = $this->fixedWidthCode($record['COUNTYFP'], 3, 'COUNTYFP', basename($path), $line);
            $name     = $record['COUNTYNAME'];

            $rows[$state . $countyfp] = [
                'geoid'       => $state . $countyfp,
                'state_geoid' => $state,
                'countyfp'    => $countyfp,
                'name'        => $name,
                'basename'    => $this->countyBasename($name),
                'classfp'     => $record['CLASSFP'] !== '' ? $record['CLASSFP'] : null,
                'funcstat'    => $record['FUNCSTAT'] !== '' ? $record['FUNCSTAT'] : null,
                'countyns'    => $record['COUNTYNS'] !== '' ? $record['COUNTYNS'] : null,
            ];
        }

        return ['rows' => array_values($rows), 'rejected' => 0];
    }

    /** Strip the class word from a published county name: "Autauga County" => "Autauga". */
    private function countyBasename(string $name): string
    {
        foreach (self::COUNTY_CLASS_SUFFIXES as $suffix) {
            $needle = ' ' . $suffix;

            if (str_ends_with($name, $needle)) {
                $stripped = trim(substr($name, 0, -strlen($needle)));

                // "County County" is not a thing, but a one-word name that IS the class word
                // would strip to nothing. Keep the published name rather than emit an empty
                // basename.
                return $stripped !== '' ? $stripped : $name;
            }
        }

        return $name;
    }

    /** @return array{rows: array<int, array<string, mixed>>, rejected: int} */
    private function parsePlaces(string $placePath, string $gazetteerPath): array
    {
        // The Gazetteer supplies the LSAD code and the internal point; the codes file supplies
        // the roster, class and functional status. Keyed by GEOID so the join is exact rather
        // than by name.
        $gazetteer = [];
        $line      = 1;

        foreach ($this->readDelimited($gazetteerPath, "\t") as $record) {
            $line++;
            $geoid             = $this->fixedWidthCode($record['GEOID'], 7, 'GEOID', basename($gazetteerPath), $line);
            $gazetteer[$geoid] = [
                'lsad'      => $record['LSAD'] !== '' ? $record['LSAD'] : null,
                'intptlat'  => is_numeric($record['INTPTLAT']) ? $record['INTPTLAT'] : null,
                'intptlong' => is_numeric($record['INTPTLONG']) ? $record['INTPTLONG'] : null,
            ];
        }

        $rows = [];
        $line = 1;

        foreach ($this->readDelimited($placePath, '|') as $record) {
            $line++;
            $state   = $this->fixedWidthCode($record['STATEFP'], 2, 'STATEFP', basename($placePath), $line);
            $placefp = $this->fixedWidthCode($record['PLACEFP'], 5, 'PLACEFP', basename($placePath), $line);
            $geoid   = $state . $placefp;

            $namelsad = $record['PLACENAME'];
            $lsad     = $gazetteer[$geoid]['lsad'] ?? null;

            $rows[$geoid] = [
                'geoid'       => $geoid,
                'state_geoid' => $state,
                'placefp'     => $placefp,
                'name'        => $this->stripPlaceSuffix($namelsad, $lsad),
                'namelsad'    => $namelsad,
                'lsad'        => $lsad,
                'classfp'     => $record['CLASSFP'] !== '' ? $record['CLASSFP'] : null,
                'funcstat'    => $record['FUNCSTAT'] !== '' ? $record['FUNCSTAT'] : null,
                'placens'     => $record['PLACENS'] !== '' ? $record['PLACENS'] : null,
                'intptlat'    => $gazetteer[$geoid]['intptlat'] ?? null,
                'intptlong'   => $gazetteer[$geoid]['intptlong'] ?? null,
            ];
        }

        return ['rows' => array_values($rows), 'rejected' => 0];
    }

    /**
     * "Abbeville city" + LSAD 25 => "Abbeville". An unknown LSAD, or a name that does not end
     * with the mapped class word, is returned untouched — a wrong name is worse than a long one.
     */
    private function stripPlaceSuffix(string $namelsad, ?string $lsad): string
    {
        if ($lsad === null || ! isset(self::PLACE_LSAD_SUFFIXES[$lsad])) {
            return $namelsad;
        }

        $needle = ' ' . self::PLACE_LSAD_SUFFIXES[$lsad];

        if (! str_ends_with($namelsad, $needle)) {
            return $namelsad;
        }

        $stripped = trim(substr($namelsad, 0, -strlen($needle)));

        return $stripped !== '' ? $stripped : $namelsad;
    }

    /** @return array{rows: array<int, array<string, mixed>>, rejected: int} */
    private function parsePlaceCounties(string $path): array
    {
        $rows = [];
        $line = 1;

        foreach ($this->readDelimited($path, '|') as $record) {
            $line++;
            $state    = $this->fixedWidthCode($record['STATEFP'], 2, 'STATEFP', basename($path), $line);
            $countyfp = $this->fixedWidthCode($record['COUNTYFP'], 3, 'COUNTYFP', basename($path), $line);
            $placefp  = $this->fixedWidthCode($record['PLACEFP'], 5, 'PLACEFP', basename($path), $line);

            // Keyed by the composite so a place published once per county yields one row per
            // county, and an exactly-repeated pair collapses instead of violating the primary key.
            $rows[$state . $placefp . '-' . $state . $countyfp] = [
                'place_geoid'  => $state . $placefp,
                'county_geoid' => $state . $countyfp,
            ];
        }

        return ['rows' => array_values($rows), 'rejected' => 0];
    }

    /**
     * Parse the ZCTA relationship file into both the ZCTA roster and the relationship rows.
     *
     * THE APPROVED SKIP LIVES HERE. Rows whose `GEOID_ZCTA5_20` is blank are county-only
     * records — a county with no ZCTA part — and are valid published data. They are counted,
     * not imported, and never treated as orphans.
     *
     * @return array{zctas: array{rows: array<int, array<string, mixed>>, rejected: int}, zcta_counties: array{rows: array<int, array<string, mixed>>, rejected: int}}
     */
    private function parseZctaRelationships(string $path): array
    {
        $zctas    = [];
        $links    = [];
        $skipped  = 0;
        $line     = 1;

        foreach ($this->readDelimited($path, '|') as $record) {
            $line++;

            if (($record['GEOID_ZCTA5_20'] ?? '') === '') {
                $skipped++;

                continue;
            }

            $zcta5  = $this->fixedWidthCode($record['GEOID_ZCTA5_20'], 5, 'GEOID_ZCTA5_20', basename($path), $line);
            $county = $this->fixedWidthCode($record['GEOID_COUNTY_20'], 5, 'GEOID_COUNTY_20', basename($path), $line);

            $zctas[$zcta5] = ['zcta5' => $zcta5];

            $areaLand = $record['AREALAND_PART'] ?? '';

            $links[$zcta5 . '-' . $county] = [
                'zcta5'         => $zcta5,
                'county_geoid'  => $county,
                'arealand_part' => is_numeric($areaLand) ? (int) $areaLand : null,
            ];
        }

        return [
            // The skip is attributed to both datasets derived from this file, because the same
            // rows are the ones not contributing to either.
            'zctas'         => ['rows' => array_values($zctas), 'rejected' => $skipped],
            'zcta_counties' => ['rows' => array_values($links), 'rejected' => $skipped],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Integrity
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Cross-check the parsed datasets against each other before anything is written.
     *
     * Failing here rather than at write time means an orphan is reported with the identifier
     * that caused it, instead of as a constraint violation from the driver.
     *
     * @param array<string, array{rows: array<int, array<string, mixed>>, rejected: int}> $parsed
     */
    private function assertReferentialIntegrity(array $parsed): void
    {
        $states   = array_flip(array_column($parsed['states']['rows'], 'geoid'));
        $counties = array_flip(array_column($parsed['counties']['rows'], 'geoid'));
        $places   = array_flip(array_column($parsed['places']['rows'], 'geoid'));
        $zctas    = array_flip(array_column($parsed['zctas']['rows'], 'zcta5'));

        $this->assertAllPresent($parsed['counties']['rows'], 'state_geoid', $states, 'census_counties', 'census_states');
        $this->assertAllPresent($parsed['places']['rows'], 'state_geoid', $states, 'census_places', 'census_states');
        $this->assertAllPresent($parsed['place_counties']['rows'], 'place_geoid', $places, 'census_place_counties', 'census_places');
        $this->assertAllPresent($parsed['place_counties']['rows'], 'county_geoid', $counties, 'census_place_counties', 'census_counties');
        $this->assertAllPresent($parsed['zcta_counties']['rows'], 'zcta5', $zctas, 'census_zcta_counties', 'census_zctas');
        $this->assertAllPresent($parsed['zcta_counties']['rows'], 'county_geoid', $counties, 'census_zcta_counties', 'census_counties');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, int>               $known
     */
    private function assertAllPresent(array $rows, string $column, array $known, string $childTable, string $parentTable): void
    {
        $missing = [];

        foreach ($rows as $row) {
            $value = (string) $row[$column];

            if (! isset($known[$value]) && ! isset($missing[$value])) {
                $missing[$value] = true;
            }
        }

        if ($missing === []) {
            return;
        }

        $sample = array_slice(array_keys($missing), 0, 10);

        throw new RuntimeException(sprintf(
            'Orphan relationship: %d distinct %s.%s value(s) have no matching row in %s. Aborting the '
            . 'entire import — nothing has been written. Offending value(s): %s%s',
            count($missing),
            $childTable,
            $column,
            $parentTable,
            implode(', ', $sample),
            count($missing) > count($sample) ? ', …' : ''
        ));
    }

    /**
     * Re-verify integrity against the written tables, inside the transaction.
     *
     * This is not a duplicate of assertReferentialIntegrity(). That one proves the FILES agree.
     * This one proves the DATABASE agrees after upserting and pruning — so a bug in the write
     * path itself, not just in the sources, still aborts before commit.
     */
    private function assertDatabaseIntegrity(): void
    {
        $checks = [
            ['census_counties', 'state_geoid', 'census_states', 'geoid'],
            ['census_places', 'state_geoid', 'census_states', 'geoid'],
            ['census_place_counties', 'place_geoid', 'census_places', 'geoid'],
            ['census_place_counties', 'county_geoid', 'census_counties', 'geoid'],
            ['census_zcta_counties', 'zcta5', 'census_zctas', 'zcta5'],
            ['census_zcta_counties', 'county_geoid', 'census_counties', 'geoid'],
        ];

        foreach ($checks as [$childTable, $childColumn, $parentTable, $parentColumn]) {
            $orphans = DB::table($childTable)
                ->leftJoin($parentTable, "{$childTable}.{$childColumn}", '=', "{$parentTable}.{$parentColumn}")
                ->whereNull("{$parentTable}.{$parentColumn}")
                ->count();

            if ($orphans > 0) {
                throw new RuntimeException(
                    "Post-write integrity check failed: {$orphans} row(s) in {$childTable} have a "
                    . "{$childColumn} with no matching {$parentTable}.{$parentColumn}. Rolling back."
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Writing
    // ─────────────────────────────────────────────────────────────────────────

    /** @param array<string, array{rows: array<int, array<string, mixed>>, rejected: int}> $parsed */
    private function writeAll(array $parsed): void
    {
        // Parents before children, so the post-write check never sees a transient orphan.
        $this->sync('census_states', $parsed['states']['rows'], ['geoid']);
        $this->sync('census_counties', $parsed['counties']['rows'], ['geoid']);
        $this->sync('census_places', $parsed['places']['rows'], ['geoid']);
        $this->sync('census_zctas', $parsed['zctas']['rows'], ['zcta5']);
        $this->sync('census_place_counties', $parsed['place_counties']['rows'], ['place_geoid', 'county_geoid']);
        $this->sync('census_zcta_counties', $parsed['zcta_counties']['rows'], ['zcta5', 'county_geoid']);
    }

    /**
     * Upsert every incoming row, then delete anything the sources no longer contain.
     *
     * The prune is what makes the table a PROJECTION of the files rather than an accumulation.
     * Without it a place withdrawn upstream would linger for ever, and a re-import would not be
     * idempotent in any meaningful sense — it would only be additive.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string>               $keyColumns
     */
    private function sync(string $table, array $rows, array $keyColumns): void
    {
        $now = now();

        if ($rows !== []) {
            $updatable = array_values(array_diff(array_keys($rows[0]), $keyColumns));
            $updatable[] = 'updated_at';

            foreach (array_chunk($rows, 500) as $chunk) {
                $stamped = array_map(
                    static fn (array $row): array => $row + ['created_at' => $now, 'updated_at' => $now],
                    $chunk
                );

                DB::table($table)->upsert($stamped, $keyColumns, $updatable);
            }
        }

        $this->prune($table, $keyColumns, $rows);
    }

    /**
     * Delete rows whose key is absent from the incoming set.
     *
     * Existing keys are read and diffed in PHP rather than expressed as a `WHERE key NOT IN
     * (33,618 bindings)`, which no driver should be asked to plan. Deletions are then issued
     * per key — acceptable because on a converged table there are none, and on a real upstream
     * withdrawal there are a handful.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string>               $keyColumns
     */
    private function prune(string $table, array $keyColumns, array $rows): void
    {
        $incoming = [];

        foreach ($rows as $row) {
            $incoming[$this->compositeKey($row, $keyColumns)] = true;
        }

        $stale = [];

        DB::table($table)
            ->select($keyColumns)
            ->orderBy($keyColumns[0])
            ->chunk(2000, function ($existing) use (&$stale, $keyColumns, $incoming) {
                foreach ($existing as $row) {
                    $values = (array) $row;

                    if (! isset($incoming[$this->compositeKey($values, $keyColumns)])) {
                        $stale[] = $values;
                    }
                }
            });

        foreach ($stale as $key) {
            $query = DB::table($table);

            foreach ($keyColumns as $column) {
                $query->where($column, $key[$column]);
            }

            $query->delete();
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string>   $keyColumns
     */
    private function compositeKey(array $row, array $keyColumns): string
    {
        $parts = [];

        foreach ($keyColumns as $column) {
            $parts[] = (string) $row[$column];
        }

        return implode("\x1F", $parts);
    }

    /**
     * @param array<string, array{rows: array<int, array<string, mixed>>, rejected: int}>      $parsed
     * @param array<string, array{path: array<string,string>, url: string, sha256: string}>    $sources
     */
    private function recordMetadata(array $parsed, array $sources, string $vintage): void
    {
        $now = now();

        foreach ($parsed as $dataset => $result) {
            $key = ['dataset' => $dataset, 'vintage' => $vintage];

            $values = [
                'source_url'     => $sources[$dataset]['url'],
                'sha256'         => $sources[$dataset]['sha256'],
                'row_count'      => count($result['rows']),
                'rejected_count' => $result['rejected'],
                'imported_at'    => $now,
                'updated_at'     => $now,
            ];

            // `created_at` is set on first import only. updateOrInsert() applies the whole
            // values array on UPDATE too, so including it unconditionally would rewrite the
            // first-seen timestamp on every run and destroy the one fact it records.
            if (! DB::table('census_geography_meta')->where($key)->exists()) {
                $values['created_at'] = $now;
            }

            DB::table('census_geography_meta')->updateOrInsert($key, $values);
        }
    }

    /** @param array<string, array{rows: array<int, array<string, mixed>>, rejected: int}> $parsed */
    private function report(array $parsed): void
    {
        $rows = [];

        foreach ($parsed as $dataset => $result) {
            $rows[] = [
                $dataset,
                number_format(count($result['rows'])),
                $result['rejected'] > 0 ? number_format($result['rejected']) : '—',
                number_format(DB::table($this->tableFor($dataset))->count()),
            ];
        }

        $this->table(['Dataset', 'Parsed', 'Skipped', 'In table'], $rows);
        $this->info('Census geography import complete.');
    }
}
