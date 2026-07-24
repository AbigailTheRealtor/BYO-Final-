<?php

namespace App\Console\Commands;

use App\Services\Spatial\Gate2\CorpusCoverageObservationSource;
use App\Services\Spatial\Gate2\CoverageQueryCatalog;
use App\Services\Spatial\Gate2\Gate2CoverageAssembler;
use App\Services\Spatial\Gate2\Gate2CoverageReport;
use App\Services\Spatial\Gate2\TerritoryCoverageLedgerWriter;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3d-b (Gate 2 Florida pilot, Class-2).
 *
 * LIVE Gate 2 corpus-coverage measurement. Unlike the offline C3d-a `spatial:gate2-matrix` (synthetic
 * fixture, no DB), this command measures the REAL loaded corpus on pgsql_spatial: it runs the
 * {@see CoverageQueryCatalog}'s read-only COUNTs via {@see CorpusCoverageObservationSource}, feeds the
 * counts to the UNCHANGED {@see Gate2CoverageAssembler} / {@see Gate2CoverageReport}, writes the
 * deterministic matrix.json / summary.json, and records ONE `corpus_imports` ledger row via
 * {@see TerritoryCoverageLedgerWriter}.
 *
 * HARD constraints (owner decision — enforced here):
 *   • REFUSES to run in production.
 *   • REFUSES unless the spatial connection is configured (SPATIAL_DATABASE_URL / SPATIAL_PGHOST).
 *   • FLORIDA-ONLY: measures FL alone. PR / AK / rural_CONUS stay `unmeasured` in the matrix; asking
 *     for any non-FL territory is refused (their execution is not approved in C3d-b Group A).
 *   • Computes NO coverage metric and NO pass/fail. There is deliberately NO --threshold option.
 *     Command SUCCESS means "the evidence was measured and assembled" — it is NOT Gate 2 acceptance,
 *     which is a per-category PRODUCT-OWNER decision (SSOT — no Gate 2 formula).
 *   • Loads NO dataset and mutates NO corpus table — the only write is the single ledger row.
 */
class Gate2MeasureCoverage extends Command
{
    /** Container key a test binds to inject a deterministic count runner in place of the live one. */
    public const COUNT_RUNNER_BINDING = 'spatial.gate2.count_runner';

    protected $signature = 'spatial:gate2-measure-coverage
        {--territory=FL : Territory to measure. Florida-only in C3d-b — only FL is accepted.}
        {--corpus-version= : Names the measured corpus snapshot (ledger run key; defaults to config).}
        {--out-dir= : Directory for matrix.json / summary.json (defaults to config).}';

    protected $description = 'LIVE: measure Gate 2 corpus coverage for Florida against pgsql_spatial (no metric, no threshold, no pass/fail, refuses production; records one corpus_imports row)';

