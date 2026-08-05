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
 * Phase 1d-2 — MEASUREMENT, not a pass/fail gate.
 *
 * WHAT THIS FILE IS FOR, AND WHAT IT REFUSES TO DO
 * ------------------------------------------------
 * Switching `geography_source` changes which places exist. Some of that change is the improvement
 * the whole phase is for — 32,188 published places instead of a USPS ZIP-locality list; ZIPs in
 * the five states `us_zip_codes` covers not at all; the 280 counties that currently resolve no
 * ZIPs. Some of it is loss: a place name that no longer matches a stored label, and USPS ZIPs
 * that have no ZCTA at all because the Census only tabulates areas with addressable population.
 *
 * Which of those outweighs the other is a product decision about data, not something a test may
 * decide. So this file asserts NOTHING about the size of any difference and changes NOTHING based
 * on what it finds. It counts, and it prints. The numbers are the input to a go/no-go conversation
 * that happens outside the suite.
 *
 * WHY MOST OF IT SKIPS IN CI, AND WHY THAT IS CORRECT
 * ---------------------------------------------------
 * A real measurement needs both corpora populated in one database. In the suite neither is: the
 * `us_*` reference tables are empty in the in-memory connection, and `census_*` is empty until
 * something imports it. Seeding both with invented rows would produce a number that looks like a
 * measurement and is not one — the single most misleading thing this file could do.
 *
 * So the real-corpus report SKIPS unless it finds both populated, naming what is missing. Point it
 * at an environment where the reference tables are real and `census:import-geography` has run, and
 * it reports. The harness itself is proven separately, on controlled fixtures, so this file is
 * never vacuous even when the report cannot run.
 */
class CensusEloquentParityTest extends TestCase
{
    use DatabaseTransactions;

    private CensusCriteriaGeographyRepository $census;

    private EloquentCriteriaGeographyRepository $eloquent;

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
     * other, so an id comparison would report 100% difference and mean nothing. Names are what the
     * stored blob carries and what the hydrator matches on, so a name that exists on one side and
     * not the other is exactly the thing that decides whether a stored selection survives.
     *
     * Normalised the way the hydrator normalises: lowercased, whitespace collapsed. Anything more
     * aggressive would flatter the result.
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
            $key = trim((string) preg_replace('/\s+/', ' ', mb_strtolower($option->name)));

