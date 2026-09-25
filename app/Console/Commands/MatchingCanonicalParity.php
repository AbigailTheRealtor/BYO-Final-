<?php

namespace App\Console\Commands;

use App\Console\Concerns\RefusesProductionDatabase;
use App\Services\Stellar\Matching\Parity\CanonicalMatchingParityRunner;
use App\Services\Stellar\Matching\Parity\CanonicalParityReport;
use App\Support\Safeguards\ProductionDatabaseGuard;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * P1-B2 — `php artisan matching:canonical-parity`: the offline canonical matching parity
 * diagnostic (docs/plans/p1-canonical-matching-convergence.md §31).
 *
 * Compares the live Bridge facts path with the canonical facts path over locally stored
 * `bridge_properties` rows and prints a report. It is read-only and local: no table is
 * written, no job dispatched, no provider or HTTP client called, no config changed. It is
 * manual only — never scheduled, no route, no controller.
 *
 * PRODUCTION IS REFUSED, WITH NO OVERRIDE. The refusal is the first statement of handle(),
 * before any option is read, any row is queried or any file is touched. The command does
 * not declare `--i-know-this-is-production`, so Symfony rejects that flag before handle()
 * runs, and it passes `$overridePermitted = false`. The real-data evidence run is made
 * against a separately provisioned, sanitised, non-production copy of the MLS rows — the
 * command needs no change to point at one: it reads the configured default connection.
 *
 * Exit codes:
 *   0  completed; no undeclared difference and no error mismatch (allowed differences alone pass)
 *   1  execution failure (an unexpected exception)
 *   2  invalid options / bounds (nothing was read)
 *   3  production refused (ProductionDatabaseGuard::EXIT_REFUSED; nothing was read)
 *   4  completed with at least one UNDECLARED_DIFFERENCE
 *   5  completed with at least one ERROR_MISMATCH (takes precedence over 4)
 *   6  completed with CANONICAL_UNRESOLVABLE rows and --fail-on-unresolvable was given
 */
class MatchingCanonicalParity extends Command
{
    use RefusesProductionDatabase;

    public const EXIT_OK             = 0;
    public const EXIT_FAILURE        = 1;
    public const EXIT_INVALID        = 2;
    public const EXIT_REFUSED        = ProductionDatabaseGuard::EXIT_REFUSED;
    public const EXIT_UNDECLARED     = 4;
    public const EXIT_ERROR_MISMATCH = 5;
    public const EXIT_UNRESOLVABLE   = 6;

    protected $signature = 'matching:canonical-parity
        {--listing-key=* : Compare exactly these ListingKeys (current provider) instead of the stored population}
        {--max-listings=2000 : Listings to examine, 1-5000; larger values are refused, never clamped}
        {--per-type=0 : Cap per property-type stratum (0 = no cap)}
        {--stride=1 : Examine every Nth eligible row within each stratum (deterministic, by id)}
        {--from-id=0 : Resume after this bridge_properties id}
        {--examples=5 : Example listings shown per finding bucket, 0-50}
        {--json : Print the JSON report to stdout instead of the console summary}
        {--output= : Also write the JSON report to this local file}
        {--force-output : Overwrite an existing --output file}
        {--fail-on-unresolvable : Exit non-zero when any listing cannot be built canonically}';

    protected $description = 'Offline, read-only parity diagnostic: compares the canonical and Bridge match-facts paths over stored MLS rows. Refuses production.';

