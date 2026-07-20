<?php

namespace App\Console\Commands;

use App\Services\Spatial\Gate1\Gate1RankSanityEvaluator;
use App\Services\Spatial\Gate1\Gate1ScenarioSet;
use Illuminate\Console\Command;
use Throwable;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part B.
 *
 * OFFLINE Hybrid Gate 1 runtime validator (Option E). Loads the synthetic benchmark
 * (Option D), ranks every scenario through the real ranking engine, and reports the
 * embarrassment rate against the SSOT §9.3 ≤3% threshold.
 *
 * HARD constraints (owner decision — Class-1 only):
 *   • REFUSES to run in the production environment.
 *   • Opens NO PostGIS / pgsql_spatial connection (touches no DB at all).
 *   • Reads NO SPATIAL_* secret, downloads nothing, imports no corpus.
 *   • Real-corpus Gate 1 (corpus nearest-3 vs frozen baseline, exclusion rules) is Class-2.
 */
class Gate1Validate extends Command
{
    protected $signature = 'spatial:gate1-validate
        {--scenarios= : Path to the synthetic scenario fixture (defaults to config/spatial_gate1.php)}
        {--top= : Override the top-N window (default from config)}
        {--threshold= : Override the pass threshold as a fraction, e.g. 0.03 (default from config)}';

    protected $description = 'OFFLINE: run the Hybrid Gate 1 rank-sanity harness over the synthetic benchmark (no PostGIS, refuses production)';

    public function handle(): int
    {
        // ── Guard: never in production. No override flag by design. ────────────
        if (app()->environment('production')) {
            $this->error('[Batch 2D Part B] spatial:gate1-validate is an OFFLINE authoring tool and REFUSES to run in production.');
            $this->line('Real-corpus Gate 1 (corpus nearest-3 vs ground truth) is deferred to the Class-2 phase.');

            return self::FAILURE;
        }

        $fixture = (string) ($this->option('scenarios')
            ?: base_path((string) config('spatial_gate1.scenario_fixture')));

        $topN      = $this->option('top') !== null ? (int) $this->option('top') : (int) config('spatial_gate1.top_n', 3);
        $threshold = $this->option('threshold') !== null
            ? (float) $this->option('threshold')
            : (float) config('spatial_gate1.embarrassment_threshold', 0.03);

        try {
            $set    = Gate1ScenarioSet::fromJsonFile($fixture);
            $report = (new Gate1RankSanityEvaluator(null, $topN, $threshold))->evaluate($set);
        } catch (Throwable $e) {
            $this->error('[Batch 2D Part B] Gate 1 harness could not run: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('[Batch 2D Part B] Hybrid Gate 1 — rank-sanity (synthetic benchmark, Option D)');
        $this->line("  fixture         : {$fixture}");
        $this->line("  scenarios       : {$report->scenarioCount()}");
        $this->line("  top-N window    : {$report->topN()}");
        $this->line("  evaluated slots : {$report->evaluatedSlots()}");
        $this->line("  embarrassments  : {$report->embarrassmentCount()}");
        $this->line(sprintf('  rate            : %.2f%% (threshold %.2f%%)', $report->rate() * 100, $report->threshold() * 100));

        $this->newLine();
        $this->line('  per category (embarrassments / slots):');
        foreach ($report->perCategory() as $category => $stats) {
            $this->line(sprintf('    - %-18s %d / %d', $category, $stats['embarrassments'], $stats['slots']));
        }

        if ($report->offenders() !== []) {
            $this->newLine();
            $this->line('  offenders (illegitimate POIs inside the top-N window):');
            foreach ($report->offenders() as $offender) {
                $this->line(sprintf(
                    '    - [%s] rank %d: "%s" (%s)',
                    $offender['scenario'],
                    $offender['rank'],
                    $offender['name'],
                    $offender['category'],
                ));
            }
        }

        $this->newLine();

        if ($report->passed()) {
            $this->info(sprintf('  PASS — embarrassment rate %.2f%% <= %.2f%%.', $report->rate() * 100, $report->threshold() * 100));

            return self::SUCCESS;
        }

        $this->error(sprintf('  FAIL — embarrassment rate %.2f%% > %.2f%%.', $report->rate() * 100, $report->threshold() * 100));
        $this->line('  Remediate via category mappings and §8.2 exclusion rules — never invented ratings (SSOT §9.3).');

        return self::FAILURE;
    }
}
