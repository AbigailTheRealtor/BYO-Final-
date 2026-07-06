<?php

namespace App\Services\Dna\Relevance\Validation;

/**
 * ValidationReport — Matching V2 C6.1 (read-only validation harness).
 *
 * The immutable-ish collector for one `matching:validate` run: per-scenario rows
 * (each with its hard/advisory checks) plus the cross-cutting SAFETY checks
 * (determinism, read-only row counts, flag restoration). Persists nothing; it is
 * a diagnostic artifact only.
 *
 * A HARD check that fails (a scenario check or a safety check with
 * severity === 'hard') makes the whole run a failure — that is what the command
 * maps to a non-zero exit code.
 *
 * @see docs/matching-v2-c6_1-validation-harness-scope.md
 */
class ValidationReport
{
    /** @var array<int,array<string,mixed>> */
    private array $scenarios = [];

    /** @var array<int,array{name:string,severity:string,pass:bool,detail:string}> */
    private array $safety = [];

    public function addScenario(array $row): void
    {
        $this->scenarios[] = $row;
    }

    public function addSafetyCheck(array $check): void
    {
        $this->safety[] = $check;
    }

    /** @return array<int,array<string,mixed>> */
    public function scenarios(): array
    {
        return $this->scenarios;
    }

    /** @return array<int,array{name:string,severity:string,pass:bool,detail:string}> */
    public function safetyChecks(): array
    {
        return $this->safety;
    }

    /** Any hard check (scenario or safety) failed → the run is a failure. */
    public function hasHardFailure(): bool
    {
        foreach ($this->scenarios as $scenario) {
            foreach ($scenario['checks'] as $check) {
                if ($check['severity'] === 'hard' && $check['pass'] === false) {
                    return true;
                }
            }
        }

        foreach ($this->safety as $check) {
            if ($check['severity'] === 'hard' && $check['pass'] === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compact per-scenario rows for the console summary table.
     *
     * @return array<int,array<int,string>>
     */
    public function summaryRows(): array
    {
        $rows = [];
        foreach ($this->scenarios as $s) {
            $status = $this->scenarioHardFailed($s) ? 'FAIL' : 'PASS';
            $rows[] = [
                $s['scenario'],
                $s['subject_type'] . '#' . $s['subject_id'],
                (string) ($s['direction'] ?? 'n/a'),
                (string) $s['considered'],
                (string) $s['determined'],
                (string) json_encode($s['tier_counts']),
                $s['truncated'] ? 'yes' : 'no',
                $status,
            ];
        }

        return $rows;
    }

    private function scenarioHardFailed(array $scenario): bool
    {
        foreach ($scenario['checks'] as $check) {
            if ($check['severity'] === 'hard' && $check['pass'] === false) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'has_hard_failure' => $this->hasHardFailure(),
            'scenario_count'   => count($this->scenarios),
            'safety_checks'    => $this->safety,
            'scenarios'        => $this->scenarios,
        ];
    }
}