    public function handle(): int
    {
        // FIRST: nothing — not even the runner — is constructed before the production check.
        if ($this->refusesProductionDatabase(false)) {
            return self::EXIT_REFUSED;
        }

        try {
            $opts = $this->validatedOptions();
        } catch (InvalidArgumentException $e) {
            $this->getOutput()->getErrorStyle()->writeln('<error>' . $e->getMessage() . '</error>');

            return self::EXIT_INVALID;
        }

        $fail = (bool) $this->option('fail-on-unresolvable');

        try {
            $runner = $this->laravel->make(CanonicalMatchingParityRunner::class);
            $report = $runner->run($opts['keys'], $opts['max'], $opts['per_type'], $opts['stride'], $opts['from_id'], $opts['examples']);
            $json   = $report->toJson($fail);
        } catch (Throwable $e) {
            // The class only: a message can carry SQL or a stored value (the report's privacy rule).
            $this->getOutput()->getErrorStyle()->writeln('<error>Parity run failed: ' . get_class($e) . '</error>');

            return self::EXIT_FAILURE;
        }

        if ($opts['output'] !== null) {
            file_put_contents($opts['output'], $json);
        }

        if ($this->option('json')) {
            $this->getOutput()->write($json, false, \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);
        } else {
            $this->renderSummary($report, $fail, $opts['output']);
        }

        return match ($report->verdict($fail)) {
            CanonicalParityReport::VERDICT_ERROR_MISMATCH => self::EXIT_ERROR_MISMATCH,
            CanonicalParityReport::VERDICT_UNDECLARED     => self::EXIT_UNDECLARED,
            CanonicalParityReport::VERDICT_UNRESOLVABLE   => self::EXIT_UNRESOLVABLE,
            default                                       => self::EXIT_OK,
        };
    }

    /** Every option is validated before anything is read or written. */
    private function validatedOptions(): array
    {
        $int = function (string $name): int {
            $raw = (string) $this->option($name);
            if (!preg_match('/^-?\d+$/', $raw)) {
                throw new InvalidArgumentException("--{$name} must be a whole number (got \"{$raw}\").");
            }

            return (int) $raw;
        };

        $opts = [
            'max'      => $int('max-listings'),
            'per_type' => $int('per-type'),
            'stride'   => $int('stride'),
            'from_id'  => $int('from-id'),
            'examples' => $int('examples'),
        ];

        $opts['keys'] = CanonicalMatchingParityRunner::validateOptions(
            (array) $this->option('listing-key'), $opts['max'], $opts['per_type'], $opts['stride'], $opts['from_id'], $opts['examples'],
        );

        $opts['output'] = $this->validatedOutputPath($this->option('output'));

        return $opts;
    }

    /** A local file the operator names; never inside a web-served directory, never silently overwritten. */
    private function validatedOutputPath(mixed $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = (string) $path;
        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('--output is not a valid path.');
        }

        $dir = realpath(dirname($path));
        if ($dir === false || !is_dir($dir) || !is_writable($dir)) {
            throw new InvalidArgumentException('--output must name a file in an existing, writable directory.');
        }

        foreach ([public_path(), storage_path('app/public')] as $served) {
            $served = realpath($served);
            if ($served !== false && ($dir === $served || str_starts_with($dir . DIRECTORY_SEPARATOR, $served . DIRECTORY_SEPARATOR))) {
                throw new InvalidArgumentException('--output may not be inside a web-served directory.');
            }
        }

        $target = $dir . DIRECTORY_SEPARATOR . basename($path);
        if (file_exists($target) && !$this->option('force-output')) {
            throw new InvalidArgumentException('--output file exists; pass --force-output to overwrite it.');
        }

