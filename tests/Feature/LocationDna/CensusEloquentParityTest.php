<?php

namespace Tests\Feature\LocationDna;

use App\Services\LocationDna\Criteria\CensusCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\CriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\EloquentCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\GeographyOption;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1d-2 Stage 2 — MEASUREMENT, not a pass/fail gate.
 *
 * WHAT THIS FILE IS FOR, AND WHAT IT REFUSES TO DO
 * ------------------------------------------------
 * Switching `geography_source` changes which places exist. Some of that is the improvement the
 * phase is for; some of it is loss. Which outweighs the other is a product decision about data,
 * not something a test may decide. So nothing here asserts the SIZE of any difference and nothing
 * changes based on what is found. It counts, and it prints.
 *
 * THE MEASUREMENT IS BOUNDED, AND THE BOUND IS REPORTED
 * -----------------------------------------------------
 * The census fixtures are a real national subset, not the full corpus, so a naive comparison would
 * read every fixture gap as data loss. Each tier is therefore scoped to where BOTH sides have
 * complete coverage, and the scope is printed alongside the numbers:
 *
 *   states    all 57 — the fixture carries the complete national roster
 *   counties  the 7 states whose fixture county count equals the real one
 *             (AZ 15, CA 58, FL 67, IL 102, MT 56, NY 62, PR 78)
 *   cities    the 7 counties the fixture carries place rows for
 *   ZIPs      the same 7 counties
 *
 * Outside those bounds the fixture is silent, and silence is not absence.
 *
 * WHERE THE `us_*` DATA COMES FROM
 * --------------------------------
 * From a read-only export, not from a live connection. The suite forces an in-memory SQLite
 * connection precisely so a test can never reach the real database, and this file does not work
 * around that. It reads a JSON export produced separately and loads it into the test connection.
 *
 * With no export present the report SKIPS, naming what is missing. Inventing production-like rows
 * to make it run would produce a number that looks like a measurement and is not one — the single
 * most misleading thing this file could do. The comparison harness itself is asserted on
 * controlled fixtures below, so the file is never vacuous even when the report cannot run.
 *
 * To produce the export, run against an environment where `us_*` is populated:
 *   CENSUS_PARITY_US_EXPORT=/path/to/us_reference_export.json
 * and point this test at the same path.
 */
class CensusEloquentParityTest extends TestCase
{
    use DatabaseTransactions;

    private CensusCriteriaGeographyRepository $census;

    private EloquentCriteriaGeographyRepository $eloquent;

    /** The states whose fixture county coverage equals the real county count. */
    private const FULLY_COVERED_STATES = ['AZ', 'CA', 'FL', 'IL', 'MT', 'NY', 'PR'];

    /** The counties the fixture carries place and ZCTA rows for, by census GEOID. */
    private const TARGET_COUNTIES = [
        '04013' => 'Maricopa County, AZ',
        '06037' => 'Los Angeles County, CA',
        '12103' => 'Pinellas County, FL',
        '17031' => 'Cook County, IL',
        '30033' => 'Garfield County, MT',
        '36119' => 'Westchester County, NY',
        '72127' => 'San Juan Municipio, PR',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->census   = new CensusCriteriaGeographyRepository();
        $this->eloquent = new EloquentCriteriaGeographyRepository();
    }

    // ═════════════════════════════════════════════════════════════════════
    // The measuring instrument
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Compare two option lists by NAME, which is the only vocabulary they share.
     *
     * Ids are deliberately not compared: they are surrogate keys on one side and GEOIDs on the
     * other, so an id comparison would report 100% difference and mean nothing. And for cities
     * there is no alternative at all — `us_cities.fips_code` is empty for every one of its 25,830
     * rows, so the name IS the identity. That is also precisely why a name difference here is the
     * thing that decides whether a stored selection survives the swap.
     *
     * Normalised the way the hydrator normalises — lowercased, whitespace collapsed — and no more
     * aggressively than that, which would flatter the result.
     *
     * @param  list<GeographyOption>  $legacy
     * @param  list<GeographyOption>  $census
     * @return array{matched: int, only_legacy: list<string>, only_census: list<string>}
     */
    private function compare(array $legacy, array $census): array
    {
        $legacyNames = $this->nameSet($legacy);
        $censusNames = $this->nameSet($census);

        return [
            'matched'     => count(array_intersect_key($legacyNames, $censusNames)),
            'only_legacy' => array_values(array_diff_key($legacyNames, $censusNames)),
            'only_census' => array_values(array_diff_key($censusNames, $legacyNames)),
        ];
    }