    public function handle(): int
    {
        // ── Guard 1: never in production. No override flag by design. ──────────
        if (app()->environment('production')) {
            $this->error('[Batch 2D Part C3d-b] spatial:gate2-measure-coverage REFUSES to run in production.');
            $this->line('The Florida Gate 2 pilot is a Class-2 controlled measurement; production execution is not approved.');

            return self::FAILURE;
        }

        // ── Guard 2: Florida-only. ─────────────────────────────────────────────
        $territories = $this->requestedTerritories();
        if ($territories !== ['FL']) {
            $this->error('[Batch 2D Part C3d-b] This command is FLORIDA-ONLY (--territory=FL).');
            $this->line('PR, AK, rural_CONUS, and national execution are NOT approved in C3d-b Group A; they stay unmeasured.');

            return self::FAILURE;
        }

        // ── Guard 3: the spatial connection must be configured. ────────────────
        $connectionName = (string) config('spatial_gate2_corpus.connection', 'pgsql_spatial');
        if (! $this->spatialConnectionConfigured($connectionName)) {
            $this->error("[Batch 2D Part C3d-b] The spatial connection [{$connectionName}] is not configured.");
            $this->line('Set SPATIAL_DATABASE_URL (or SPATIAL_PGHOST / SPATIAL_PGDATABASE) — this command reads the live corpus and cannot run without a cluster.');

            return self::FAILURE;
        }

        $corpusVersion = (string) ($this->option('corpus-version')
            ?: config('spatial_gate2_corpus.default_corpus_version', 'c3d-b-fl-pilot-unversioned'));

        $outDir = (string) ($this->option('out-dir')
            ?: storage_path((string) config('spatial_gate2_corpus.out_dir', 'app/spatial/gate2-corpus')));

        // ── Measure the corpus (read-only) and assemble via the UNCHANGED C3d-a engine. ──
        try {
            $catalog = new CoverageQueryCatalog(
                (array) config('spatial_gate2_corpus.datasets', []),
                (array) config('spatial_gate2_corpus.state_fips', []),
                (string) config('spatial_gate2_corpus.place_territory_boundary_kind', 'county'),
            );

            $source       = new CorpusCoverageObservationSource($catalog, $this->countRunner($connectionName));
            $observations = $source->observe($territories);

            $required    = (array) config('spatial_gate2.required_territories', ['FL', 'PR', 'AK', 'rural_CONUS']);
            $prWatch     = (array) config('spatial_gate2.pr_watch_datasets', []);
            $prTerritory = (string) config('spatial_gate2.pr_territory', 'PR');

            $input = [
                'territories'       => array_values($required),
                'datasets'          => $this->declaredDatasets(),
                'pr_watch_datasets' => array_values($prWatch),
                'observations'      => $observations,
            ];

            $matrix = (new Gate2CoverageAssembler($required))->fromArray($input);
            $report = new Gate2CoverageReport($matrix, $prTerritory);
        } catch (Throwable $e) {
            // Assembly failed → NO artifacts, NO ledger row (the write is strictly post-assembly).
            $this->error('[Batch 2D Part C3d-b] Gate 2 coverage could not be measured/assembled: ' . $e->getMessage());

            return self::FAILURE;
        }

        $totals = $report->cellTotals();

        $this->info('[Batch 2D Part C3d-b] Gate 2 — corpus coverage LIVE MEASUREMENT (Florida pilot)');
        $this->line("  connection   : {$connectionName}");
        $this->line("  corpus_ver   : {$corpusVersion}");
        $this->line('  measured     : FL only (PR / AK / rural_CONUS remain unmeasured)');
        $this->line('  territories  : ' . implode(', ', $matrix->territories()));
        $this->line('  cells        : ' . $totals['total']);
        $this->line("    present    : {$totals['present']}");
        $this->line("    absent     : {$totals['absent']} (measured, zero — honest gaps, NOT failures)");
        $this->line("    unmeasured : {$totals['unmeasured']} (not queried — distinct from measured-zero)");

        $this->newLine();
        $this->line('  per territory (present / absent / unmeasured):');
        foreach ($report->perTerritory() as $territory => $stats) {
            $this->line(sprintf('    - %-12s %d / %d / %d', $territory, $stats['present'], $stats['absent'], $stats['unmeasured']));
        }

        $this->writeArtifact($outDir, 'matrix.json', $this->json($matrix->toArray()));
        $this->writeArtifact($outDir, 'summary.json', $this->json($report->toArray()));

        // ── The ONLY write: one corpus_imports ledger row, strictly after assembly. ──
        $observedFeatures = array_sum(array_map(static fn (array $o): int => (int) $o['present_count'], $observations));
        $ledgerResult = $this->recordLedger($connectionName, $corpusVersion, $observedFeatures, $report, $territories);

        $this->newLine();
        $this->line('  artifacts written:');
        foreach (['matrix.json', 'summary.json'] as $f) {
            $this->line('    - ' . rtrim($outDir, '/') . '/' . $f);
        }
        $this->line('  corpus_imports ledger row: ' . ($ledgerResult['written'] ? 'INSERTED' : 'skipped (' . $ledgerResult['reason'] . ')'));

        $this->newLine();
        $this->warn('  NO coverage metric computed. NO threshold. NO automated pass/fail.');
        $this->warn('  Per-category coverage ACCEPTANCE is a product-owner decision — this run does NOT accept Gate 2.');
        $this->info('[Batch 2D Part C3d-b] Gate 2 coverage measured & assembled. This is NOT a Gate 2 pass.');

        return self::SUCCESS;
    }

