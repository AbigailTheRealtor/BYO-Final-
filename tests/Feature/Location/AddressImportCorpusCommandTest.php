<?php

namespace Tests\Feature\Location;

use App\Services\Location\AddressCorpus\NadSourceReader;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The dry run: what it reports, and the far more important question of what it
 * touches.
 *
 * The assertions fall into three groups, and the ordering is the priority
 * ordering. First, what the command must never do — no query on any connection,
 * no outbound request, no write path reachable. Second, what it must refuse:
 * every required option, checked one at a time, because a guard that only works
 * when the other guards also fire is not a guard. Third, what it reports, which
 * matters least right up until somebody makes an import decision from it.
 */
class AddressImportCorpusCommandTest extends TestCase
{
    private const FIXTURE   = 'tests/fixtures/address-corpus/nad-florida-sample.csv';
    private const RENAMED   = 'tests/fixtures/address-corpus/nad-renamed-schema.csv';
    private const NO_NUMBER = 'tests/fixtures/address-corpus/nad-no-address-number.csv';
    private const DUP_REF   = 'tests/fixtures/address-corpus/nad-duplicate-source-ref.csv';

    private function fixture(string $rel = self::FIXTURE): string
    {
        return base_path($rel);
    }

    /** @return array{code:int, output:string} */
    private function dry(array $options = [], string $file = self::FIXTURE): array
    {
        $code = Artisan::call('address:import-corpus', array_merge([
            'file'             => $this->fixture($file),
            '--source'         => 'nad',
            '--state-fips'     => '12',
            '--corpus-version' => 'nad-2026-06-fl',
            '--dry-run'        => true,
            '--collisions'     => 'none',
        ], $options));

        return ['code' => $code, 'output' => Artisan::output()];
    }

    /** A run with exact collision measurement, into a spill dir we can inspect. */
    private function exact(array $options = [], string $file = self::FIXTURE): array
    {
        return $this->dry(array_merge(['--collisions' => 'exact'], $options), $file);
    }

    private function tempDir(string $suffix = ''): string
    {
        $dir = sys_get_temp_dir() . '/nad-test-' . getmypid() . '-' . bin2hex(random_bytes(4)) . $suffix;
        mkdir($dir, 0700, true);

        return $dir;
    }

    /** @return list<string> */
    private function spillFilesIn(string $dir): array
    {
        return glob($dir . '/nad-normalized-*') ?: [];
    }

    private function jsonFrom(array $options = [], string $file = self::FIXTURE): array
    {
        $path = $this->tempDir() . '/report.json';

        $this->dry(array_merge(['--json' => $path], $options), $file);

        $data = json_decode((string) file_get_contents($path), true);
        @unlink($path);

        $this->assertIsArray($data);

        return $data;
    }

    // ── the non-mutation guarantee ──────────────────────────────────────────

    public function test_a_dry_run_executes_no_database_query_on_any_connection(): void
    {
        // The strong form of "non-mutating". Not "issues no writes" — issues
        // nothing at all, which is a property a reviewer can verify by the
        // absence of persistence code rather than by reading every statement.
        $queries = [];
        DB::listen(function ($q) use (&$queries) { $queries[] = $q->sql; });

        $run = $this->dry();

        $this->assertSame(0, $run['code']);
        $this->assertSame([], $queries, "A dry run issued SQL:\n" . implode("\n", $queries));
    }

    public function test_a_dry_run_leaves_application_tables_untouched(): void
    {
        $before = $this->tableCounts();

        $this->dry();

        $this->assertSame($before, $this->tableCounts());
    }

