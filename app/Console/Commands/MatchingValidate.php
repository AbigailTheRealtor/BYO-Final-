<?php

namespace App\Console\Commands;

use App\Models\DnaScore;
use App\Services\Dna\Relevance\Validation\MatchingValidationRunner;
use App\Services\Dna\Relevance\Validation\ValidationReport;
use App\Services\Dna\Relevance\Validation\ValidationRosterBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * matching:validate — Matching V2 C6.1 read-only validation harness.
 *
 * Runs the approved validation roster through the SAME backend pipeline
 * matching:preview uses, writes per-scenario + summary JSON to an output dir, and
 * prints a summary table. Compliance scenarios run FIRST.
 *
 * SAFETY (enforced here):
 *   - STAGING/DEV ONLY: aborts on the production environment with NO override.
 *   - Requires a populated dna_scores corpus (else there is nothing to validate).
 *   - READ-ONLY: the runner writes nothing to the DB and restores the in-process
 *     Matching V2 flag; this command's only writes are diagnostic JSON files.
 *   - Never enables DNA generation, never persists match results, exposes no
 *     UI/API, and never enables Matching V2 in production.
 *
 * Exit codes: 0 = all hard checks passed · 1 = a hard check failed ·
 * 2 = refused (production env or empty corpus).
 *
 * @see docs/matching-v2-c6_1-validation-harness-scope.md
 */
class MatchingValidate extends Command
{
    protected $signature = 'matching:validate
        {--roster= : path to a pinned roster JSON (default: auto-discover)}
        {--out= : output directory (default storage/app/matching-validation)}
        {--limit=5 : subjects per auto-discovered category}
        {--cap= : discovery candidate cap passthrough}
        {--determinism-sample=3 : how many subjects to double-run for determinism}
        {--fail-fast : stop at the first compliance failure}';

    protected $description = 'Read-only Matching V2 validation harness (staging/dev only; never enables V2/generation in prod).';

    private const EXIT_REFUSED = 2;

    public function handle(): int
    {
        // --- guard 1: staging/dev only, no override ---
        if ($this->getLaravel()->environment('production')) {
            $this->error('matching:validate is a staging/dev diagnostic and refuses to run in production.');
            return self::EXIT_REFUSED;
        }

        // --- guard 2: nothing to validate on an empty corpus ---
        if (DnaScore::count() === 0) {
            $this->error('No dna_scores rows found. Enable generation in STAGING and run `dna:generate-scores` first.');
            return self::EXIT_REFUSED;
        }

        $out = $this->option('out') ?: storage_path('app/matching-validation');
        $cap = $this->option('cap') !== null ? (int) $this->option('cap') : null;

        $roster = null;
        if ($this->option('roster')) {
            $roster = app(ValidationRosterBuilder::class)->fromFile((string) $this->option('roster'));
        }

        $this->info('Matching V2 validation — READ-ONLY, in-process force-enable only.');

        $report = app(MatchingValidationRunner::class)->run([
            'limit'              => (int) $this->option('limit'),
            'cap'                => $cap,
            'determinism_sample' => (int) $this->option('determinism-sample'),
            'fail_fast'          => (bool) $this->option('fail-fast'),
            'roster'             => $roster,
        ]);

        $this->writeOutputs($out, $report);
        $this->renderSummary($report);
        $this->line('Output written to: ' . $out);

        return $report->hasHardFailure() ? self::FAILURE : self::SUCCESS;
    }

    private function writeOutputs(string $dir, ValidationReport $report): void
    {
        File::ensureDirectoryExists($dir);

        foreach ($report->scenarios() as $scenario) {
            $name = sprintf('%s-%s-%s.json', $scenario['scenario'], $scenario['subject_type'], $scenario['subject_id']);
            File::put(
                rtrim($dir, '/') . '/' . $name,
                (string) json_encode($scenario['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );
        }

        File::put(
            rtrim($dir, '/') . '/summary.json',
            (string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    private function renderSummary(ValidationReport $report): void
    {
        $this->table(
            ['scenario', 'subject', 'direction', 'considered', 'determined', 'tiers', 'trunc', 'status'],
            $report->summaryRows(),
        );

        $this->line('Safety checks:');
        foreach ($report->safetyChecks() as $check) {
            $mark = $check['pass'] ? '<info>PASS</info>' : '<error>FAIL</error>';
            $this->line(sprintf('  [%s] %s (%s) — %s', $mark, $check['name'], $check['severity'], $check['detail']));
        }

        if ($report->hasHardFailure()) {
            $this->error('HARD FAILURE — one or more compliance/determinism/read-only invariants did not hold.');
        } else {
            $this->info('All hard invariants held (advisory notes above are for human review).');
        }
    }
}
