<?php

namespace App\Console\Commands;

use App\Services\Location\AddressCorpus\AddressCorpusDryRunReport;
use App\Services\Location\AddressCorpus\Contracts\AddressRowNormalizer;
use App\Services\Location\AddressCorpus\Contracts\AddressSourceReader;
use App\Services\Location\AddressCorpus\CorpusAddressRecord;
use App\Services\Location\AddressCorpus\CorpusSourceRegistry;
use App\Services\Location\AddressCorpus\StateFips;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

/**
 * Streams a NAD address distribution and reports what a Florida import would
 * hold — without touching a database.
 *
 * THE DRY RUN OPENS NO CONNECTION. AT ALL.
 * ----------------------------------------
 * Not "makes no writes" — makes no queries, and resolves no connection. That is
 * a stronger property and a much easier one to prove: a test counts queries on
 * every connection across a full run and asserts zero. "Non-mutating" enforced
 * by reviewing each statement would be a promise; this is a structural fact,
 * because there is no persistence code on the dry-run path to review.
 *
 * MEMORY IS BOUNDED, AND WHERE IT ISN'T, IT SAYS SO
 * -------------------------------------------------
 * The national file is ~98M rows. Rows stream one at a time through a pure
 * normalizer into {@see AddressCorpusDryRunReport}, which keeps only counters
 * and small capped sets.
 *
 * Duplicate-address statistics are the one thing that genuinely cannot be
 * computed that way: knowing how many normalized lines repeat means knowing all
 * of them. Rather than quietly sampling, or quietly holding 12 million strings,
 * the lines are spilled to a temp file and counted with an external sort — an
 * explicit disk-assisted exact measurement, labelled as such in the output. With
 * `--collisions=none` the spill is skipped and the report says "not measured"
 * rather than reporting a zero it did not earn.
 *
 * "NOT MEASURED" IS A RESULT; ZERO IS A CLAIM
 * -------------------------------------------
 * The external sort needs `shell_exec` and POSIX text utilities, and neither is
 * guaranteed: a hardened php.ini disables the first, and a minimal container may
 * lack the second. `shell_exec()` returns null in both cases, and `(int) null`
 * is `0` — so the original code reported "exact measurement: 0 duplicates" on a
 * corpus it had not read a byte of. A confident zero is worse than a blank,
 * because a zero is what an operator would act on. The toolchain is now probed
 * before anything claims to be exact, every count is parsed as a strict integer,
 * and any failure collapses the whole measurement to `measured => false` with a
 * stated reason.
 *
 * THE COLLISION THAT MATTERS IS DISAGREEMENT, NOT REPETITION
 * ----------------------------------------------------------
 * A repeated normalized line is normal: every unit in a building shares one, by
 * design, because the line is unit-free. What breaks the AddressPoint rung is
 * repeated lines whose rows sit at *different* points — that is the case
 * {@see AddressPointCoordinateAdapter} answers with `unresolved`, and it is
 * invisible in a single "duplicates" count that folds condos and conflicts
 * together. The measurement therefore splits repeats into agreeing and
 * disagreeing, comparing points at the same 6-decimal precision the rung itself
 * uses, so the number here predicts the number there.
 *
 * FLORIDA IS A FILTER
 * -------------------
 * `--state-fips` is required and carries no default. Nothing about this command
 * knows Florida; `12` is a value an operator supplies, and the same command
 * scans any jurisdiction — or, with a FIPS the source does not contain, reports
 * zero rows rather than pretending.
 */
class AddressImportCorpus extends Command
{
    /**
     * Sources this command can read, from {@see CorpusSourceRegistry}.
     *
     * `--source` exists so the source stops being implied by which class the
     * command happens to construct — it is the third member of the eventual
     * `UNIQUE (corpus_version, source, source_ref)` key, and a value that
     * important should be stated by the operator rather than inferred.
     *
     * The list lives in the registry rather than here because this command must
     * not learn what a jurisdiction is: adding a NENA county is a column map and
     * a registry entry, never an edit to a console command.
     *
     * @return list<string>
     */
    public static function supportedSources(): array
    {
        return CorpusSourceRegistry::supported();
    }

    /** `<source>-<YYYY>-<MM>-<state>`, e.g. `nad-2026-06-fl`. */
    private const CORPUS_VERSION_PATTERN = '/^([a-z0-9]+)-(\d{4})-(\d{2})-([a-z]{2})$/';