    /**
     * Row count for every table in the test database.
     *
     * The blunt instrument on purpose: the query-count assertion above proves
     * the command issued no SQL, and this proves the observable state is
     * identical regardless of how it got there. Two independent proofs of the
     * same promise, because the promise is the whole point of a dry run.
     *
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        $counts = [];

        foreach (DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'") as $table) {
            $name = $table->name;
            $counts[$name] = (int) DB::table($name)->count();
        }

        ksort($counts);

        return $counts;
    }

    public function test_a_dry_run_makes_no_outbound_request(): void
    {
        Http::fake();

        $this->dry();

        Http::assertNothingSent();
    }

    public function test_the_command_never_names_the_spatial_connection_on_the_dry_run_path(): void
    {
        // A grep-level guard, because the connection this would reach is the one
        // holding the corpus. If persistence is added later it must arrive with
        // its own approved review, not by a helper quietly resolving a builder.
        $source = file_get_contents(base_path('app/Console/Commands/AddressImportCorpus.php'));

        $this->assertIsString($source);

        foreach (['DB::connection', 'DB::table', '->insert(', '->upsert(', 'Schema::'] as $needle) {
            $this->assertStringNotContainsString($needle, $source, "Dry-run command must not contain {$needle}");
        }
    }

    public function test_execute_is_refused(): void
    {
        $run = $this->dry(['--execute' => true]);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('not available', $run['output']);
    }

    public function test_execute_is_refused_even_when_every_other_option_is_absent(): void
    {
        // The refusal must not depend on passing validation first — --execute is
        // checked before anything reads a file or a jurisdiction.
        $code = Artisan::call('address:import-corpus', [
            'file'      => $this->fixture(),
            '--execute' => true,
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('not available', Artisan::output());
    }

    public function test_dry_run_must_be_explicit(): void
    {
        $code = Artisan::call('address:import-corpus', [
            'file'             => $this->fixture(),
            '--source'         => 'nad',
            '--state-fips'     => '12',
            '--corpus-version' => 'nad-2026-06-fl',
        ]);

        $this->assertSame(1, $code);
    }

    // ── required scope: source, jurisdiction, version ───────────────────────

    public function test_source_is_required(): void
    {
        $run = $this->dry(['--source' => '']);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('--source is required', $run['output']);
    }

    public function test_an_unsupported_source_fails_closed(): void
    {
        // Not "falls back to NAD". A source this command cannot parse must stop
        // the run, or the report describes a schema it never read.
        $run = $this->dry(['--source' => 'openaddresses']);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('is not supported', $run['output']);
    }

    public function test_state_fips_is_required(): void
    {
        $run = $this->dry(['--state-fips' => '']);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('known FIPS code', $run['output']);
    }

    public function test_an_unknown_state_fips_is_refused(): void
    {
        $run = $this->dry(['--state-fips' => '99']);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('known FIPS code', $run['output']);
    }

    public function test_there_is_no_nationwide_or_default_scope(): void
    {
        // Three separate omissions, each independently fatal. Nothing about this
        // command has a default that would scan everything.
        $definition = Artisan::all()['address:import-corpus']->getDefinition();

        $this->assertNull($definition->getOption('state-fips')->getDefault());
        $this->assertNull($definition->getOption('source')->getDefault());
        $this->assertNull($definition->getOption('corpus-version')->getDefault());
        $this->assertFalse($definition->hasOption('all'));
    }

    /** @dataProvider malformedCorpusVersions */
    public function test_an_invalid_corpus_version_is_refused(string $version): void
    {
        $run = $this->dry(['--corpus-version' => $version]);

        $this->assertSame(1, $run['code'], "Corpus version [{$version}] should have been refused");
        $this->assertStringContainsString('corpus-version', $run['output']);
    }

    public static function malformedCorpusVersions(): array
    {
        return [
            'empty'            => [''],
            'free text'        => ['florida'],
            'old test tag'     => ['nad-test-fl'],
            'no state'         => ['nad-2026-06'],
            'no period'        => ['nad-fl'],
            'month 13'         => ['nad-2026-13-fl'],
            'month 00'         => ['nad-2026-00-fl'],
            'implausible year' => ['nad-1026-06-fl'],
            'two-digit year'   => ['nad-26-06-fl'],
            'uppercase'        => ['NAD-2026-06-FL'],
        ];
    }

    public function test_a_corpus_version_naming_another_state_is_refused(): void
    {
        // The tag is what a future import is scoped by. A Florida scan stamped
        // "…-ga" would produce a report whose own label contradicts it.
        $run = $this->dry(['--corpus-version' => 'nad-2026-06-ga']);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('names state [ga]', $run['output']);
    }