        return $target;
    }

    private function renderSummary(CanonicalParityReport $report, bool $fail, ?string $output): void
    {
        $r    = $report->result;
        $sel  = $r['selection'];
        $cost = $report->cost;

        $this->line('<info>Canonical matching parity — offline, read-only</info>');
        $this->line(sprintf('Mode: %s · coverage: %s%s · connection: %s (%s)',
            $sel['mode'], $sel['coverage'], $sel['partial_reasons'] === [] ? '' : ' (' . implode(', ', $sel['partial_reasons']) . ')',
            $report->run['connection'], $report->run['driver']));
        $this->line('Post-attachment tag comparison: ' . CanonicalMatchingParityRunner::postAttachmentPosture($r));
        $this->newLine();

        // Findings that fail the run come first.
        $status = $r['listings']['status'];
        $bad = [
            ['ERROR_MISMATCH', $status['ERROR_MISMATCH'] ?? 0, $sel['examined']],
            ['UNDECLARED_DIFFERENCE', $status['UNDECLARED_DIFFERENCE'] ?? 0, $sel['examined']],
        ];
        $this->table(['Failing finding (listings)', 'Count', 'Of examined'], $bad);
        foreach (['error_mismatch', 'undeclared_facts', 'undeclared_outcomes', 'post_attachment_undeclared'] as $bucket) {
            $this->renderExamples($r['examples'][$bucket] ?? null, $bucket);
        }

        $excluded = [];
        foreach ($sel['excluded'] as $reason => $n) {
            $excluded[] = "{$reason}={$n}";
        }
        $this->table(['Coverage', 'Value'], [
            ['rows scanned', $sel['rows_scanned']],
            ['eligible', $sel['eligible']],
            ['examined', $sel['examined']],
            ['excluded', $excluded === [] ? '0' : implode(', ', $excluded)],
            ['canonical resolved', $r['resolution']['resolved'] . ' / ' . $sel['examined']],
            ['canonical unresolvable', $r['resolution']['unresolvable'] . ' / ' . $sel['examined']],
            ['criteria cases evaluated', $r['outcomes']['cases_evaluated']],
        ]);

        $rows = [];
        foreach (array_merge(CanonicalMatchingParityRunner::STRATA, [CanonicalMatchingParityRunner::OTHER_TYPE]) as $stratum) {
            $s = $r['listings']['by_stratum'][$stratum] ?? [];
            $rows[] = [
                $stratum,
                $sel['examined_by_stratum'][$stratum] ?? 0,
                $s['EXACT'] ?? 0, $s['ALLOWED_DIFFERENCE'] ?? 0, $s['UNDECLARED_DIFFERENCE'] ?? 0,
                $s['CANONICAL_UNRESOLVABLE'] ?? 0, $s['ERROR_PARITY'] ?? 0, $s['ERROR_MISMATCH'] ?? 0,
            ];
        }
        $this->table(['Stratum', 'Examined', 'Exact', 'Allowed', 'Undeclared', 'Unresolvable', 'Err parity', 'Err mismatch'], $rows);

        $ads = [];
        foreach ($r['registry']['ids'] as $ad) {
            $e = $r['facts']['by_ad'][$ad] ?? null;
            $ads[] = [$ad, ($e['listings'] ?? 0) . ' / ' . $r['facts']['listings_compared'], $e === null ? '' : implode(', ', array_map(fn ($f, $n) => "{$f}={$n}", array_keys($e['fields']), $e['fields']))];
        }
        $this->table(['Allowed difference', 'Listings', 'Fields'], $ads);

        $mm = [];
        foreach (['total_score', 'category_scores', 'important_places', 'explanations'] as $g) {
            $m = $r['outcomes']['mismatches'][$g] ?? [];
            $mm[] = [$g, $m['allowed'] ?? 0, $m['undeclared'] ?? 0, $r['outcomes']['cases_evaluated']];
        }
        $this->table(['Outcome', 'Allowed diffs', 'Undeclared diffs', 'Of cases'], $mm);

        $rank = $r['outcomes']['ranking'];
        $this->line(sprintf('Ranking: %d / %d cohorts in identical order.', $rank['exact_order'] ?? 0, $rank['cohorts'] ?? 0));
        $this->line(sprintf('Cost: %.1f ms, %d queries, path cost ratio %s (budget %.1f).',
            $cost['elapsed_ms'], $cost['queries'], $cost['path_cost_ratio'] ?? 'n/a', $cost['budget_ratio']));
        $this->line('Result digest: ' . $report->digest());
        $this->line('Verdict: ' . $report->verdict($fail));
        if ($output !== null) {
            $this->line('JSON report written to ' . $output);
        }
    }

    private function renderExamples(?array $bucket, string $name): void
    {
        if ($bucket === null || $bucket['shown'] === []) {
            return;
        }

        $this->line("<comment>{$name}: {$bucket['count']} (showing " . count($bucket['shown']) . ')</comment>');
        foreach ($bucket['shown'] as $e) {
            $this->line('  ' . json_encode($e, JSON_UNESCAPED_SLASHES));
        }
    }
}
