<?php

namespace Tests\Feature\Census;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1d-1 — import integrity for `census:import-geography`.
 *
 * WHAT THIS SUITE IS FOR
 * ----------------------
 * The importer's whole justification is that the data it replaces was populated silently and
 * could not be audited afterwards. A test suite that only asserted "rows appeared" would
 * reproduce that failure one layer up. So every assertion here is about a property that, if it
 * broke, would break quietly:
 *
 *   1. GEOIDs keep their leading zeros. `01` becoming `1` would be invisible until a join
 *      returned nothing.
 *   2. A second import converges. An importer that accumulates looks identical to one that
 *      converges until the day a row is withdrawn upstream.
 *   3. Orphan relationships abort the WHOLE import. A partial import is worse than none.
 *   4. The blank-ZCTA rows are skipped and COUNTED, not silently dropped and not treated as
 *      orphans — the one approved exception, pinned by test so it cannot quietly widen.
 *   5. The `us_*` tables are not touched. Phase 1d-1 is additive; the shipped repository still
 *      serves every consumer surface from them.
 *
 * Fixtures are a real national subset — 57 states including all five territories, 438 counties,
 * 419 places, 778 ZCTA relationships and the 6 county-only rows — held in
 * tests/fixtures/census/2020.
 */
class CensusGeographyImportTest extends TestCase
{
    use DatabaseTransactions;

    /** Row counts the fixtures are known to contain, asserted rather than recomputed. */
    private const EXPECTED = [
        'census_states'         => 57,
        'census_counties'       => 438,
        'census_places'         => 419,
        'census_place_counties' => 419,
        'census_zctas'          => 778,
        'census_zcta_counties'  => 778,
    ];

    /** Rows in the ZCTA relationship file with a blank GEOID_ZCTA5_20 — valid, and skipped. */
    private const EXPECTED_BLANK_ZCTA_ROWS = 6;

    /**
     * Mirrors the chunk size the importer's prune reads existing keys with.
     *
     * Duplicated rather than derived on purpose: if the importer's chunk size changes, these
     * tests should keep crossing a boundary at the size they were reasoned about, and the
     * assertion that the corpus exceeds it will say so if the two ever stop agreeing.
     */
    private const PRUNE_CHUNK_SIZE = 2000;

    /** Synthetic places / ZCTAs appended to push a relationship table past that boundary. */
    private const SYNTHETIC_ROWS = 1200;

    /** First synthetic PLACEFP and ZCTA5. Above every code the fixtures use, so nothing collides. */
    private const SYNTHETIC_CODE_BASE = 90000;

    /** The AZ state the synthetic rows hang off. Present in the fixtures. */
    private const SYNTHETIC_STATE = '04';

    /**
     * Two real counties the synthetic places and ZCTAs are linked to, so every synthetic code
     * appears TWICE and the prune's ordering column carries ties.
     */
    private const SYNTHETIC_COUNTIES = ['001', '003'];

    /**
     * A real county that no fixture relationship row uses and that no synthetic source row links
     * to. Any row carrying it is therefore stale by construction, which is what lets the tests
     * count survivors without tracking every injected pair.
     */
    private const STALE_COUNTY = '04005';