    public function test_a_corpus_version_naming_another_source_is_refused(): void
    {
        $run = $this->dry(['--corpus-version' => 'oa-2026-06-fl']);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('names source [oa]', $run['output']);
    }

    public function test_a_valid_corpus_version_is_accepted_and_echoed(): void
    {
        $run = $this->dry(['--corpus-version' => 'nad-2026-06-fl']);

        $this->assertSame(0, $run['code']);
        $this->assertStringContainsString('nad-2026-06-fl', $run['output']);
    }

    // ── schema: fail closed ─────────────────────────────────────────────────

    public function test_a_missing_file_is_refused(): void
    {
        $run = $this->dry([], 'tests/fixtures/address-corpus/does-not-exist.csv');

        $this->assertSame(1, $run['code']);
    }

    public function test_a_renamed_schema_stops_the_run_and_names_the_difference(): void
    {
        // The instruction that matters if a future NAD release moves: do not
        // guess. A header without StNam_Full would otherwise import a corpus of
        // rows with empty streets and report success.
        $run = $this->dry([], self::RENAMED);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('does not match the schema this importer maps', $run['output']);
        $this->assertStringContainsString('StNam_Full', $run['output']);
        $this->assertStringContainsString('AddressGUID', $run['output'], 'The actual header must be shown');
    }

    public function test_a_source_with_no_address_number_column_fails_at_the_schema_level(): void
    {
        // Previously this scanned the whole file and reported 100%
        // missing_address_number, which reads as "this corpus has no house
        // numbers" rather than "we are looking for the wrong column". One is a
        // reason to stop; the other is a reason to change the mapping.
        $run = $this->dry([], self::NO_NUMBER);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('does not match the schema this importer maps', $run['output']);
        $this->assertStringContainsString('address number', $run['output']);
        $this->assertStringContainsString('AddNo_Full', $run['output']);
        $this->assertStringContainsString('Add_Number', $run['output']);

        // And it must NOT have scanned: no per-row reject reporting at all.
        $this->assertStringNotContainsString('missing_address_number', $run['output']);
    }

    public function test_either_address_number_column_alone_satisfies_the_schema(): void
    {
        // The group requires one of the two, not both — NAD supplies the bare
        // integer and the complete form under different names.
        $schema = (new NadSourceReader($this->fixture(self::DUP_REF)))->assertSchema();

        $this->assertTrue($schema['ok']);
        $this->assertSame([], $schema['missing_required_groups']);
    }

    // ── collision measurement: never a zero it did not earn ─────────────────

    public function test_an_unmeasured_collision_run_reports_null_counts_not_zero(): void
    {
        // The bug this closes: shell_exec returning null became (int) null === 0,
        // and the report announced "exact measurement: 0 duplicates" for a file
        // it had not read. A zero is what an operator acts on; a blank is not.
        $data = $this->jsonFrom(['--collisions' => 'none']);

        $this->assertFalse($data['normalization']['measured']);
        $this->assertNull($data['normalization']['distinct']);
        $this->assertNull($data['normalization']['repeated_lines']);
        $this->assertNull($data['normalization']['repeated_disagreeing']);
        $this->assertNull($data['normalization']['duplicate_source_refs']);
        $this->assertNotNull($data['normalization']['reason']);
    }

    public function test_an_unmeasurable_spill_reports_not_measured_rather_than_zero(): void
    {
        // An unwritable spill directory is the reachable stand-in for a missing
        // toolchain: both must land on measured=false with a stated reason, and
        // neither may report counts.
        $path = $this->tempDir() . '/report.json';

        $run = $this->dry([
            '--collisions' => 'exact',
            '--spill-dir'  => '/nonexistent-spill-dir-' . getmypid(),
            '--json'       => $path,
        ]);

        $data = json_decode((string) file_get_contents($path), true);
        @unlink($path);

        $this->assertSame(0, $run['code'], 'An unmeasurable collision run is still a valid dry run');
        $this->assertFalse($data['normalization']['measured']);
        $this->assertNull($data['normalization']['distinct']);
        $this->assertStringContainsString('not measured', $run['output']);
    }