    /**
     * @param  list<GeographyOption>  $options
     * @return array<string, string>  normalised name => original name
     */
    private function nameSet(array $options): array
    {
        $set = [];

        foreach ($options as $option) {
            $key = $this->nameKey($option->name);

            if ($key !== '') {
                $set[$key] ??= $option->name;
            }
        }

        return $set;
    }

    private function nameKey(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower($name)));
    }

    // ═════════════════════════════════════════════════════════════════════
    // Proving the instrument, on controlled data
    // ═════════════════════════════════════════════════════════════════════

    /**
     * The comparison itself is asserted, because a harness that silently reported "0 differences"
     * would be indistinguishable from perfect parity — and would be believed.
     */
    /** @test */
    public function the_comparison_identifies_matches_losses_and_gains(): void
    {
        $legacy = [
            GeographyOption::city('1', 'Prattville', '10'),
            GeographyOption::city('2', 'Saint Petersburg', '10'),
            GeographyOption::city('3', 'Amf Ohare', '10'),
        ];

        $census = [
            GeographyOption::city('0100100', 'Prattville', '01001'),
            GeographyOption::city('0100200', 'St. Petersburg', '01001'),
            GeographyOption::city('0100300', 'Fairhope', '01001'),
        ];

        $result = $this->compare($legacy, $census);

        $this->assertSame(1, $result['matched'], 'Only Prattville is spelled the same on both sides.');
        $this->assertEqualsCanonicalizing(['Saint Petersburg', 'Amf Ohare'], $result['only_legacy']);
        $this->assertEqualsCanonicalizing(['St. Petersburg', 'Fairhope'], $result['only_census']);
    }

    /** Case and spacing differences are not real differences, and must not be counted as any. */
    /** @test */
    public function the_comparison_normalises_case_and_whitespace(): void
    {
        $result = $this->compare(
            [GeographyOption::city('1', 'ST.  PETERSBURG', '10')],
            [GeographyOption::city('0100100', 'St. Petersburg', '01001')]
        );

        $this->assertSame(1, $result['matched']);
        $this->assertSame([], $result['only_legacy']);
        $this->assertSame([], $result['only_census']);
    }

    /** @test */
    public function both_implementations_satisfy_the_same_interface(): void
    {
        $this->assertInstanceOf(CriteriaGeographyRepository::class, $this->census);
        $this->assertInstanceOf(CriteriaGeographyRepository::class, $this->eloquent);
    }

    // ═════════════════════════════════════════════════════════════════════
    // The report
    // ═════════════════════════════════════════════════════════════════════

    /** @test */
    public function it_reports_the_difference_between_the_two_sources(): void
    {
        $export = $this->loadUsReferenceExport();

        $this->assertSame(
            0,
            $this->artisan('census:import-geography', [
                '--path' => base_path('tests/fixtures/census/2020'),
            ])->run(),
            'The census fixtures must import before anything can be compared.'
        );

        $this->line('');
        $this->line('════════════════════════════════════════════════════════════════════');
        $this->line(' CRITERIA GEOGRAPHY PARITY — us_* (legacy) vs census_* (Phase 1d-1)');
        $this->line('════════════════════════════════════════════════════════════════════');
        $this->line(sprintf(
            ' us_* export: %d states, %d counties, %d cities, %d zips',
            count($export['us_states']),
            count($export['us_counties']),
            count($export['us_cities']),
            count($export['us_zip_codes'])
        ));
        $this->line(sprintf(
            ' census_*   : %d states, %d counties, %d places, %d zctas',
            DB::table('census_states')->count(),
            DB::table('census_counties')->count(),
            DB::table('census_places')->count(),
            DB::table('census_zctas')->count()
        ));

        $this->reportStates();
        $this->reportCounties();
        $this->reportCitiesAndZips();

        $this->line('');
        $this->line('════════════════════════════════════════════════════════════════════');
        $this->line('');

        // The only assertions in the report: both sides honour the contract.
        foreach ($this->eloquent->states() as $option) {
            $this->assertTrue($option->is(GeographyOption::KIND_STATE));
        }

        foreach ($this->census->states() as $option) {
            $this->assertTrue($option->is(GeographyOption::KIND_STATE));
        }
    }

    // ── 1 · States ───────────────────────────────────────────────────────

    private function reportStates(): void
    {
        $legacy = $this->eloquent->states();
        $census = $this->census->states();

        $this->section('1 · STATES  (scope: complete on both sides)');
        $this->line(sprintf('   eloquent %3d    census %3d', count($legacy), count($census)));

        $result = $this->compare($legacy, $census);

        $this->line(sprintf(
            '   name matched %3d | only legacy %3d | only census %3d',
            $result['matched'],
            count($result['only_legacy']),
            count($result['only_census'])
        ));

        $this->samples('only legacy', $result['only_legacy']);
        $this->samples('only census', $result['only_census']);

        // FIPS pairing, which is available for states and is the structural check names cannot give.
        $legacyFips = [];
        foreach ($legacy as $option) {
            if ($option->code !== null && trim($option->code) !== '') {
                $legacyFips[str_pad(trim($option->code), 2, '0', STR_PAD_LEFT)] = $option->name;
            }
        }

        $censusFips = [];
        foreach ($census as $option) {
            $censusFips[(string) $option->code] = $option->name;
        }

        $pairedButRenamed = [];
        foreach (array_intersect_key($legacyFips, $censusFips) as $fips => $legacyName) {
            if ($this->nameKey($legacyName) !== $this->nameKey($censusFips[$fips])) {
                $pairedButRenamed[] = "{$fips}: '{$legacyName}' vs '{$censusFips[$fips]}'";
            }
        }

        $this->line(sprintf(
            '   FIPS paired  %3d | legacy without FIPS %d | differing name under same FIPS %d',
            count(array_intersect_key($legacyFips, $censusFips)),
            count($legacy) - count($legacyFips),
            count($pairedButRenamed)
        ));

        $this->samples('renamed', $pairedButRenamed);
    }

    // ── 2 · Counties ─────────────────────────────────────────────────────

    private function reportCounties(): void
    {
        $this->section('2 · COUNTIES  (scope: the 7 states with complete fixture coverage)');

        $censusStateByUsps = [];
        foreach (DB::table('census_states')->get(['geoid', 'usps']) as $row) {
            $censusStateByUsps[strtoupper((string) $row->usps)] = (string) $row->geoid;
        }

        $legacyStateByAbbrev = [];
        foreach (DB::table('us_states')->get(['id', 'abbreviation']) as $row) {
            $legacyStateByAbbrev[strtoupper((string) $row->abbreviation)] = (string) $row->id;
        }

        $totalLegacy = $totalCensus = $totalMatched = 0;
        $allOnlyLegacy = $allOnlyCensus = [];

        foreach (self::FULLY_COVERED_STATES as $abbrev) {
            $legacyStateId = $legacyStateByAbbrev[$abbrev] ?? null;
            $censusStateId = $censusStateByUsps[$abbrev] ?? null;

            if ($legacyStateId === null || $censusStateId === null) {
                $this->line(sprintf('   %-3s  unpairable (legacy=%s census=%s)', $abbrev, $legacyStateId ?? '—', $censusStateId ?? '—'));

                continue;
            }

            $legacy = $this->eloquent->countiesInState($legacyStateId);
            $census = $this->census->countiesInState($censusStateId);
            $result = $this->compare($legacy, $census);

            $totalLegacy  += count($legacy);
            $totalCensus  += count($census);
            $totalMatched += $result['matched'];

            foreach ($result['only_legacy'] as $n) {
                $allOnlyLegacy[] = "{$abbrev}: {$n}";
            }
            foreach ($result['only_census'] as $n) {
                $allOnlyCensus[] = "{$abbrev}: {$n}";
            }

            $this->line(sprintf(
                '   %-3s  eloquent %3d  census %3d  matched %3d  only-legacy %3d  only-census %3d',
                $abbrev,
                count($legacy),
                count($census),
                $result['matched'],
                count($result['only_legacy']),
                count($result['only_census'])
            ));
        }

        $this->line(sprintf(
            '   ───  eloquent %3d  census %3d  matched %3d  only-legacy %3d  only-census %3d',
            $totalLegacy,
            $totalCensus,
            $totalMatched,
            count($allOnlyLegacy),
            count($allOnlyCensus)
        ));

        $this->samples('only legacy', $allOnlyLegacy, 12);
        $this->samples('only census', $allOnlyCensus, 12);
    }

    // ── 3 & 4 · Cities and ZIPs ──────────────────────────────────────────

    private function reportCitiesAndZips(): void
    {
        $this->section('3 · CITIES / PLACES   and   4 · ZIPS  (scope: the 7 fixture counties)');

        $legacyCountyByKey = [];
        foreach (DB::table('us_counties')->join('us_states', 'us_states.id', '=', 'us_counties.state_id')
                     ->get(['us_counties.id as id', 'us_counties.name as name', 'us_states.abbreviation as abbrev']) as $row) {
            $key = $this->nameKey((string) $row->name).'|'.strtoupper((string) $row->abbrev);
            $legacyCountyByKey[$key] = (string) $row->id;
        }

        $cityTotals = ['legacy' => 0, 'census' => 0, 'matched' => 0];
        $zipTotals  = ['legacy' => 0, 'census' => 0, 'matched' => 0];
        $cityOnlyLegacy = $cityOnlyCensus = $zipOnlyLegacy = $zipOnlyCensus = [];

        foreach (self::TARGET_COUNTIES as $geoid => $label) {
            [$countyName, $abbrev] = array_map('trim', explode(',', $label));

            $legacyCountyId = $legacyCountyByKey[$this->nameKey($countyName).'|'.$abbrev] ?? null;

            if ($legacyCountyId === null) {
                $this->line(sprintf('   %-26s  NO LEGACY COUNTY — name does not exist in us_counties', $label));
                $this->line(sprintf('   %-26s  census places %d, zctas %d (all of them unreachable from legacy)',
                    '',
                    count($this->census->citiesInCounties([$geoid])),
                    count($this->census->zipsInCounties([$geoid]))
                ));

                continue;
            }

            $legacyCities = $this->eloquent->citiesInCounties([$legacyCountyId]);
            $censusCities = $this->census->citiesInCounties([$geoid]);
            $cityResult   = $this->compare($legacyCities, $censusCities);

            $legacyZips = $this->eloquent->zipsInCounties([$legacyCountyId]);
            $censusZips = $this->census->zipsInCounties([$geoid]);
            $zipResult  = $this->compare($legacyZips, $censusZips);

            $cityTotals['legacy']  += count($legacyCities);
            $cityTotals['census']  += count($censusCities);
            $cityTotals['matched'] += $cityResult['matched'];

            $zipTotals['legacy']  += count($this->nameSet($legacyZips));
            $zipTotals['census']  += count($this->nameSet($censusZips));
            $zipTotals['matched'] += $zipResult['matched'];

            foreach ($cityResult['only_legacy'] as $n) {
                $cityOnlyLegacy[] = "{$abbrev}: {$n}";
            }
            foreach ($cityResult['only_census'] as $n) {
                $cityOnlyCensus[] = "{$abbrev}: {$n}";
            }
            foreach ($zipResult['only_legacy'] as $n) {
                $zipOnlyLegacy[] = "{$abbrev}: {$n}";
            }
            foreach ($zipResult['only_census'] as $n) {
                $zipOnlyCensus[] = "{$abbrev}: {$n}";
            }

            $this->line(sprintf('   %s', $label));
            $this->line(sprintf(
                '      cities  eloquent %4d  census %4d  matched %4d  only-legacy %4d  only-census %4d',
                count($legacyCities),
                count($censusCities),
                $cityResult['matched'],
                count($cityResult['only_legacy']),
                count($cityResult['only_census'])
            ));
            $this->line(sprintf(
                '      zips    eloquent %4d  census %4d  matched %4d  only-legacy %4d  only-census %4d',
                count($this->nameSet($legacyZips)),
                count($this->nameSet($censusZips)),
                $zipResult['matched'],
                count($zipResult['only_legacy']),
                count($zipResult['only_census'])
            ));
        }

        $this->line('');
        $this->line(sprintf(
            '   CITY TOTALS  eloquent %4d  census %4d  matched %4d  only-legacy %4d  only-census %4d',
            $cityTotals['legacy'],
            $cityTotals['census'],
            $cityTotals['matched'],
            count($cityOnlyLegacy),
            count($cityOnlyCensus)
        ));
        $this->samples('removed by census', $cityOnlyLegacy, 20);
        $this->samples('added by census', $cityOnlyCensus, 20);

        $this->line('');
        $this->line(sprintf(
            '   ZIP TOTALS   eloquent %4d  census %4d  matched %4d  only-legacy %4d  only-census %4d',
            $zipTotals['legacy'],
            $zipTotals['census'],
            $zipTotals['matched'],
            count($zipOnlyLegacy),
            count($zipOnlyCensus)
        ));
        $this->samples('missing from census', $zipOnlyLegacy, 20);
        $this->samples('added by census', $zipOnlyCensus, 20);
    }

    // ═════════════════════════════════════════════════════════════════════
    // Loading and output
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Load the read-only `us_*` export into the test connection.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function loadUsReferenceExport(): array
    {
        $path = (string) (getenv('CENSUS_PARITY_US_EXPORT') ?: '');

        if ($path === '' || ! is_file($path)) {
            $this->markTestSkipped(
                'No us_* export available, so there is nothing real to compare against. The suite '
                .'runs an in-memory connection with empty reference tables, and inventing rows '
                .'would produce a number that looks like a measurement and is not one. Set '
                .'CENSUS_PARITY_US_EXPORT to a JSON export taken from an environment where the '
                .'us_* tables are populated. The comparison harness itself is covered by the '
                .'assertions in this file regardless.'
            );
        }

        $export = json_decode((string) file_get_contents($path), true);

        if (! is_array($export) || ! isset($export['us_states'])) {
            $this->markTestSkipped("The export at {$path} is not readable as a us_* reference export.");
        }

        foreach (['us_states', 'us_counties', 'us_cities', 'us_zip_codes'] as $table) {
            $rows = $export[$table] ?? [];

            foreach (array_chunk($rows, 400) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }

        return $export;
    }

    private function line(string $text): void
    {
        fwrite(STDOUT, $text.PHP_EOL);
    }

    private function section(string $title): void
    {
        $this->line('');
        $this->line(' '.$title);
        $this->line(' '.str_repeat('─', 66));
    }

    /** @param list<string> $values */
    private function samples(string $label, array $values, int $limit = 10): void
    {
        if ($values === []) {
            return;
        }

        $sample = array_slice($values, 0, $limit);

        $this->line(sprintf(
            '      %s (%d): %s%s',
            $label,
            count($values),
            implode(' · ', $sample),
            count($values) > count($sample) ? ' · …' : ''
        ));
    }
}
