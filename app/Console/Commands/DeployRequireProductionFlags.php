<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Refuse to serve a release whose required product flags are wrong.
 *
 * THE OPPOSITE OF deploy:preflight, DELIBERATELY
 * ----------------------------------------------
 * That command reports and always exits zero, because the conditions it
 * describes have semantics that could not be established from outside the
 * container. This one gates, because its conditions are settled: the contract in
 * config/required_production_flags.php names surfaces that are finished and
 * verified live, and a release that disagrees with it is a regression that would
 * otherwise ship looking perfectly healthy.
 *
 * FAIL CLOSED MEANS FAIL BEFORE BINDING A PORT
 * --------------------------------------------
 * deploy/start-production.sh calls this with no `|| true`, before `migrate` and
 * long before `serve`, so a wrong flag fails the deployment's health check
 * instead of serving the wrong platform to users. A regression that answers 200
 * is worse than a deploy that never starts: the first is discovered by a
 * customer, the second by the deploy log.
 *
 * READ-ONLY, IN BOTH DIRECTIONS
 * -----------------------------
 * It reads config and exits. It cannot enable a flag, disable one, repair one,
 * write a file or reach a database, so running it twice or killing it mid-run
 * leaves nothing behind — and a wrong value stops a deploy rather than being
 * silently corrected into a shape nobody chose.
 *
 * IT PRINTS KEYS AND FLAG VALUES, NEVER CREDENTIALS. The contract may only name
 * boolean and role-list product flags, and RequiredProductionFlagsTest asserts
 * that no credential or safety switch is in it, so there is nothing secret for
 * this output to leak into a deployment log.
 */
class DeployRequireProductionFlags extends Command
{
    protected $signature = 'deploy:require-flags {--report : print the contract and always exit 0}';

    protected $description = 'Refuse to start unless every required production flag resolves to its contracted value';

    public function handle(): int
    {
        $contract = config('required_production_flags.required');
        $enforced = (bool) config('required_production_flags.enforced', true);
        $report   = (bool) $this->option('report');

        $this->line('── required production flags ───────────────────────');

        // An empty contract is NOT "everything passed". A config file that failed
        // to load, a typo in the filename, a deploy that shipped without it — all
        // look identical to a contract that requires nothing, and the difference
        // matters far too much to infer. Fail closed on the ambiguity itself.
        if (! is_array($contract) || $contract === []) {
            $this->error('  The required-flag contract is EMPTY or unreadable.');
            $this->error('  config/required_production_flags.php did not load, so nothing could be verified.');

            if ($report) {
                $this->warn('  --report: exiting 0 without gating.');

                return self::SUCCESS;
            }

            if (! $enforced) {
                $this->warn('  REQUIRED_PRODUCTION_FLAGS_ENFORCED=false — starting anyway. THE GATE IS OFF.');

                return self::SUCCESS;
            }

            return self::FAILURE;
        }

        $failures = [];

        foreach ($contract as $key => $spec) {
            $expected = is_array($spec) ? ($spec['expect'] ?? null) : null;
            $actual   = config($key);

            $ok = $this->satisfies($expected, $actual);

            $this->line(sprintf(
                '  %-6s %-42s %s',
                $ok ? '[ ok ]' : '[FAIL]',
                $key,
                $this->render($actual)
            ));

            if (! $ok) {
                $failures[$key] = [
                    'expected' => $this->render($expected),
                    'actual'   => $this->render($actual),
                    'why'      => (string) (is_array($spec) ? ($spec['why'] ?? '') : ''),
                ];
            }
        }

        if ($failures === []) {
            $this->line('  all required flags satisfied');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('  ' . count($failures) . ' required production flag(s) are not satisfied:');

        foreach ($failures as $key => $failure) {
            $this->error(sprintf('    %s  expected %s, got %s', $key, $failure['expected'], $failure['actual']));

            if ($failure['why'] !== '') {
                $this->error('      ' . $failure['why']);
            }
        }

        if ($report) {
            $this->newLine();
            $this->warn('  --report: exiting 0 without gating.');

            return self::SUCCESS;
        }

        if (! $enforced) {
            // Loud on purpose, and in the failure stream. A disabled gate that
            // logs quietly is a gate nobody knows is disabled.
            $this->newLine();
            $this->warn('  REQUIRED_PRODUCTION_FLAGS_ENFORCED=false — starting anyway. THE GATE IS OFF.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('  Refusing to start. Fix the environment, or set');
        $this->error('  REQUIRED_PRODUCTION_FLAGS_ENFORCED=false to override for one deploy.');

        return self::FAILURE;
    }

    /**
     * Does the resolved value satisfy the contracted one?
     *
     * An array expectation is a SUBSET test — every named value must be present,
     * extras are fine. See the note in config/required_production_flags.php: a
     * fifth role adopting a redesign must not fail a deployment for being extra.
     *
     * Anything else is compared as a boolean, so the string "false" arriving from
     * an environment file does not read as satisfied merely for being non-empty —
     * the config layer has already cast it by the time it reaches here, and this
     * cast is the backstop for a key that skipped that layer.
     */
    private function satisfies(mixed $expected, mixed $actual): bool
    {
        if (is_array($expected)) {
            return is_array($actual) && array_diff($expected, $actual) === [];
        }

        return (bool) $actual === (bool) $expected;
    }

    private function render(mixed $value): string
    {
        return is_array($value)
            ? '[' . implode(',', array_map('strval', $value)) . ']'
            : var_export($value, true);
    }
}