    protected $signature = 'address:import-corpus
        {file : Local source file — NAD text distribution (.zip/.gz/.csv/.txt) or NG9-1-1 GeoJSON}
        {--source= : Corpus source identifier — required (nad, pinellas, hillsborough)}
        {--state-fips= : FIPS code of the jurisdiction to scan (e.g. 12 for Florida) — required}
        {--corpus-version= : corpus_version tag, <source>-<YYYY>-<MM>-<state> (e.g. nad-2026-06-fl) — required}
        {--dry-run : Scan and report only. No database connection is opened.}
        {--execute : Persist the corpus. Refused — real import is not yet approved.}
        {--limit=0 : Stop after N source rows (0 = no limit). Produces a PARTIAL report.}
        {--collisions=exact : exact (disk-assisted spill+sort) or none}
        {--spill-dir= : Where the collision spill file is written (default: system temp)}
        {--json= : Also write the full report as JSON to this path (overwrites)}';

    protected $description = 'Stream an authoritative address corpus and report what an import would hold (dry run; opens no database connection)';

    public function handle(): int
    {
        $file         = (string) $this->argument('file');
        $stateFipsRaw = (string) $this->option('state-fips');

        if ($this->option('execute')) {
            $this->error('[address:import-corpus] --execute is not available.');
            $this->line('Persisting an address corpus has not been approved. Run with --dry-run and');
            $this->line('review the report — in particular the Placement distribution, which decides');
            $this->line('what precision the imported rows could honestly carry.');

            return self::FAILURE;
        }

        if (! $this->option('dry-run')) {
            $this->error('[address:import-corpus] --dry-run is required.');
            $this->line('This command has no other mode today. The flag is explicit so that a future');
            $this->line('write path cannot become the default by omission.');

            return self::FAILURE;
        }

        $source = strtolower(trim((string) $this->option('source')));

        if ($source === '') {
            $this->error('[address:import-corpus] --source is required.');
            $this->line('Supported: ' . implode(', ', self::supportedSources()) . '. The source is one third of the');
            $this->line('dedupe key a future import would use, so it is stated rather than inferred.');

            return self::FAILURE;
        }

        if (! CorpusSourceRegistry::supports($source)) {
            $this->error("[address:import-corpus] --source [{$source}] is not supported.");
            $this->line('Supported: ' . implode(', ', self::supportedSources()) . '. Reading a source this command');
            $this->line('does not understand would produce a report about a schema it never parsed.');

            return self::FAILURE;
        }

        $stateFips = StateFips::normalizeFips($stateFipsRaw);
        $usps      = StateFips::toUsps($stateFips);

        if ($usps === null) {
            $this->error("[address:import-corpus] --state-fips is required and must be a known FIPS code; got [{$stateFipsRaw}].");

            return self::FAILURE;
        }

        $corpusVersion = trim((string) $this->option('corpus-version'));
        $versionError  = $this->corpusVersionError($corpusVersion, $source, $usps);

        if ($versionError !== null) {
            $this->error('[address:import-corpus] ' . $versionError);
            $this->line('The version tag scopes a future import and is what the AddressPoint rung pins');
            $this->line('to. It must name the source, the release period and the jurisdiction, because a');
            $this->line('tag that says none of those cannot distinguish two imports later.');
            $this->line('Expected form: <source>-<YYYY>-<MM>-<state>, e.g. nad-2026-06-fl');

            return self::FAILURE;
        }

        try {
            // The command does not know which reader or normalizer a source
            // needs, and must not learn: that is what would turn every new
            // jurisdiction into an edit here rather than a column map.
            ['reader' => $reader, 'normalizer' => $normalizer, 'label' => $label]
                = CorpusSourceRegistry::open($source, $file);
        } catch (RuntimeException | InvalidArgumentException $e) {
            $this->error('[address:import-corpus] ' . $e->getMessage());

            return self::FAILURE;
        }

        $schema = $reader->assertSchema();

        if (! $schema['ok']) {
            $this->error('[address:import-corpus] The source does not match the schema this importer maps.');

            if ($schema['missing_required'] !== []) {
                $this->line('  Missing: ' . implode(', ', $schema['missing_required']));
            }

            foreach ($schema['missing_required_groups'] as $group) {
                $this->line('  Missing: ' . $group);
            }

            $this->line('  Found:   ' . implode(', ', $schema['header']));
            $this->newLine();
            $this->line('This release does not match the schema the importer maps. Stopping rather than');
            $this->line('producing a corpus of rows with empty streets.');

            return self::FAILURE;
        }

        return $this->dryRun(
            $reader,
            $normalizer,
            $stateFips,
            $usps,
            $corpusVersion,
            $source,
            $label,
            $file,
            $schema
        );
    }