    public function test_exact_measurement_states_its_method(): void
    {
        $out = $this->exact()['output'];

        $this->assertStringContainsString('exact (disk-assisted', $out);
    }

    public function test_repeated_lines_are_split_into_agreeing_and_disagreeing(): void
    {
        // The distinction the AddressPoint rung actually turns on.
        //
        //   4200 Gulf Blvd  — two source rows (one with a unit), ONE point.
        //                     A condo. The rung resolves it.
        //   900 Duplicate Twin Ave/Avenue — two source rows, TWO points.
        //                     The rung returns unresolved for this address.
        //
        // A single "2 duplicates" headline would hide the second inside the first.
        $data = $this->jsonFrom(['--collisions' => 'exact']);
        $n    = $data['normalization'];

        $this->assertTrue($n['measured']);
        $this->assertSame(13, $data['scan']['accepted']);
        $this->assertSame(11, $n['distinct']);
        $this->assertSame(9, $n['unique_lines']);
        $this->assertSame(2, $n['repeated_lines']);
        $this->assertSame(1, $n['repeated_agreeing'], 'the condo pair agrees on its point');
        $this->assertSame(1, $n['repeated_disagreeing'], 'the twin pair disagrees on its point');
        $this->assertSame(4, $n['rows_in_repeats']);
    }

    public function test_coordinate_disagreement_is_surfaced_in_the_human_report(): void
    {
        $out = $this->exact()['output'];

        $this->assertStringContainsString('DISAGREEING', $out);
        $this->assertStringContainsString('same coordinate', $out);
        $this->assertStringContainsString('resolve ambiguously', $out);
    }

    public function test_duplicate_source_refs_are_detected(): void
    {
        // UNIQUE (corpus_version, source, source_ref) is what makes a re-import
        // idempotent. A file that cannot satisfy it must be known before a load,
        // not discovered by a constraint violation during one.
        $data = $this->jsonFrom(['--collisions' => 'exact'], self::DUP_REF);
        $n    = $data['normalization'];

        $this->assertSame(3, $data['scan']['accepted']);
        $this->assertSame(2, $n['distinct_source_refs']);
        $this->assertSame(1, $n['duplicate_source_refs']);
    }

    public function test_a_clean_file_reports_no_duplicate_source_refs(): void
    {
        $data = $this->jsonFrom(['--collisions' => 'exact']);

        $this->assertSame(13, $data['normalization']['distinct_source_refs']);
        $this->assertSame(0, $data['normalization']['duplicate_source_refs']);
    }

    public function test_the_duplicate_source_ref_warning_names_the_constraint(): void
    {
        $out = $this->exact([], self::DUP_REF)['output'];

        $this->assertStringContainsString('source_ref', $out);
        $this->assertStringContainsString('UNIQUE', $out);
    }

    // ── the spill file ──────────────────────────────────────────────────────

    public function test_the_spill_file_is_removed_after_a_successful_measurement(): void
    {
        // It holds complete residential address lines. Leaving one behind is the
        // difference between a diagnostic and a data leak.
        $dir = $this->tempDir();

        $run = $this->exact(['--spill-dir' => $dir]);

        $this->assertSame(0, $run['code']);
        $this->assertSame([], $this->spillFilesIn($dir), 'A spill file survived a successful run');
    }

    public function test_the_spill_file_is_removed_when_the_run_fails_afterwards(): void
    {
        // The JSON write fails after the scan, so the command returns FAILURE —
        // and the spill must still be gone, because cleanup is in `finally`
        // rather than on the success path.
        $dir = $this->tempDir();

        $run = $this->exact([
            '--spill-dir' => $dir,
            '--json'      => '/nonexistent-json-dir-' . getmypid() . '/report.json',
        ]);

        $this->assertSame(1, $run['code']);
        $this->assertSame([], $this->spillFilesIn($dir), 'A spill file survived a failed run');
    }