    /**
     * The count runner the observation source calls. Bound to the pgsql_spatial connection by default;
     * a test may bind {@see self::COUNT_RUNNER_BINDING} in the container to inject a deterministic fake.
     *
     * @return Closure(string,list<mixed>):int
     */
    private function countRunner(string $connectionName): Closure
    {
        if (app()->bound(self::COUNT_RUNNER_BINDING)) {
            /** @var Closure(string,list<mixed>):int $fake */
            $fake = app(self::COUNT_RUNNER_BINDING);

            return $fake;
        }

        return static function (string $sql, array $bindings) use ($connectionName): int {
            $row = DB::connection($connectionName)->selectOne($sql, $bindings);

            return (int) (is_object($row) ? ($row->present_count ?? 0) : 0);
        };
    }

    /**
     * Record the single provenance row for this measurement run.
     *
     * @param  list<string> $territories
     * @return array{written:bool,skipped:bool,reason:string}
     */
    private function recordLedger(string $connectionName, string $corpusVersion, int $observedFeatures, Gate2CoverageReport $report, array $territories): array
    {
        $required   = $report->matrix()->territories();
        $unmeasured = array_values(array_diff($required, $territories));

        $territoryCoverage = [
            'scope'                  => 'c3d-b-fl-pilot',
            'measured_territories'   => array_values($territories),
            'unmeasured_territories' => $unmeasured,
            'per_territory'          => $report->perTerritory(),
            'cell_totals'            => $report->cellTotals(),
        ];

        $notes = [
            'command'    => 'spatial:gate2-measure-coverage',
            'batch'      => 'phase-2-batch-2d-part-c3d-b',
            'no_metric'  => true,
            'acceptance' => 'deferred to product owner (per-category)',
        ];

        $now = now()->toIso8601String();

        $writer = new TerritoryCoverageLedgerWriter(DB::connection($connectionName));

        $result = $writer->write(
            dataset: (string) config('spatial_gate2_corpus.ledger_dataset', 'gate2_coverage'),
            corpusVersion: $corpusVersion,
            rowCount: $observedFeatures,
            territoryCoverage: $territoryCoverage,
            notes: $notes,
            startedAt: $now,
            finishedAt: $now,
        );

        return ['written' => $result['written'], 'skipped' => $result['skipped'], 'reason' => $result['reason']];
    }

    /** @return list<string> */
    private function requestedTerritories(): array
    {
        $raw = (string) $this->option('territory');

        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $t): bool => $t !== ''));
    }

    /**
     * The full declared dataset axis for the assembler input, in config order.
     *
     * @return list<array{key:string,categories:list<string>}>
     */
    private function declaredDatasets(): array
    {
        $out = [];
        foreach ((array) config('spatial_gate2_corpus.datasets', []) as $key => $spec) {
            $out[] = [
                'key'        => (string) $key,
                'categories' => array_values(array_map('strval', (array) ($spec['categories'] ?? []))),
            ];
        }

        return $out;
    }

    private function spatialConnectionConfigured(string $connectionName): bool
    {
        $conf = config("database.connections.{$connectionName}");
        if (empty($conf) || ! is_array($conf)) {
            return false;
        }

        return ! (empty($conf['url']) && empty($conf['host']) && empty($conf['database']));
    }

    private function writeArtifact(string $dir, string $file, string $contents): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(rtrim($dir, '/') . '/' . $file, $contents);
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n";
    }
}