    /**
     * Why a corpus version is unacceptable, or null when it is fine.
     *
     * Syntactic and cross-field only — this deliberately does not ask a database
     * whether the version already exists. The dry run opens no connection, and
     * buying reuse-detection at the cost of that guarantee would be a bad trade
     * for a check the loader has to repeat anyway.
     */
    private function corpusVersionError(string $version, string $source, string $usps): ?string
    {
        if ($version === '') {
            return '--corpus-version is required.';
        }

        if (! preg_match(self::CORPUS_VERSION_PATTERN, $version, $m)) {
            return "--corpus-version [{$version}] is not in the required form <source>-<YYYY>-<MM>-<state>.";
        }

        [, $versionSource, $year, $month, $state] = $m;

        if ($versionSource !== $source) {
            return "--corpus-version [{$version}] names source [{$versionSource}] but --source is [{$source}].";
        }

        if ((int) $year < 2000 || (int) $year > 2099) {
            return "--corpus-version [{$version}] has an implausible year [{$year}].";
        }

        if ((int) $month < 1 || (int) $month > 12) {
            return "--corpus-version [{$version}] has an invalid month [{$month}].";
        }

        if ($state !== strtolower($usps)) {
            return "--corpus-version [{$version}] names state [{$state}] but --state-fips resolves to ["
                . strtolower($usps) . '].';
        }

        return null;
    }

    private function dryRun(
        AddressSourceReader $reader,
        AddressRowNormalizer $normalizer,
        string $stateFips,
        string $usps,
        string $corpusVersion,
        string $source,
        string $label,
        string $file,
        array $schema,
    ): int {
        // Which raw columns to count for presence is the one source-shaped input
        // the report still needs — NAD's names against a NENA file would report
        // every field missing on every row.
        $report  = new AddressCorpusDryRunReport($this->trackedFieldsFor($source));
        $limit   = (int) $this->option('limit');
        $limited = false;

        $spill = $this->openSpill();
        $start = microtime(true);

        // Everything from here is wrapped: the spill file holds complete
        // residential address lines, so its removal must not depend on the scan
        // finishing, on the measurement succeeding, or on nothing throwing.
        try {
            foreach ($reader->rows() as $row) {
                $report->countRowScanned();

                if ($limit > 0 && $report->rowsScanned > $limit) {
                    $report->rowsScanned--;
                    $limited = true;
                    break;
                }

                if (! $normalizer->matchesState($row, $stateFips)) {
                    continue;
                }

                $report->countInState();
                $report->countFieldPresence($row);

                ['record' => $record, 'reject' => $reject] = $normalizer->normalize($row, $stateFips);

                if ($record === null) {
                    $report->countReject((string) $reject);

                    continue;
                }

                $report->countAccepted($record);

                if ($spill !== null) {
                    fwrite($spill, $this->spillLine($record));
                }

                if ($report->rowsScanned % 250000 === 0) {
                    $this->line(sprintf(
                        '  … %s scanned, %s in %s, %s accepted, %s MB peak',
                        number_format($report->rowsScanned),
                        number_format($report->rowsInState),
                        $usps,
                        number_format($report->accepted),
                        number_format(memory_get_peak_usage(true) / 1048576, 1)
                    ));
                }
            }

            $elapsed = microtime(true) - $start;

            // Close before sorting — the external tools read the path, not the
            // handle, and an unflushed tail would silently shorten the corpus.
            if ($spill !== null) {
                fclose($spill);
                $spill = null;
            }

            $collisions = $this->measureCollisions();
        } finally {
            if (is_resource($spill)) {
                fclose($spill);
            }

            $this->removeSpill();
        }

        $this->render($report, $collisions, $elapsed, $file, $usps, $stateFips, $corpusVersion, $source, $label, $schema, $limited, $limit);

        if (($path = trim((string) $this->option('json'))) !== '') {
            return $this->writeJson(
                $path,
                $this->reportArray($report, $collisions, $elapsed, $file, $usps, $stateFips, $corpusVersion, $source, $label, $schema, $limited, $limit)
            );
        }

        return self::SUCCESS;
    }