    public function test_the_spill_file_is_not_written_into_the_project_by_default(): void
    {
        $before = glob(base_path('nad-normalized-*')) ?: [];

        $this->exact();

        $this->assertSame($before, glob(base_path('nad-normalized-*')) ?: []);
    }

    // ── JSON output ─────────────────────────────────────────────────────────

    public function test_it_writes_a_json_report_when_asked(): void
    {
        $path = $this->tempDir() . '/report.json';

        $run = $this->dry(['--json' => $path]);

        $this->assertSame(0, $run['code']);
        $this->assertFileExists($path);

        $data = json_decode((string) file_get_contents($path), true);

        $this->assertSame('nad', $data['source']['source']);
        $this->assertSame('FL', $data['source']['state']);
        $this->assertSame('12', $data['source']['state_fips']);
        $this->assertSame('nad-2026-06-fl', $data['source']['corpus_version']);
        $this->assertSame(25, $data['scan']['rows_scanned']);
        $this->assertIsArray($data['placement']['distribution']);

        @unlink($path);
    }

    public function test_a_failed_json_write_does_not_claim_success(): void
    {
        // It used to print "JSON report written" without checking the return
        // value, so an unwritable path produced a success message and no file.
        $run = $this->dry(['--json' => '/nonexistent-json-dir-' . getmypid() . '/report.json']);

        $this->assertSame(1, $run['code']);
        $this->assertStringNotContainsString('JSON report written', $run['output']);
        $this->assertStringContainsString('Cannot write the JSON report', $run['output']);
    }

    public function test_an_existing_json_report_is_overwritten(): void
    {
        // Documented rather than changed: re-running a dry run to refresh a
        // report is the normal case, so the overwrite is the contract.
        $path = $this->tempDir() . '/report.json';
        file_put_contents($path, '{"stale":true}');

        $run = $this->dry(['--json' => $path]);

        $this->assertSame(0, $run['code']);

        $data = json_decode((string) file_get_contents($path), true);
        $this->assertArrayNotHasKey('stale', $data);

        @unlink($path);
    }

    // ── partial scans ───────────────────────────────────────────────────────

    public function test_the_limit_option_stops_the_scan(): void
    {
        $out = $this->dry(['--limit' => '5'])['output'];

        $this->assertStringContainsString('Rows scanned      5', $out);
    }

    public function test_a_limited_run_is_marked_partial_in_the_human_report(): void
    {
        // The failure this prevents: --limit=1000 against a national file
        // ordered by state produces "Rows in FL 0, Accepted 0" and reads like a
        // finding about Florida rather than a truncated scan.
        $out = $this->dry(['--limit' => '5'])['output'];

        $this->assertStringContainsString('PARTIAL SCAN', $out);
        $this->assertStringContainsString('NOT A CORPUS VALIDATION', $out);
    }

    public function test_a_limited_run_is_marked_partial_in_the_json(): void
    {
        $data = $this->jsonFrom(['--limit' => '5']);

        $this->assertTrue($data['limited']);
        $this->assertSame(5, $data['limit']);
        $this->assertTrue($data['scan']['limited']);
        $this->assertFalse($data['scan']['complete']);
        $this->assertSame(5, $data['scan']['rows_scanned']);
    }

    public function test_a_complete_run_is_marked_complete(): void
    {
        $data = $this->jsonFrom();

        $this->assertFalse($data['limited']);
        $this->assertNull($data['limit']);
        $this->assertTrue($data['scan']['complete']);
    }

    // ── what it reports ─────────────────────────────────────────────────────

    public function test_it_counts_only_the_requested_jurisdiction(): void
    {
        $out = $this->dry()['output'];

        // The fixture holds 25 rows, 3 of them outside Florida (GA, LA).
        $this->assertStringContainsString('Rows scanned      25', $out);
        $this->assertStringContainsString('Rows in FL        23', $out);
    }