            if ($key !== '') {
                $set[$key] ??= $option->name;
            }
        }

        return $set;
    }

    private function report(string $line): void
    {
        fwrite(STDOUT, PHP_EOL.'    '.$line);
    }

    /** @param array{matched: int, only_legacy: list<string>, only_census: list<string>} $result */
    private function reportComparison(string $tier, array $result): void
    {
        $this->report(sprintf(
            '%-10s matched %5d | only legacy %5d | only census %5d',
            $tier,
            $result['matched'],
            count($result['only_legacy']),
            count($result['only_census'])
        ));

        foreach (['only legacy' => 'only_legacy', 'only census' => 'only_census'] as $label => $key) {
            if ($result[$key] === []) {
                continue;
            }

            $sample = array_slice($result[$key], 0, 8);

            $this->report(sprintf(
                '             %s e.g. %s%s',
                $label,
                implode(', ', $sample),
                count($result[$key]) > count($sample) ? ', …' : ''
            ));
        }
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

    // ═════════════════════════════════════════════════════════════════════
    // The report
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Enumerate both sources across every state both can see, and print the deltas.
     *
     * Asserts only that both implementations honour the interface — that every option is of the
     * kind its tier promises. The differences themselves are reported, never asserted.
     */
    /** @test */
    public function it_reports_the_difference_between_the_two_sources(): void
    {
        $this->skipUnlessBothCorporaArePopulated();

        $legacyStates = $this->eloquent->states();
        $censusStates = $this->census->states();

        $this->report('');
        $this->report('── Criteria geography parity: us_* vs census_* ──');
        $this->reportComparison('states', $this->compare($legacyStates, $censusStates));

        // Pair the states by name so each tier below is compared within the same state rather than
        // across the whole country, where a duplicate county name would match the wrong parent.
        $censusByName = [];

        foreach ($censusStates as $option) {
            $censusByName[$this->nameKey($option->name)] = $option->id;
        }

        $totals = [
            'counties' => ['matched' => 0, 'only_legacy' => 0, 'only_census' => 0],
            'cities'   => ['matched' => 0, 'only_legacy' => 0, 'only_census' => 0],
            'zips'     => ['matched' => 0, 'only_legacy' => 0, 'only_census' => 0],
        ];

        foreach ($legacyStates as $legacyState) {
            $censusStateId = $censusByName[$this->nameKey($legacyState->name)] ?? null;

            if ($censusStateId === null) {
                continue;
            }

            $legacyCounties = $this->eloquent->countiesInState($legacyState->id);
            $censusCounties = $this->census->countiesInState($censusStateId);

            $this->accumulate($totals['counties'], $this->compare($legacyCounties, $censusCounties));

            $legacyCountyIds = $this->idsOf($legacyCounties);
            $censusCountyIds = $this->idsOf($censusCounties);

            $this->accumulate($totals['cities'], $this->compare(
                $this->eloquent->citiesInCounties($legacyCountyIds),
                $this->census->citiesInCounties($censusCountyIds)
            ));

            $this->accumulate($totals['zips'], $this->compare(
                $this->eloquent->zipsInCounties($legacyCountyIds),
                $this->census->zipsInCounties($censusCountyIds)
            ));
        }

        foreach ($totals as $tier => $counts) {
            $this->report(sprintf(
                '%-10s matched %5d | only legacy %5d | only census %5d',
                $tier,
                $counts['matched'],
                $counts['only_legacy'],
                $counts['only_census']
            ));
        }

        $this->report('');
        $this->report('"only legacy" is what a stored selection would stop matching.');
        $this->report('"only census" is coverage the reference tables never had.');
        $this->report('');

        // The only assertions in the report: both sides honour the contract.
        $this->assertEveryOptionIsOfKind($legacyStates, GeographyOption::KIND_STATE);
        $this->assertEveryOptionIsOfKind($censusStates, GeographyOption::KIND_STATE);
    }

    // ═════════════════════════════════════════════════════════════════════
    // Helpers
    // ═════════════════════════════════════════════════════════════════════

    private function nameKey(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower($name)));
    }

    /** @param list<GeographyOption> $options @return list<string> */
    private function idsOf(array $options): array
    {
        return array_values(array_unique(array_map(
            static fn (GeographyOption $o): string => $o->id,
            $options
        )));
    }

    /**
     * @param  array{matched: int, only_legacy: int, only_census: int}                       $totals
     * @param  array{matched: int, only_legacy: list<string>, only_census: list<string>}     $result
     */
    private function accumulate(array &$totals, array $result): void
    {
        $totals['matched']     += $result['matched'];
        $totals['only_legacy'] += count($result['only_legacy']);
        $totals['only_census'] += count($result['only_census']);
    }

    /** @param list<GeographyOption> $options */
    private function assertEveryOptionIsOfKind(array $options, string $kind): void
    {
        foreach ($options as $option) {
            $this->assertTrue(
                $option->is($kind),
                "Both implementations must emit only `{$kind}` options for this tier."
            );
        }
    }

    /**
     * Skip with a message that says exactly what is missing and how to supply it.
     *
     * A silent skip would let this file rot into decoration. A skip that names the two conditions
     * tells the next reader precisely what kind of environment produces a real measurement.
     */
    private function skipUnlessBothCorporaArePopulated(): void
    {
        $legacyRows = DB::table('us_states')->count();
        $censusRows = DB::table('census_states')->count();

        if ($legacyRows > 0 && $censusRows > 0) {
            return;
        }

        $missing = [];

        if ($legacyRows === 0) {
            $missing[] = 'the us_* reference tables are empty';
        }

        if ($censusRows === 0) {
            $missing[] = 'the census_* corpus is empty (run census:import-geography)';
        }

        $this->markTestSkipped(
            'Parity can only be measured where both sources are populated: '
            .implode(' and ', $missing).'. The comparison harness itself is covered by the '
            .'assertions above; this reports real numbers only against a real environment.'
        );
    }

    /** The interface both sides implement, asserted once so the report cannot drift off-contract. */
    /** @test */
    public function both_implementations_satisfy_the_same_interface(): void
    {
        $this->assertInstanceOf(CriteriaGeographyRepository::class, $this->census);
        $this->assertInstanceOf(CriteriaGeographyRepository::class, $this->eloquent);
    }
}