    /**
     * The raw column names whose emptiness this source's report should count.
     *
     * Derived from the jurisdiction's column map for a NENA source, so adding a
     * county does not add a branch here either.
     *
     * @return list<string>
     */
    private function trackedFieldsFor(string $source): array
    {
        $map = CorpusSourceRegistry::columnMap($source);

        if ($map === null) {
            return AddressCorpusDryRunReport::TRACKED_FIELDS;
        }

        return array_values(array_unique(array_filter([
            $map->sourceRefColumn,
            $map->numberColumn,
            $map->streetColumn,
            $map->unitIdColumn,
            $map->cityColumn,
            $map->zipColumn,
            $map->stateColumn,
            $map->countyColumn,
            $map->placementColumn,
            $map->statusColumn,
        ])));
    }

    /**
     * One spill row: normalized line, point, source ref — tab separated.
     *
     * The point is rendered at the same 6 decimals
     * {@see AddressPointCoordinateAdapter::distinctValidPoints()} uses to decide
     * whether matched rows agree. Measuring at a different precision here would
     * produce a number that looks like the rung's behaviour and is not.
     *
     * Tabs and newlines are stripped from the source ref rather than escaped:
     * the field is an opaque id, the columns are positional, and a stray control
     * character in one row must not shift every column after it.
     */
    private function spillLine(CorpusAddressRecord $record): string
    {
        $point = number_format($record->latitude, 6, '.', '')
            . ',' . number_format($record->longitude, 6, '.', '');

        $ref = str_replace(["\t", "\n", "\r"], ' ', $record->sourceRef);

        return $record->normalized() . "\t" . $point . "\t" . $ref . "\n";
    }

    /**
     * Writes the JSON report, or fails loudly.
     *
     * Overwrites an existing file at the path — that is the pre-existing
     * contract and it is kept deliberately, because re-running a dry run to
     * refresh a report is the normal case. What changed is that the command no
     * longer announces a write it did not verify.
     */
    private function writeJson(string $path, array $payload): int
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $this->error('[address:import-corpus] Could not encode the report as JSON: ' . json_last_error_msg());