    public function test_it_reports_each_reject_reason_by_name(): void
    {
        $out = $this->dry()['output'];

        foreach ([
            'missing_uuid',
            'missing_address_number',
            'missing_street_name',
            'missing_latitude',
            'missing_longitude',
            'malformed_latitude',
            'malformed_longitude',
            'coordinate_out_of_range',
            'coordinate_outside_state_bounds',
        ] as $reason) {
            $this->assertStringContainsString($reason, $out, "Reject reason {$reason} must be reported");
        }
    }

    public function test_it_reports_the_placement_distribution_with_null_as_its_own_category(): void
    {
        $out = $this->dry()['output'];

        $this->assertStringContainsString('PLACEMENT', $out);
        $this->assertStringContainsString('(null)', $out);
        $this->assertStringContainsString('structure - rooftop', $out);
        $this->assertStringContainsString('site - approximate', $out);
    }

    public function test_an_unrecognised_placement_is_labelled_not_mapped(): void
    {
        $out = $this->dry()['output'];

        $this->assertStringContainsString('UNRECOGNISED', $out);
        $this->assertStringContainsString('UNMAPPED (decision open)', $out);
    }

    public function test_it_reports_locality_and_unit_provenance(): void
    {
        $out = $this->dry()['output'];

        $this->assertStringContainsString('LOCALITY', $out);
        $this->assertStringContainsString('Post_City', $out);
        $this->assertStringContainsString('Inc_Muni', $out);
        $this->assertStringContainsString('UNITS', $out);
        $this->assertStringContainsString('SubAddress', $out);
    }

    public function test_it_reports_coverage_dimensions(): void
    {
        $out = $this->dry()['output'];

        $this->assertStringContainsString('COVERAGE', $out);
        $this->assertStringContainsString('Counties', $out);
        $this->assertStringContainsString('Postal cities', $out);
    }

    // ── source dispatch: the same command, three sources ────────────────────

    /** @return array{code:int, output:string} */
    private function ng911(string $source, array $options = [], ?string $file = null): array
    {
        $file ??= "tests/fixtures/address-corpus/ng911-{$source}-sample.geojson";

        $code = Artisan::call('address:import-corpus', array_merge([
            'file'             => $this->fixture($file),
            '--source'         => $source,
            '--state-fips'     => '12',
            '--corpus-version' => "{$source}-2026-08-fl",
            '--dry-run'        => true,
            '--collisions'     => 'none',
        ], $options));

        return ['code' => $code, 'output' => Artisan::output()];
    }

    public function test_every_registered_source_is_dispatchable(): void
    {
        // The architectural claim, asserted rather than described: one command,
        // three sources, no county named anywhere in it.
        $this->assertSame(
            ['nad', 'pinellas', 'hillsborough'],
            \App\Services\Location\AddressCorpus\CorpusSourceRegistry::supported()
        );
    }

    public function test_a_pinellas_ng911_source_runs_end_to_end(): void
    {
        $run = $this->ng911('pinellas');

        $this->assertSame(0, $run['code']);
        $this->assertStringContainsString('Rows scanned      10', $run['output']);
        $this->assertStringContainsString('pinellas', $run['output']);
    }

    public function test_a_hillsborough_ng911_source_runs_end_to_end(): void
    {
        $run = $this->ng911('hillsborough');

        $this->assertSame(0, $run['code']);
        $this->assertStringContainsString('Rows scanned      6', $run['output']);
    }

    public function test_an_ng911_run_opens_no_connection_and_sends_nothing(): void
    {
        // The guarantee has to hold for every source, not just the one it was
        // written for.
        Http::fake();
        $queries = [];
        DB::listen(function ($q) use (&$queries) { $queries[] = $q->sql; });

        $run = $this->ng911('hillsborough');

        $this->assertSame(0, $run['code']);
        $this->assertSame([], $queries);
        Http::assertNothingSent();
    }

    public function test_ng911_rejects_are_reported_by_name(): void
    {
        $out = $this->ng911('pinellas')['output'];

        foreach ([
            'inactive_address_status',
            'non_address_feature',
            'missing_address_number',
            'missing_source_ref',
            'coordinate_outside_state_bounds',
        ] as $reason) {
            $this->assertStringContainsString($reason, $out, "Reject reason {$reason} must be reported");
        }
    }