    /** @var list<string> Temporary directories built by a test, removed in tearDown. */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->deleteDirectory($dir);
        }

        $this->tempDirs = [];

        parent::tearDown();
    }

    private function fixturePath(): string
    {
        return base_path('tests/fixtures/census/2020');
    }

    private function import(array $options = []): int
    {
        return $this->artisan('census:import-geography', array_merge(
            ['--path' => $this->fixturePath()],
            $options
        ))->run();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Happy path
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_populates_all_six_tables_from_the_fixtures(): void
    {
        $this->assertSame(0, $this->import(), 'The import should succeed against the fixtures.');

        foreach (self::EXPECTED as $table => $count) {
            $this->assertSame(
                $count,
                DB::table($table)->count(),
                "{$table} should hold exactly {$count} rows after import."
            );
        }
    }

    /** @test */
    public function it_imports_all_five_territories(): void
    {
        $this->import();

        $territories = [
            '72' => 'PR',
            '78' => 'VI',
            '66' => 'GU',
            '60' => 'AS',
            '69' => 'MP',
        ];

        foreach ($territories as $geoid => $usps) {
            $row = DB::table('census_states')->where('geoid', $geoid)->first();

            $this->assertNotNull($row, "Territory {$usps} (GEOID {$geoid}) should be imported.");
            $this->assertSame($usps, $row->usps);
        }

        // Puerto Rico's county equivalents are municipios, and they must survive the same
        // basename strip the mainland uses.
        $municipio = DB::table('census_counties')->where('geoid', '72001')->first();
        $this->assertNotNull($municipio, 'PR municipio 72001 should be imported.');
        $this->assertSame('Adjuntas Municipio', $municipio->name);
        $this->assertSame('Adjuntas', $municipio->basename);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Leading zeros
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_preserves_leading_zeros_in_every_geoid(): void
    {
        $this->import();

        // Alabama is the canonical case: '01' cast to an integer becomes 1, and every join in
        // the schema then misses.
        $alabama = DB::table('census_states')->where('usps', 'AL')->first();
        $this->assertNotNull($alabama);
        $this->assertSame('01', $alabama->geoid);

        $county = DB::table('census_counties')->where('geoid', '04001')->first();
        $this->assertNotNull($county, 'County 04001 (Apache County, AZ) should be imported.');
        $this->assertSame('04', $county->state_geoid);
        $this->assertSame('001', $county->countyfp);

        $place = DB::table('census_places')->where('geoid', '0400730')->first();
        $this->assertNotNull($place, 'Place 0400730 (Aguila CDP, AZ) should be imported.');
        $this->assertSame('04', $place->state_geoid);
        $this->assertSame('00730', $place->placefp);

        // A ZCTA beginning with zero exists in the fixtures (Puerto Rico's 007xx range).
        $zcta = DB::table('census_zctas')->where('zcta5', '00725')->first();
        $this->assertNotNull($zcta, 'ZCTA 00725 should be imported with its leading zeros.');
        $this->assertSame('00725', $zcta->zcta5);
    }

    /** @test */
    public function no_stored_identifier_has_been_shortened_by_a_numeric_cast(): void
    {
        $this->import();

        // A width check across every row is the assertion that would actually catch a cast
        // regression anywhere in the corpus, rather than at the handful of spot-checked GEOIDs.
        $widths = [
            ['census_states', 'geoid', 2],
            ['census_counties', 'geoid', 5],
            ['census_counties', 'state_geoid', 2],
            ['census_counties', 'countyfp', 3],
            ['census_places', 'geoid', 7],
            ['census_places', 'state_geoid', 2],
            ['census_places', 'placefp', 5],
            ['census_place_counties', 'place_geoid', 7],
            ['census_place_counties', 'county_geoid', 5],
            ['census_zctas', 'zcta5', 5],
            ['census_zcta_counties', 'zcta5', 5],
            ['census_zcta_counties', 'county_geoid', 5],
        ];

        foreach ($widths as [$table, $column, $width]) {
            $wrong = DB::table($table)
                ->whereRaw("LENGTH({$column}) <> ?", [$width])
                ->count();

            $this->assertSame(
                0,
                $wrong,
                "{$table}.{$column} should be {$width} characters wide in every row; {$wrong} row(s) are not."
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Idempotence
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function a_second_import_converges_to_identical_data(): void
    {
        $this->assertSame(0, $this->import());
        $first = $this->snapshot();

        $this->assertSame(0, $this->import(), 'The second import should also succeed.');
        $second = $this->snapshot();

        $this->assertSame($first, $second, 'A second import over unchanged sources must change nothing.');

        foreach (self::EXPECTED as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "{$table} row count must not drift on re-import.");
        }
    }

    /** @test */
    public function a_second_import_does_not_duplicate_relationship_rows(): void
    {
        $this->import();
        $this->import();

        // The relationship tables are the ones a non-idempotent importer would double, because
        // they have no surrogate key to collide on beyond the composite primary key.
        $this->assertSame(
            self::EXPECTED['census_place_counties'],
            DB::table('census_place_counties')->distinct()->count(DB::raw('place_geoid || county_geoid')),
            'place/county pairs must remain distinct after a re-import.'
        );

        $this->assertSame(
            self::EXPECTED['census_zcta_counties'],
            DB::table('census_zcta_counties')->distinct()->count(DB::raw('zcta5 || county_geoid')),
            'ZCTA/county pairs must remain distinct after a re-import.'
        );
    }

    /** @test */
    public function it_prunes_rows_the_sources_no_longer_contain(): void
    {
        $this->import();

        // A row that no source file describes. A merely additive importer would leave it for
        // ever, which is the failure mode `us_cities` already has.
        DB::table('census_states')->insert([
            'geoid'      => '99',
            'usps'       => 'ZZ',
            'name'       => 'Withdrawn Test State',
            'statens'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, $this->import());

        $this->assertDatabaseMissing('census_states', ['geoid' => '99']);
        $this->assertSame(self::EXPECTED['census_states'], DB::table('census_states')->count());
    }

    /** @test */
    public function skip_unchanged_short_circuits_without_altering_data(): void
    {
        $this->import();
        $before = $this->snapshot();

        $this->assertSame(0, $this->import(['--skip-unchanged' => true]));

        $this->assertSame($before, $this->snapshot(), '--skip-unchanged must leave the data exactly as it was.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // The one approved exception
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function blank_zcta_geoids_are_skipped_rather_than_failing_the_import(): void
    {
        $this->assertSame(
            0,
            $this->import(),
            'County-only rows with a blank GEOID_ZCTA5_20 are valid published data and must not abort the import.'
        );

        // They must not have been imported as an empty-string ZCTA either — that would be a
        // silent corruption dressed up as a successful run.
        $this->assertSame(0, DB::table('census_zctas')->where('zcta5', '')->count());
        $this->assertSame(0, DB::table('census_zcta_counties')->where('zcta5', '')->count());

        $this->assertSame(self::EXPECTED['census_zcta_counties'], DB::table('census_zcta_counties')->count());
    }

    /** @test */
    public function the_skipped_rows_are_counted_in_the_metadata(): void
    {
        $this->import();

        foreach (['zctas', 'zcta_counties'] as $dataset) {
            $meta = DB::table('census_geography_meta')->where('dataset', $dataset)->first();

            $this->assertNotNull($meta);
            $this->assertSame(
                self::EXPECTED_BLANK_ZCTA_ROWS,
                (int) $meta->rejected_count,
                "{$dataset} should record the 6 county-only rows it skipped, so the skip is visible rather than silent."
            );
        }

        // Every other dataset skipped nothing. If one starts skipping rows, that is a defect,
        // and this is what surfaces it.
        foreach (['states', 'counties', 'places', 'place_counties'] as $dataset) {
            $meta = DB::table('census_geography_meta')->where('dataset', $dataset)->first();

            $this->assertSame(0, (int) $meta->rejected_count, "{$dataset} should skip no rows.");
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Orphans fail loudly
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function an_orphan_place_county_relationship_aborts_the_whole_import(): void
    {
        $dir = $this->fixtureCopyWithLineAppended(
            'national_place_by_county2020.txt',
            'AZ|04|999|Nonexistent County|02830|02409718|Apache Junction city|INCORPORATED PLACE|C1|A'
        );

        $this->assertSame(1, $this->artisan('census:import-geography', ['--path' => $dir])->run());

        $this->assertNothingWasWritten();
    }

    /** @test */
    public function an_orphan_zcta_county_relationship_aborts_the_whole_import(): void
    {
        $dir = $this->fixtureCopyWithLineAppended(
            'tab20_zcta520_county20_natl.txt',
            '1|85001|ZCTA5 85001|1000|0|G6350|B5|S|2|99999|Nonexistent County|1000|0|G4020|H1|A|1000|0'
        );

        $this->assertSame(1, $this->artisan('census:import-geography', ['--path' => $dir])->run());

        $this->assertNothingWasWritten();
    }

    /** @test */
    public function a_county_referencing_an_unknown_state_aborts_the_whole_import(): void
    {
        $dir = $this->fixtureCopyWithLineAppended(
            'national_county2020.txt',
            'ZZ|99|001|00099999|Nonexistent County|H1|A'
        );

        $this->assertSame(1, $this->artisan('census:import-geography', ['--path' => $dir])->run());

        $this->assertNothingWasWritten();
    }

    /** @test */
    public function a_missing_source_file_aborts_before_anything_is_read(): void
    {
        $dir = $this->fixtureCopy();
        unlink($dir . '/national_place2020.txt');

        $this->assertSame(1, $this->artisan('census:import-geography', ['--path' => $dir])->run());

        $this->assertNothingWasWritten();
    }

    /** @test */
    public function an_unsupported_vintage_is_refused(): void
    {
        $this->assertSame(1, $this->import(['--vintage' => '2010']));

        $this->assertNothingWasWritten();
    }

    /** @test */
    public function a_failed_import_leaves_a_previously_good_import_intact(): void
    {
        $this->assertSame(0, $this->import());
        $good = $this->snapshot();

        $dir = $this->fixtureCopyWithLineAppended(
            'national_place_by_county2020.txt',
            'AZ|04|999|Nonexistent County|02830|02409718|Apache Junction city|INCORPORATED PLACE|C1|A'
        );

        $this->assertSame(1, $this->artisan('census:import-geography', ['--path' => $dir])->run());

        // The abort must be a rollback, not a truncate-then-fail. This is the property that
        // makes a failed re-import safe to run in production.
        $this->assertSame($good, $this->snapshot(), 'A failed import must leave the previous data untouched.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Many-to-many, proven on purpose-built data
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function a_place_spanning_two_counties_gets_one_row_per_county(): void
    {
        // The national fixtures happen to carry exactly one county per place, so they cannot
        // demonstrate the many-to-many rule this schema exists to support. This builds the case
        // explicitly rather than asserting a property the data cannot show.
        $dir = $this->fixtureCopyWithLineAppended(
            'national_place_by_county2020.txt',
            'AZ|04|021|Pinal County|02830|02409718|Apache Junction city|INCORPORATED PLACE|C1|A'
        );

        $this->assertSame(0, $this->artisan('census:import-geography', ['--path' => $dir])->run());

        $counties = DB::table('census_place_counties')
            ->where('place_geoid', '0402830')
            ->orderBy('county_geoid')
            ->pluck('county_geoid')
            ->all();

        $this->assertSame(
            ['04013', '04021'],
            $counties,
            'Apache Junction spans Maricopa and Pinal and must hold a row for each.'
        );
    }

    /** @test */
    public function an_exactly_repeated_relationship_row_collapses_instead_of_colliding(): void
    {
        // A duplicated line in a source file must not violate the composite primary key.
        $dir = $this->fixtureCopyWithLineAppended(
            'national_place_by_county2020.txt',
            'AZ|04|013|Maricopa County|00730|02582720|Aguila CDP|CENSUS DESIGNATED PLACE|U1|S'
        );

        $this->assertSame(0, $this->artisan('census:import-geography', ['--path' => $dir])->run());

        $this->assertSame(
            1,
            DB::table('census_place_counties')
                ->where('place_geoid', '0400730')
                ->where('county_geoid', '04013')
                ->count()
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Name normalisation
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function place_names_are_stripped_using_the_lsad_code(): void
    {
        $this->import();

        $cases = [
            // geoid      => [namelsad,                published lsad, expected bare name]
            '0402830' => ['Apache Junction city', '25', 'Apache Junction'],
            '0400730' => ['Aguila CDP',           '57', 'Aguila'],
        ];

        foreach ($cases as $geoid => [$namelsad, $lsad, $expected]) {
            $place = DB::table('census_places')->where('geoid', $geoid)->first();

            $this->assertNotNull($place, "Place {$geoid} should be imported.");
            $this->assertSame($namelsad, $place->namelsad, 'The published name must be retained verbatim.');
            $this->assertSame($lsad, $place->lsad, 'The LSAD code must be stored so the strip stays auditable.');
            $this->assertSame($expected, $place->name);
        }
    }

    /** @test */
    public function no_place_name_was_stripped_to_nothing(): void
    {
        $this->import();

        $this->assertSame(
            0,
            DB::table('census_places')->where('name', '')->orWhereNull('name')->count(),
            'A blank place name means the suffix strip consumed the whole name.'
        );

        $this->assertSame(
            0,
            DB::table('census_counties')->where('basename', '')->orWhereNull('basename')->count(),
            'A blank county basename means the class-word strip consumed the whole name.'
        );
    }

    /** @test */
    public function county_names_keep_their_published_form_and_gain_a_basename(): void
    {
        $this->import();

        $county = DB::table('census_counties')->where('geoid', '04001')->first();

        // The published form is what historical stored labels carry ("Pinellas County, FL"),
        // so it must survive verbatim alongside the loose-matching basename.
        $this->assertSame('Apache County', $county->name);
        $this->assertSame('Apache', $county->basename);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Metadata
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function it_records_provenance_for_every_dataset(): void
    {
        $this->import();

        $datasets = ['states', 'counties', 'places', 'place_counties', 'zctas', 'zcta_counties'];

        $this->assertSame(count($datasets), DB::table('census_geography_meta')->count());

        foreach ($datasets as $dataset) {
            $meta = DB::table('census_geography_meta')->where('dataset', $dataset)->first();

            $this->assertNotNull($meta, "Provenance should be recorded for '{$dataset}'.");
            $this->assertSame('2020', $meta->vintage);
            $this->assertNotNull($meta->imported_at);
            $this->assertStringContainsString('census.gov', $meta->source_url);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $meta->sha256);
            $this->assertSame(
                DB::table('census_' . $dataset)->count(),
                (int) $meta->row_count,
                "row_count for '{$dataset}' should match what is actually in the table."
            );
        }
    }

    /** @test */
    public function the_recorded_hash_is_the_digest_of_the_source_file(): void
    {
        $this->import();

        // A recorded hash that is not the file's hash would make an upstream change invisible,
        // which is the whole reason the column exists.
        $meta = DB::table('census_geography_meta')->where('dataset', 'states')->first();

        $this->assertSame(
            hash_file('sha256', $this->fixturePath() . '/national_state2020.txt'),
            $meta->sha256
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Blast radius
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function the_legacy_us_tables_are_left_untouched(): void
    {
        $legacy = ['us_states', 'us_counties', 'us_cities', 'us_zip_codes'];

        $before = [];
        foreach ($legacy as $table) {
            $before[$table] = DB::table($table)->count();
        }

        $this->import();

        foreach ($legacy as $table) {
            $this->assertSame(
                $before[$table],
                DB::table($table)->count(),
                "{$table} must not be modified by the Census import — Phase 1d-1 is additive."
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Pruning past a chunk boundary (F1)
    // ─────────────────────────────────────────────────────────────────────
    //
    // The prune reads existing keys with chunk(), which paginates by LIMIT/OFFSET. On the two
    // relationship tables the first key column is NOT unique — a place spans several counties,
    // a ZCTA spans several counties — so ordering by it alone leaves ties whose order between
    // pages is not guaranteed. A tie straddling a page boundary can be skipped, and a skipped
    // row is a stale row that survives: the table quietly stops being a projection of the files.
    //
    // The fixtures hold 419 and 778 relationship rows, both under one chunk, so the boundary is
    // never crossed by the other tests. These two build a corpus that crosses it several times
    // and carries ties throughout.

    /** @test */
    public function stale_place_county_rows_are_pruned_across_chunk_boundaries(): void
    {
        $dir = $this->fixtureCopyWithSyntheticPlaces();

        $this->assertSame(0, $this->import(['--path' => $dir]), 'The enlarged corpus should import cleanly.');

        $converged = DB::table('census_place_counties')->count();

        $this->assertGreaterThan(
            self::PRUNE_CHUNK_SIZE,
            $converged,
            'This test proves nothing unless the table exceeds the prune chunk size.'
        );

        $stale = $this->injectStalePlaceCounties();

        $this->assertSame(
            $converged + count($stale),
            DB::table('census_place_counties')->count(),
            'Guard: the stale rows must actually have been inserted.'
        );

        $this->assertSame(0, $this->import(['--path' => $dir]), 'The re-import should succeed.');

        $survivors = DB::table('census_place_counties')
            ->where('county_geoid', self::STALE_COUNTY)
            ->count();

        $this->assertSame(
            0,
            $survivors,
            "{$survivors} stale place/county row(s) survived the prune. Every one of them is a row no "
            . 'source file describes, so the table is no longer a projection of the sources.'
        );

        $this->assertSame($converged, DB::table('census_place_counties')->count());
    }

    /** @test */
    public function stale_zcta_county_rows_are_pruned_across_chunk_boundaries(): void
    {
        $dir = $this->fixtureCopyWithSyntheticZctas();

        $this->assertSame(0, $this->import(['--path' => $dir]), 'The enlarged corpus should import cleanly.');

        $converged = DB::table('census_zcta_counties')->count();

        $this->assertGreaterThan(
            self::PRUNE_CHUNK_SIZE,
            $converged,
            'This test proves nothing unless the table exceeds the prune chunk size.'
        );

        $stale = $this->injectStaleZctaCounties();

        $this->assertSame($converged + count($stale), DB::table('census_zcta_counties')->count());

        $this->assertSame(0, $this->import(['--path' => $dir]), 'The re-import should succeed.');

        $survivors = DB::table('census_zcta_counties')
            ->where('county_geoid', self::STALE_COUNTY)
            ->count();

        $this->assertSame(0, $survivors, "{$survivors} stale ZCTA/county row(s) survived the prune.");
        $this->assertSame($converged, DB::table('census_zcta_counties')->count());
    }

    /**
     * The ties themselves, asserted rather than assumed.
     *
     * If a future change to the generator produced one relationship row per place, the two tests
     * above would still pass and would silently stop testing the thing they exist for.
     */
    /** @test */
    public function the_enlarged_corpus_actually_contains_ties_on_the_ordering_column(): void
    {
        $dir = $this->fixtureCopyWithSyntheticPlaces();
        $this->import(['--path' => $dir]);

        $placesWithSeveralCounties = DB::table('census_place_counties')
            ->select('place_geoid')
            ->groupBy('place_geoid')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $this->assertGreaterThanOrEqual(
            self::SYNTHETIC_ROWS,
            $placesWithSeveralCounties,
            'The corpus must contain places spanning more than one county, or the ordering column '
            . 'has no ties and the chunk-boundary hazard is not being exercised.'
        );
    }

    /**
     * The ordering itself, asserted at the source.
     *
     * The two tests above prove the PROPERTY — stale rows go, at a size that crosses several
     * chunk boundaries — but they cannot prove the HAZARD is gone, because SQLite happens to
     * return tied rows in a stable order and so does not reproduce it. Postgres, which is what
     * production runs, offers no such guarantee: a seq scan, an index scan and a parallel scan
     * can each order ties differently for each page.
     *
     * So the guarantee is pinned where it can be: the sort must be total. This is the same
     * shape as the other regression guards in this codebase, and it is the assertion that
     * fails if `orderBy($keyColumns[0])` is ever reintroduced.
     */
    /** @test */
    public function the_prune_orders_by_every_key_column_not_just_the_first(): void
    {
        $source = (string) file_get_contents(app_path('Console/Commands/ImportCensusGeography.php'));

        $this->assertStringNotContainsString(
            'orderBy($keyColumns[0])',
            $source,
            'The prune must not order by the first key column alone: on census_place_counties and '
            . 'census_zcta_counties that column is not unique, so a tie straddling a LIMIT/OFFSET '
            . 'page boundary can be skipped and its stale row survives.'
        );

        $this->assertMatchesRegularExpression(
            '/foreach \(\$keyColumns as \$column\) \{\s*\$query->orderBy\(\$column\);\s*\}/',
            $source,
            'The prune should order by every key column, which is unique by definition and makes '
            . 'the pagination deterministic.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // A blank code is not zero (F2)
    // ─────────────────────────────────────────────────────────────────────
    //
    // str_pad('', 2, '0', STR_PAD_LEFT) is '00', which satisfies a two-digit check and enters the
    // schema as a real identifier. For a child row the orphan check catches it — no state '00'
    // exists. For a ROSTER row nothing does: a blank STATEFP becomes state '00' and is imported.
    // The published files carry no blanks, but a source that has been through a spreadsheet will,
    // and that is the case the padding was written for.

    /** @test */
    public function a_blank_state_code_is_refused_rather_than_becoming_state_zero(): void
    {
        $dir = $this->fixtureCopyWithLineAppended(
            'national_state2020.txt',
            'ZZ||00000099|Blank Code Test State'
        );

        $this->assertSame(
            1,
            $this->artisan('census:import-geography', ['--path' => $dir])->run(),
            'A blank STATEFP must abort the import, not become state 00.'
        );

        $this->assertNothingWasWritten();
    }

    /** @test */
    public function a_blank_county_code_is_refused_rather_than_becoming_county_zero(): void
    {
        $dir = $this->fixtureCopyWithLineAppended(
            'national_county2020.txt',
            'AZ|04||00025441|Blank Code Test County|H1|A'
        );

        $this->assertSame(1, $this->artisan('census:import-geography', ['--path' => $dir])->run());
        $this->assertNothingWasWritten();
    }

    /** @test */
    public function a_blank_place_code_is_refused_rather_than_becoming_place_zero(): void
    {
        $dir = $this->fixtureCopyWithLineAppended(
            'national_place2020.txt',
            'AZ|04||02582720|Blank Code Test place|INCORPORATED PLACE|C1|A|Apache County'
        );

        $this->assertSame(1, $this->artisan('census:import-geography', ['--path' => $dir])->run());
        $this->assertNothingWasWritten();
    }

    /** @test */
    public function no_zero_identifier_is_ever_created_by_a_clean_import(): void
    {
        $this->import();

        $this->assertSame(0, DB::table('census_states')->where('geoid', '00')->count());
        $this->assertSame(0, DB::table('census_counties')->where('countyfp', '000')->count());
        $this->assertSame(0, DB::table('census_places')->where('placefp', '00000')->count());
    }

    /**
     * The hardening must not swallow the one approved exception.
     *
     * The blank-ZCTA rows are recognised and counted BEFORE any code is validated, so rejecting
     * blank codes must leave them exactly as they were. If this ever fails, the fix for F2 has
     * widened into behaviour that was deliberately allowed.
     */
    /** @test */
    public function the_approved_blank_zcta_skip_survives_the_blank_code_hardening(): void
    {
        $this->assertSame(0, $this->import());

        $this->assertSame(
            self::EXPECTED_BLANK_ZCTA_ROWS,
            (int) DB::table('census_geography_meta')->where('dataset', 'zcta_counties')->value('rejected_count')
        );

        $this->assertSame(self::EXPECTED['census_zcta_counties'], DB::table('census_zcta_counties')->count());
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Every census_* row, ordered and stripped of timestamps.
     *
     * Timestamps are excluded deliberately: `updated_at` moves on every upsert by design, and
     * comparing it would make the idempotence assertion fail for a reason that is not about the
     * data. What must be stable is the geography itself.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function snapshot(): array
    {
        $snapshot = [];

        $tables = [
            'census_states'         => 'geoid',
            'census_counties'       => 'geoid',
            'census_places'         => 'geoid',
            'census_place_counties' => 'place_geoid',
            'census_zctas'          => 'zcta5',
            'census_zcta_counties'  => 'zcta5',
        ];

        foreach ($tables as $table => $order) {
            $snapshot[$table] = DB::table($table)
                ->orderBy($order)
                ->get()
                ->map(static function ($row): array {
                    $values = (array) $row;
                    unset($values['created_at'], $values['updated_at']);

                    return $values;
                })
                ->all();
        }

        return $snapshot;
    }

    /** Assert the import wrote nothing at all — used by every fail-loudly case. */
    private function assertNothingWasWritten(): void
    {
        foreach (array_keys(self::EXPECTED) as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} must be empty after an aborted import.");
        }

        $this->assertSame(0, DB::table('census_geography_meta')->count());
    }

    /** Copy the fixture directory to a scratch location the test may mutate. */
    private function fixtureCopy(): string
    {
        $dir = sys_get_temp_dir() . '/census-fixture-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        $this->tempDirs[] = $dir;

        foreach (glob($this->fixturePath() . '/*') ?: [] as $file) {
            copy($file, $dir . '/' . basename($file));
        }

        return $dir;
    }

    /** Copy the fixtures and append one raw line to a named file. */
    private function fixtureCopyWithLineAppended(string $file, string $line): string
    {
        $dir = $this->fixtureCopy();

        file_put_contents($dir . '/' . $file, PHP_EOL . $line, FILE_APPEND);

        return $dir;
    }

    /** The synthetic place GEOIDs, in the order the generator emits them. @return list<string> */
    private function syntheticPlaceGeoids(): array
    {
        $geoids = [];

        for ($i = 0; $i < self::SYNTHETIC_ROWS; $i++) {
            $geoids[] = self::SYNTHETIC_STATE . $this->syntheticCode($i);
        }

        return $geoids;
    }

    /** The synthetic ZCTA5 codes. @return list<string> */
    private function syntheticZcta5s(): array
    {
        $codes = [];

        for ($i = 0; $i < self::SYNTHETIC_ROWS; $i++) {
            $codes[] = $this->syntheticCode($i);
        }

        return $codes;
    }

    private function syntheticCode(int $offset): string
    {
        return (string) (self::SYNTHETIC_CODE_BASE + $offset);
    }

    /**
     * Fixtures plus SYNTHETIC_ROWS places, each linked to TWO counties.
     *
     * Two links per place is the point: it puts 2 × SYNTHETIC_ROWS rows in
     * `census_place_counties` — past the chunk boundary — and it makes every synthetic
     * `place_geoid` a tie in the column the prune orders by.
     */
    private function fixtureCopyWithSyntheticPlaces(): string
    {
        $dir = $this->fixtureCopy();

        $places        = [];
        $relationships = [];

        foreach ($this->syntheticPlaceGeoids() as $i => $geoid) {
            $placefp = substr($geoid, 2);

            // STATE|STATEFP|PLACEFP|PLACENS|PLACENAME|TYPE|CLASSFP|FUNCSTAT|COUNTIES
            $places[] = sprintf(
                'AZ|%s|%s|9%06d|Synthetic %d town|INCORPORATED PLACE|C1|A|Apache County',
                self::SYNTHETIC_STATE,
                $placefp,
                $i,
                $i
            );

            foreach (self::SYNTHETIC_COUNTIES as $countyfp) {
                // STATE|STATEFP|COUNTYFP|COUNTYNAME|PLACEFP|PLACENS|PLACENAME|TYPE|CLASSFP|FUNCSTAT
                $relationships[] = sprintf(
                    'AZ|%s|%s|Synthetic County|%s|9%06d|Synthetic %d town|INCORPORATED PLACE|C1|A',
                    self::SYNTHETIC_STATE,
                    $countyfp,
                    $placefp,
                    $i,
                    $i
                );
            }
        }

        $this->appendLines($dir . '/national_place2020.txt', $places);
        $this->appendLines($dir . '/national_place_by_county2020.txt', $relationships);

        return $dir;
    }

    /** Fixtures plus SYNTHETIC_ROWS ZCTAs, each linked to TWO counties, for the same reason. */
    private function fixtureCopyWithSyntheticZctas(): string
    {
        $dir  = $this->fixtureCopy();
        $rows = [];

        foreach ($this->syntheticZcta5s() as $i => $zcta5) {
            foreach (self::SYNTHETIC_COUNTIES as $countyfp) {
                // Only GEOID_ZCTA5_20 (2), GEOID_COUNTY_20 (10) and AREALAND_PART (17) are read;
                // the remaining columns exist so the row has the 18 the header declares.
                $rows[] = sprintf(
                    '1|%s|ZCTA5 %s|1|0|G6350|B5|S|2|%s%s|Synthetic County|1|0|G4020|H1|A|%d|0',
                    $zcta5,
                    $zcta5,
                    self::SYNTHETIC_STATE,
                    $countyfp,
                    1000 + $i
                );
            }
        }

        $this->appendLines($dir . '/tab20_zcta520_county20_natl.txt', $rows);

        return $dir;
    }

    /**
     * Insert referentially VALID place/county rows that no source describes.
     *
     * Valid on purpose. An orphan would be caught by the post-write integrity check even if the
     * prune missed it, so only a well-formed stale row can prove the prune itself did the work.
     *
     * @return list<string> the place GEOIDs made stale
     */
    private function injectStalePlaceCounties(): array
    {
        $geoids = $this->syntheticPlaceGeoids();
        $rows   = [];

        foreach ($geoids as $geoid) {
            $rows[] = [
                'place_geoid'  => $geoid,
                'county_geoid' => self::STALE_COUNTY,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('census_place_counties')->insert($chunk);
        }

        return $geoids;
    }

    /** The ZCTA equivalent. @return list<string> */
    private function injectStaleZctaCounties(): array
    {
        $codes = $this->syntheticZcta5s();
        $rows  = [];

        foreach ($codes as $zcta5) {
            $rows[] = [
                'zcta5'         => $zcta5,
                'county_geoid'  => self::STALE_COUNTY,
                'arealand_part' => null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('census_zcta_counties')->insert($chunk);
        }

        return $codes;
    }

    /** @param list<string> $lines */
    private function appendLines(string $path, array $lines): void
    {
        file_put_contents($path, PHP_EOL . implode(PHP_EOL, $lines), FILE_APPEND);
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->deleteDirectory($file) : unlink($file);
        }

        rmdir($dir);
    }
}