            return self::FAILURE;
        }

        $dir = dirname($path);

        if (! is_dir($dir) || ! is_writable($dir)) {
            $this->error("[address:import-corpus] Cannot write the JSON report: [{$dir}] is not a writable directory.");

            return self::FAILURE;
        }

        $written = @file_put_contents($path, $json);

        if ($written === false || $written < strlen($json)) {
            $this->error("[address:import-corpus] Failed to write the JSON report to [{$path}].");
            $this->line('The report above is complete; only the file write failed. Nothing was persisted');
            $this->line('anywhere else, so re-running is safe.');

            return self::FAILURE;
        }

        $this->line("JSON report written: {$path} ({$this->humanBytes($written)})");

        return self::SUCCESS;
    }

    // ── collision measurement ───────────────────────────────────────────────

    private ?string $spillPath = null;

    /** True once a shutdown hook for this run's spill file has been installed. */
    private bool $cleanupRegistered = false;

    /**
     * The shape returned when nothing could be measured.
     *
     * Every count is null rather than 0. A zero here would be indistinguishable
     * from "measured, and there were none" — which is exactly the confusion this
     * whole path was rewritten to remove.
     */
    private function unmeasured(string $reason): array
    {
        return [
            'measured'                 => false,
            'method'                   => 'not measured',
            'reason'                   => $reason,
            'distinct'                 => null,
            'unique_lines'             => null,
            'repeated_lines'           => null,
            'repeated_agreeing'        => null,
            'repeated_disagreeing'     => null,
            'rows_in_repeats'          => null,
            'distinct_source_refs'     => null,
            'duplicate_source_refs'    => null,
        ];
    }

    /** @return resource|null */
    private function openSpill()
    {
        if ((string) $this->option('collisions') !== 'exact') {
            return null;
        }

        $dir = trim((string) $this->option('spill-dir')) ?: sys_get_temp_dir();

        if (! is_dir($dir) || ! is_writable($dir)) {
            $this->warn("Spill directory [{$dir}] is not writable — collision statistics will not be measured.");

            return null;
        }

        if (str_starts_with(realpath($dir) ?: $dir, base_path())) {
            // Not refused — an operator may have a good reason — but the file
            // holds real address lines and the project directory is the one
            // place they could end up committed.
            $this->warn("Spill directory [{$dir}] is inside the project. The spill file holds full");
            $this->warn('address lines; it is removed at the end of the run, but prefer a temp directory.');
        }

        // Random suffix as well as the pid: a pid recycles, and 'x' below turns
        // a collision into a failure rather than a silent overwrite of whatever
        // happened to be there.
        $this->spillPath = rtrim($dir, '/') . '/nad-normalized-' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.tsv';

        $handle = @fopen($this->spillPath, 'x');

        if ($handle === false) {
            $this->warn('Could not open a spill file — collision statistics will not be measured.');
            $this->spillPath = null;

            return null;
        }

        // Before a single address is written. The default umask would leave this
        // world-readable in a shared temp directory.
        @chmod($this->spillPath, 0600);

        $this->registerCleanup();

        return $handle;
    }

    /**
     * Best-effort removal on paths `finally` cannot reach.
     *
     * `finally` covers a normal return and an exception. It does not cover a
     * fatal error or a Ctrl-C, and this file can be hundreds of megabytes of
     * addresses, so both get a hook where the runtime offers one. `pcntl` is
     * absent in many PHP builds; its absence is not a failure, just one fewer
     * net.
     */
    private function registerCleanup(): void
    {
        if ($this->cleanupRegistered) {
            return;
        }

        $this->cleanupRegistered = true;

        register_shutdown_function(function (): void {
            $this->removeSpill();
        });

        if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);

            foreach ([SIGINT, SIGTERM] as $signal) {
                pcntl_signal($signal, function () use ($signal): void {
                    $this->removeSpill();
                    exit(128 + $signal);
                });
            }
        }
    }

    private function removeSpill(): void
    {
        if ($this->spillPath !== null && is_file($this->spillPath)) {
            @unlink($this->spillPath);
        }

        $this->spillPath = null;
    }

    /**
     * Exact collision counts via an external sort.
     *
     * `sort` is an external merge sort: bounded memory, spills to disk itself.
     * That is precisely the algorithm needed and precisely what re-implementing
     * it in PHP would get wrong.
     *
     * Three questions, and they are different questions:
     *
     *   repeated_agreeing     one address, several source rows, one point.
     *                         A condo. Harmless — the rung returns the point.
     *   repeated_disagreeing  one address, several source rows, different
     *                         points. The rung returns UNRESOLVED for every one
     *                         of these, so this is the number that decides
     *                         whether a corpus is worth importing.
     *   duplicate_source_refs the same upstream id twice. Nothing to do with
     *                         addresses — it is whether the file can satisfy
     *                         UNIQUE (corpus_version, source, source_ref) at all.
     *
     * @return array<string, mixed>
     */
    private function measureCollisions(): array
    {
        if ((string) $this->option('collisions') !== 'exact') {
            return $this->unmeasured('--collisions=none');
        }

        if ($this->spillPath === null || ! is_file($this->spillPath)) {
            return $this->unmeasured('no spill file was written');
        }

        if (! $this->textToolchainAvailable()) {
            return $this->unmeasured('shell_exec or the POSIX text utilities (cut/sort/uniq/wc) are unavailable');
        }

        $f = escapeshellarg($this->spillPath);

        // Column 1 is the normalized line, column 2 the point, column 3 the
        // source ref. `sort -u` on columns 1+2 collapses identical (address,
        // point) pairs, so an address left with more than one row there is an
        // address whose sources disagree about where it is.
        $counts = [
            'distinct'              => "cut -f1 {$f} | sort -u | wc -l",
            'repeated_lines'        => "cut -f1 {$f} | sort | uniq -d | wc -l",
            'repeated_disagreeing'  => "cut -f1,2 {$f} | sort -u | cut -f1 | sort | uniq -d | wc -l",
            'rows_in_repeats'       => "cut -f1 {$f} | sort | uniq -c | awk '$1>1 {s+=$1} END {print s+0}'",
            'distinct_source_refs'  => "cut -f3 {$f} | sort -u | wc -l",
            'duplicate_source_refs' => "cut -f3 {$f} | sort | uniq -d | wc -l",
        ];

        $measured = [];

        foreach ($counts as $key => $command) {
            $value = $this->countFrom($command);

            if ($value === null) {
                // One failed pipeline invalidates the set. Reporting the four
                // that worked alongside two blanks would invite reading the
                // blanks as zeros.
                return $this->unmeasured("the '{$key}' measurement did not return a count");
            }

            $measured[$key] = $value;
        }

        return [
            'measured'              => true,
            'method'                => 'exact (disk-assisted spill + external sort)',
            'reason'                => null,
            'distinct'              => $measured['distinct'],
            'unique_lines'          => max(0, $measured['distinct'] - $measured['repeated_lines']),
            'repeated_lines'        => $measured['repeated_lines'],
            'repeated_agreeing'     => max(0, $measured['repeated_lines'] - $measured['repeated_disagreeing']),
            'repeated_disagreeing'  => $measured['repeated_disagreeing'],
            'rows_in_repeats'       => $measured['rows_in_repeats'],
            'distinct_source_refs'  => $measured['distinct_source_refs'],
            'duplicate_source_refs' => $measured['duplicate_source_refs'],
        ];
    }

    /**
     * Whether the external toolchain can actually run.
     *
     * Probed, not assumed. `shell_exec` may be listed in `disable_functions`,
     * in which case PHP emits a warning and returns null; a minimal container
     * may simply not ship `cut`. The probe exercises the whole pipeline this
     * class depends on — cut, sort, uniq and wc together — against input with a
     * known answer, so a partial toolchain fails here rather than halfway
     * through a measurement.
     */
    private function textToolchainAvailable(): bool
    {
        if (! function_exists('shell_exec')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        if (in_array('shell_exec', $disabled, true)) {
            return false;
        }

        // Two rows share column 1, one does not: exactly one duplicated key.
        $probe = @shell_exec("printf 'b\\ta\\nb\\tz\\nc\\tz\\n' | cut -f1 | sort | uniq -d | wc -l 2>/dev/null");

        return $probe !== null && trim($probe) === '1';
    }

    /** A single integer from a shell pipeline, or null when it did not produce one. */
    private function countFrom(string $command): ?int
    {
        $output = @shell_exec($command . ' 2>/dev/null');

        if ($output === null) {
            return null;
        }

        $trimmed = trim($output);

        // Strict: anything that is not purely digits is a failure, not a zero.
        return preg_match('/^\d+$/', $trimmed) === 1 ? (int) $trimmed : null;
    }

    // ── output ──────────────────────────────────────────────────────────────

    private function render(
        AddressCorpusDryRunReport $r,
        array $collisions,
        float $elapsed,
        string $file,
        string $usps,
        string $stateFips,
        string $corpusVersion,
        string $source,
        string $label,
        array $schema,
        bool $limited,
        int $limit,
    ): void {
        $this->newLine();
        $this->info("NAD corpus dry run — {$usps} (FIPS {$stateFips})");
        $this->line('Nothing was written. No database connection was opened.');

        if ($limited) {
            $this->newLine();
            $this->warn('*** PARTIAL SCAN — THIS IS NOT A CORPUS VALIDATION ***');
            $this->warn("--limit={$limit} stopped the scan early. Every count below describes only the");
            $this->warn('first ' . number_format($limit) . ' rows of the file, which may contain few or no ' . $usps . ' rows.');
        }

        $this->newLine();

        $this->line('SOURCE');
        $this->line('  Source          ' . $source);
        $this->line('  File            ' . $file);
        $this->line('  Size            ' . $this->humanBytes(is_file($file) ? (int) filesize($file) : 0));
        $this->line('  Corpus version  ' . $corpusVersion);
        $this->line('  Scan            ' . ($limited ? 'PARTIAL (--limit=' . number_format($limit) . ')' : 'complete'));

        if ($schema['missing_optional'] !== []) {
            $this->line('  Absent fields   ' . implode(', ', $schema['missing_optional']));
        }

        $this->newLine();
        $this->line('SCAN');
        $this->line('  Rows scanned      ' . number_format($r->rowsScanned));
        $this->line("  Rows in {$usps}        " . number_format($r->rowsInState));
        $this->line('  Accepted          ' . number_format($r->accepted) . '  (' . $r->pctOfInState($r->accepted) . '% of in-state)');
        $this->line('  Rejected          ' . number_format($r->rejected) . '  (' . $r->pctOfInState($r->rejected) . '%)');

        if ($r->rejectReasons !== []) {
            arsort($r->rejectReasons);

            foreach ($r->rejectReasons as $reason => $n) {
                $this->line(sprintf('    %-34s %10s  %5s%%', $reason, number_format($n), $r->pctOfInState($n)));
            }
        }

        $this->newLine();
        $this->line('COVERAGE');
        $this->line('  Counties       ' . $r->distinctCount('county') . ($r->wasTruncated('county') ? ' (truncated)' : ''));
        $this->line('  ZIPs           ' . $r->distinctCount('zip') . ($r->wasTruncated('zip') ? ' (truncated)' : ''));
        $this->line('  Postal cities  ' . $r->distinctCount('city') . ($r->wasTruncated('city') ? ' (truncated)' : ''));

        $this->newLine();
        $this->line('MISSING FIELDS (of in-state rows)');

        foreach (AddressCorpusDryRunReport::TRACKED_FIELDS as $field) {
            $n = $r->missingFields[$field] ?? 0;
            $this->line(sprintf('  %-12s %10s missing  %5s%%', $field, number_format($n), $r->pctOfInState($n)));
        }

        $this->newLine();
        $this->line('PLACEMENT (of accepted rows)');
        $this->line('  null / empty   ' . number_format($r->placementNull) . '  (' . $r->pctOfAccepted($r->placementNull) . '%)');
        $this->line('  recognised     ' . number_format($r->placementRecognised) . '  (' . $r->pctOfAccepted($r->placementRecognised) . '%)');
        $this->line('  unrecognised   ' . number_format($r->placementUnrecognised) . '  (' . $r->pctOfAccepted($r->placementUnrecognised) . '%)');
        $this->newLine();

        foreach ($r->distinctValues('placement') as $value => $n) {
            $resolution = $r->placementResolution($value === '(null)' ? '' : $value);

            $proposed = $value === '(null)'
                ? 'UNMAPPED (decision open)'
                : ($resolution['precision'] ?? 'UNRECOGNISED');

            $this->line(sprintf('    %-30s %10s  %5s%%   → %s', $value, number_format($n), $r->pctOfAccepted($n), $proposed));
        }

        $this->newLine();
        $this->line('PRECISION (of accepted rows)');

        foreach ($r->distinctValues('precision') as $value => $n) {
            $this->line(sprintf('  %-16s %10s  %5s%%', $value, number_format($n), $r->pctOfAccepted($n)));
        }

        if ($r->injectedJurisdiction > 0) {
            $this->line('  ' . number_format($r->injectedJurisdiction)
                . ' row(s) took state/county from source configuration, not from the data.');
        }

        $this->newLine();
        $this->line('UNITS (of accepted rows)');
        $this->line('  SubAddress     ' . number_format($r->unitSubAddress) . '  (' . $r->pctOfAccepted($r->unitSubAddress) . '%)');
        $this->line('  Unit fallback  ' . number_format($r->unitFallback) . '  (' . $r->pctOfAccepted($r->unitFallback) . '%)');
        $this->line('  No unit        ' . number_format($r->unitNone) . '  (' . $r->pctOfAccepted($r->unitNone) . '%)');

        $this->newLine();
        $this->line('LOCALITY (of accepted rows)');
        $this->line('  Post_City      ' . number_format($r->localityPostCity) . '  (' . $r->pctOfAccepted($r->localityPostCity) . '%)');
        $this->line('  Inc_Muni       ' . number_format($r->localityIncMuni) . '  (' . $r->pctOfAccepted($r->localityIncMuni) . '%)');
        $this->line('  Neither        ' . number_format($r->localityNone) . '  (' . $r->pctOfAccepted($r->localityNone) . '%)');

        $this->newLine();
        $this->line('NORMALIZATION / COLLISIONS');
        $this->line('  Normalized OK        ' . number_format($r->accepted));
        $this->line('  Method               ' . $collisions['method']);

        if (! $collisions['measured']) {
            // Named, not blank. "not measured" without a cause reads as a bug;
            // with a cause it reads as a decision or an environment.
            $this->line('  Not measured because ' . $collisions['reason']);
        } else {
            $this->line('  Distinct lines       ' . number_format($collisions['distinct']));
            $this->line('    occurring once     ' . number_format($collisions['unique_lines']));
            $this->line('    repeated           ' . number_format($collisions['repeated_lines']));
            $this->line('      same coordinate  ' . number_format($collisions['repeated_agreeing'])
                . '   (units of one building — the rung still resolves these)');
            $this->line('      DISAGREEING      ' . number_format($collisions['repeated_disagreeing'])
                . '   (the rung returns unresolved for these)');
            $this->line('  Rows in repeats      ' . number_format($collisions['rows_in_repeats'])
                . '  (' . $r->pctOfAccepted($collisions['rows_in_repeats']) . '%)');

            if ($collisions['repeated_disagreeing'] > 0) {
                $this->warn('  ' . number_format($collisions['repeated_disagreeing'])
                    . ' address(es) would resolve ambiguously and return no coordinate.');
            }

            $this->newLine();
            $this->line('SOURCE REFERENCES (dedupe key feasibility)');
            $this->line('  Distinct source_ref  ' . number_format($collisions['distinct_source_refs']));
            $this->line('  Duplicated           ' . number_format($collisions['duplicate_source_refs']));

            if ($collisions['duplicate_source_refs'] > 0) {
                $this->warn('  UNIQUE (corpus_version, source, source_ref) could not be satisfied by this file');
                $this->warn('  as-is — ' . number_format($collisions['duplicate_source_refs'])
                    . ' source_ref value(s) appear more than once.');
            }
        }

        $this->newLine();
        $this->line('RUN');
        $this->line('  Duration       ' . number_format($elapsed, 1) . 's');
        $this->line('  Peak memory    ' . $this->humanBytes(memory_get_peak_usage(true)));

        if ($limited) {
            $this->newLine();
            $this->warn('*** PARTIAL SCAN — report describes ' . number_format($r->rowsScanned)
                . ' of an unknown total ***');
        }

        $this->newLine();
    }

    private function reportArray(
        AddressCorpusDryRunReport $r,
        array $collisions,
        float $elapsed,
        string $file,
        string $usps,
        string $stateFips,
        string $corpusVersion,
        string $source,
        string $label,
        array $schema,
        bool $limited,
        int $limit,
    ): array {
        $placement = [];

        foreach ($r->distinctValues('placement') as $value => $n) {
            $placement[] = [
                'raw'        => $value,
                'count'      => $n,
                'pct'        => $r->pctOfAccepted($n),
                'recognised' => $value !== '(null)' && $r->placementResolution($value)['recognised'],
                'proposed'   => $value === '(null)' ? null : $r->placementResolution($value)['precision'],
            ];
        }

        return [
            // Deliberately first, and deliberately duplicated into `scan`: a
            // consumer that reads only one section must still be told that the
            // numbers it is reading may describe a fraction of the file.
            'limited' => $limited,
            'limit'   => $limit > 0 ? $limit : null,

            'source' => [
                'source'         => $source,
                'label'          => $label,
                'file'           => $file,
                'bytes'          => is_file($file) ? (int) filesize($file) : 0,
                'state'          => $usps,
                'state_fips'     => $stateFips,
                'corpus_version' => $corpusVersion,
                'header'         => $schema['header'],
                'missing_optional_fields' => $schema['missing_optional'],
            ],
            'scan' => [
                'limited'        => $limited,
                'limit'          => $limit > 0 ? $limit : null,
                'complete'       => ! $limited,
                'rows_scanned'   => $r->rowsScanned,
                'rows_in_state'  => $r->rowsInState,
                'accepted'       => $r->accepted,
                'rejected'       => $r->rejected,
                'reject_reasons' => $r->rejectReasons,
                // Rows whose state/county came from source configuration rather
                // than from the data. A single-county NENA feed publishes
                // neither; supplying them is fine, not saying so would not be.
                'injected_jurisdiction' => $r->injectedJurisdiction,
            ],

            // What precision the accepted rows would carry. The number that
            // decides whether Location DNA may measure from this corpus at all,
            // and the one a reviewer should read before approving a load.
            'precision' => $r->distinctValues('precision'),
            'coverage' => [
                'counties'      => $r->distinctValues('county'),
                'county_count'  => $r->distinctCount('county'),
                'zip_count'     => $r->distinctCount('zip'),
                'city_count'    => $r->distinctCount('city'),
            ],
            'missing_fields' => $r->missingFields,
            'placement'      => [
                'null'         => $r->placementNull,
                'recognised'   => $r->placementRecognised,
                'unrecognised' => $r->placementUnrecognised,
                'distribution' => $placement,
            ],
            'units' => [
                'subaddress' => $r->unitSubAddress,
                'fallback'   => $r->unitFallback,
                'none'       => $r->unitNone,
            ],
            'locality' => [
                'post_city' => $r->localityPostCity,
                'inc_muni'  => $r->localityIncMuni,
                'none'      => $r->localityNone,
            ],
            'normalization' => $collisions,
            'run'           => [
                'seconds'          => round($elapsed, 1),
                'peak_memory_bytes' => memory_get_peak_usage(true),
            ],
        ];
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = (int) floor(log($bytes, 1024));
        $i     = min($i, count($units) - 1);

        return number_format($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}