    public function test_ng911_precision_never_reads_as_rooftop(): void
    {
        $path = $this->tempDir() . '/report.json';
        $this->ng911('hillsborough', ['--json' => $path]);

        $data = json_decode((string) file_get_contents($path), true);
        @unlink($path);

        $precisions = $data['precision'] ?? [];

        $this->assertArrayHasKey('parcel', $precisions);
        $this->assertArrayNotHasKey('rooftop', $precisions);
        $this->assertArrayNotHasKey('entrance', $precisions);
    }

    public function test_injected_jurisdiction_is_reported(): void
    {
        // Hillsborough publishes no state or county; the report must say that
        // those values were configured rather than read.
        $path = $this->tempDir() . '/report.json';
        $this->ng911('hillsborough', ['--json' => $path]);

        $data = json_decode((string) file_get_contents($path), true);
        @unlink($path);

        $this->assertGreaterThan(0, $data['scan']['injected_jurisdiction']);
    }

    public function test_a_projected_source_is_refused_at_the_schema_gate(): void
    {
        $run = $this->ng911('hillsborough', [], 'tests/fixtures/address-corpus/ng911-stateplane.geojson');

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('does not match the schema this importer maps', $run['output']);
    }

    public function test_a_non_point_source_is_refused(): void
    {
        $run = $this->ng911('hillsborough', [], 'tests/fixtures/address-corpus/ng911-polygon.geojson');

        $this->assertSame(1, $run['code']);
    }

    public function test_ng911_collisions_separate_units_from_disagreement(): void
    {
        // Pinellas fixture: 4200 Gulf Blvd units 501/502 share one point (a
        // condo, harmless), while the two "Disagree Twin" rows sit at different
        // points (the case that makes the rung return unresolved).
        $path = $this->tempDir() . '/report.json';
        $this->ng911('pinellas', ['--collisions' => 'exact', '--json' => $path]);

        $data = json_decode((string) file_get_contents($path), true);
        @unlink($path);

        $n = $data['normalization'];

        $this->assertTrue($n['measured']);
        $this->assertSame(1, $n['repeated_agreeing'], 'the condo pair agrees on its point');
        $this->assertSame(1, $n['repeated_disagreeing'], 'the twin pair disagrees on its point');
    }

    public function test_an_ng911_corpus_version_must_name_its_own_source(): void
    {
        $run = $this->ng911('pinellas', ['--corpus-version' => 'hillsborough-2026-08-fl']);

        $this->assertSame(1, $run['code']);
        $this->assertStringContainsString('names source [hillsborough]', $run['output']);
    }

    public function test_a_florida_county_source_cannot_be_scanned_for_another_state(): void
    {
        // FIPS 13 is Georgia. The map declares Florida, so no row qualifies —
        // a county file cannot smuggle rows into a jurisdiction it never covered.
        $run = $this->ng911('pinellas', [
            '--state-fips'     => '13',
            '--corpus-version' => 'pinellas-2026-08-ga',
        ]);

        $this->assertSame(0, $run['code']);
        $this->assertStringContainsString('Rows in GA        0', $run['output']);
        $this->assertStringContainsString('Accepted          0', $run['output']);
    }

    // ── the reader ──────────────────────────────────────────────────────────

    public function test_the_reader_streams_without_materialising_the_file(): void
    {
        $reader = new NadSourceReader($this->fixture());

        $seen = 0;

        foreach ($reader->rows() as $row) {
            $seen++;
            $this->assertArrayHasKey('UUID', $row);
            $this->assertArrayHasKey('StNam_Full', $row);
        }

        $this->assertSame(25, $seen);
    }

    public function test_the_reader_matches_headers_case_insensitively(): void
    {
        $schema = (new NadSourceReader($this->fixture()))->assertSchema();

        $this->assertTrue($schema['ok']);
        $this->assertSame([], $schema['missing_required']);
    }
}
