<?php

namespace App\Console\Commands;

use App\Services\Spatial\Gate2\Gate2CoverageAssembler;
use App\Services\Spatial\Gate2\Gate2CoverageReport;
use Illuminate\Console\Command;
use Throwable;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3d-a.
 *
 * OFFLINE Gate 2 corpus-coverage EVIDENCE SCHEMA author. Assembles a dataset × territory coverage
 * matrix from the synthetic fixture and writes the deterministic evidence artifacts a product owner
 * would read at C3d-b:
 *   • matrix.json  — the full dataset × territory grid (every cell present; unmeasured is explicit)
 *   • summary.json — per-territory roll-ups, the PR watch-dataset verification block, and the
 *                    explicit "no metric / no threshold / no pass-fail / acceptance deferred" block
 *
 * HARD constraints (owner decision — Class-1 only, enforced here):
 *   • REFUSES to run in the production environment.
 *   • Opens NO PostGIS / pgsql_spatial connection (touches no DB at all).
 *   • Reads NO SPATIAL_* secret, downloads nothing, imports no corpus.
 *   • Computes NO coverage metric and NO pass/fail. Exit 0 means "the evidence schema assembled",
 *     NOT "Gate 2 passed". Real coverage measurement + product-owner acceptance are C3d-b (Class-2).
 *
 * There is deliberately NO --threshold option: Gate 2 has no threshold in the SSOT.
 */
class Gate2CoverageMatrix extends Command
{
    protected $signature = 'spatial:gate2-matrix
        {--fixture= : Path to the synthetic coverage input fixture (defaults to config/spatial_gate2.php)}
        {--out-dir= : Directory for the deterministic evidence artifacts}';

    protected $description = 'OFFLINE: author the Gate 2 dataset × territory coverage EVIDENCE SCHEMA (no PostGIS, no metric, no pass/fail, refuses production)';

    public function handle(): int
    {
        // ── Guard: never in production. No override flag by design. ────────────
        if (app()->environment('production')) {
            $this->error('[Batch 2D Part C3d-a] spatial:gate2-matrix is an OFFLINE authoring tool and REFUSES to run in production.');
            $this->line('Real Gate 2 coverage measurement + product-owner acceptance are deferred to C3d-b (Class-2).');

            return self::FAILURE;
        }

        $fixture = (string) ($this->option('fixture')
            ?: base_path((string) config('spatial_gate2.fixture')));

        $outDir = (string) ($this->option('out-dir')
            ?: storage_path((string) config('spatial_gate2.out_dir', 'app/spatial/gate2')));

        $required    = (array) config('spatial_gate2.required_territories', ['FL', 'PR', 'AK', 'rural_CONUS']);
        $prTerritory = (string) config('spatial_gate2.pr_territory', 'PR');

        try {
            $matrix = (new Gate2CoverageAssembler($required))->fromJsonFile($fixture);
            $report = new Gate2CoverageReport($matrix, $prTerritory);
        } catch (Throwable $e) {
            $this->error('[Batch 2D Part C3d-a] Gate 2 matrix could not be assembled: ' . $e->getMessage());

            return self::FAILURE;
        }

        $totals = $report->cellTotals();

        $this->info('[Batch 2D Part C3d-a] Gate 2 — corpus coverage EVIDENCE SCHEMA (synthetic inputs)');
        $this->line("  fixture      : {$fixture}");
        $this->line('  territories  : ' . implode(', ', $matrix->territories()));
        $this->line('  datasets     : ' . count($matrix->datasets()));
        $this->line('  cells        : ' . $totals['total']);
        $this->line("    present    : {$totals['present']}");
        $this->line("    absent     : {$totals['absent']} (measured, zero — honest gaps, NOT failures)");
        $this->line("    unmeasured : {$totals['unmeasured']} (not queried — distinct from measured-zero)");

        $this->newLine();
        $this->line('  per territory (present / absent / unmeasured):');
        foreach ($report->perTerritory() as $territory => $stats) {
            $this->line(sprintf('    - %-12s %d / %d / %d', $territory, $stats['present'], $stats['absent'], $stats['unmeasured']));
        }

        $this->newLine();
        $this->line("  PR watch datasets (E-32 — verify explicitly for {$prTerritory}):");
        foreach ($report->prWatchVerification() as $dataset => $cells) {
            foreach ($cells as $cell) {
                $count = $cell['present_count'] === null ? '—' : (string) $cell['present_count'];
                $this->line(sprintf('    - %-18s %-18s %-10s (present_count: %s)', $dataset, $cell['category'], $cell['status'], $count));
            }
        }

        $this->writeArtifact($outDir, 'matrix.json', $this->json($matrix->toArray()));
        $this->writeArtifact($outDir, 'summary.json', $this->json($report->toArray()));

        $this->newLine();
        $this->line('  artifacts written (DRY RUN — nothing measured against a corpus):');
        foreach (['matrix.json', 'summary.json'] as $f) {
            $this->line('    - ' . rtrim($outDir, '/') . '/' . $f);
        }

        $this->newLine();
        $this->warn('  NO coverage metric computed. NO threshold. NO automated pass/fail.');
        $this->warn('  Per-category coverage ACCEPTANCE is a product-owner decision — deferred to C3d-b (Class-2).');
        $this->info('[Batch 2D Part C3d-a] Gate 2 evidence schema assembled. This is NOT a Gate 2 pass.');

        return self::SUCCESS;
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
