<?php

namespace App\Services\Dna\Relevance\Validation;

use App\Services\Dna\Relevance\CandidateAttributeResolverInterface;
use App\Services\Dna\Relevance\MatchDirection;
use App\Services\Dna\Relevance\MatchingV2Service;
use App\Services\Dna\Relevance\OrchestratedMatchResult;
use Illuminate\Support\Facades\DB;

/**
 * MatchingValidationRunner — Matching V2 C6.1 (read-only validation harness).
 *
 * Runs the approved validation roster through the SAME backend pipeline
 * matching:preview uses (MatchingV2Service::matchForSubject), evaluates each
 * scenario's hard/advisory checks, and runs the cross-cutting safety checks
 * (determinism, read-only row counts, flag restoration) into a ValidationReport.
 *
 * SAFETY GOVERNANCE:
 *   - PURE READ-ONLY: it only reads dna_scores + attributes; it writes nothing to
 *     the database and persists no match results. The read-only safety check
 *     asserts every product-table row count is unchanged across the whole run.
 *   - Matching V2 is FORCE-ENABLED in-process only (config override, like the
 *     preview command) and ALWAYS restored in a finally — never written to .env or
 *     any persisted store. It never enables DNA generation.
 *   - Staging/dev only; the command owns the production guard.
 *
 * @see docs/matching-v2-c6_1-validation-harness-scope.md
 */
class MatchingValidationRunner
{
    /** Product tables whose row counts must be unchanged by a read-only run. */
    private const READ_ONLY_TABLES = [
        'dna_scores',
        'seller_agent_auctions', 'landlord_agent_auctions', 'buyer_agent_auctions', 'tenant_agent_auctions',
        'seller_agent_auction_metas', 'landlord_agent_auction_metas', 'buyer_agent_auction_metas', 'tenant_agent_auction_metas',
        'property_location_dna',
    ];

    public function __construct(
        private readonly MatchingV2Service $matching,
        private readonly ValidationRosterBuilder $roster,
        private readonly CandidateAttributeResolverInterface $attributes,
    ) {
    }

    /**
     * @param array{limit?:int,cap?:int|null,determinism_sample?:int,fail_fast?:bool,roster?:array} $opts
     */
    public function run(array $opts = []): ValidationReport
    {
        $limit             = (int) ($opts['limit'] ?? 5);
        $cap               = array_key_exists('cap', $opts) ? $opts['cap'] : null;
        $determinismSample = (int) ($opts['determinism_sample'] ?? 3);
        $failFast          = (bool) ($opts['fail_fast'] ?? false);
        $entries           = $this->roster->complianceFirst($opts['roster'] ?? $this->roster->build($limit));

        $report     = new ValidationReport();
        $before     = $this->rowCounts();
        $flagBefore = (bool) config('matching.v2_enabled', false);

        // Force-enable in-process (read-only preview), always restored below.
        config(['matching.v2_enabled' => true]);
        try {
            foreach ($entries as $entry) {
                $row = $this->runScenario($entry, $cap);
                $report->addScenario($row);

                if ($failFast && $row['hard_failed'] && str_starts_with($entry['scenario'], 'compliance-')) {
                    break; // stop at the first compliance breach
                }
            }

            $report->addSafetyCheck($this->determinismCheck($entries, $cap, $determinismSample));
        } finally {
            config(['matching.v2_enabled' => $flagBefore]); // ALWAYS restore
        }

        $report->addSafetyCheck($this->readOnlyCheck($before, $this->rowCounts()));
        $report->addSafetyCheck($this->flagRestoredCheck($flagBefore));

        return $report;
    }

    // ---- scenario execution -------------------------------------------------

    private function runScenario(array $entry, ?int $cap): array
    {
        if ($entry['scenario'] === 'truncation') {
            return $this->runTruncationScenario($entry);
        }

        $result = $this->matching->matchForSubject($entry['listing_type'], $entry['listing_id'], $cap);

        return $this->scenarioRow($entry, $result, $this->checksFor($entry['scenario'], $result));
    }

    private function runTruncationScenario(array $entry): array
    {
        $type = $entry['listing_type'];
        $id   = $entry['listing_id'];

        $low  = $this->matching->matchForSubject($type, $id, 1);
        $high = $this->matching->matchForSubject($type, $id, 100000);

        $checks = [
            $this->check('truncation-cap-respected', 'hard', $low->candidatesConsidered() <= 1,
                'low-cap considered=' . $low->candidatesConsidered() . ' (must be ≤ 1)'),
            $this->check('truncation-flag-honest', 'hard',
                $high->candidatesConsidered() <= 1 || $low->candidatePoolTruncated() === true,
                'high considered=' . $high->candidatesConsidered() . ' low truncated=' . ($low->candidatePoolTruncated() ? 'true' : 'false')),
        ];

        return $this->scenarioRow($entry, $high, $checks, [
            'low_cap'  => $low->toArray(),
            'high_cap' => $high->toArray(),
        ]);
    }

    /** @return array<int,array{name:string,severity:string,pass:bool,detail:string}> */
    private function checksFor(string $scenario, OrchestratedMatchResult $result): array
    {
        $checks = [$this->typeCorrectnessCheck($result)];

        switch ($scenario) {
            case 'compliance-seeker':
                $checks[] = $this->seekerComplianceCheck($result);
                break;
            case 'compliance-listing':
                $checks[] = $this->listingComplianceCheck($result);
                break;
            case 'mixed-pool':
                $checks[] = $this->mixedPoolAdvisory($result);
                break;
            case 'no-dna':
                $checks[] = $this->noDnaCheck($result);
                break;
            case 'low-dna':
                $checks[] = $this->lowDnaAdvisory($result);
                break;
            case 'confidence':
                $checks[] = $this->confidenceAdvisory($result);
                break;
        }

        return $checks;
    }

    // ---- individual checks --------------------------------------------------

    /** HARD: every match sits on the direction's expected counterpart side. */
    private function typeCorrectnessCheck(OrchestratedMatchResult $result): array
    {
        $expected = match ($result->direction()) {
            MatchDirection::DemandToListings => ['seller_agent', 'landlord_agent'],
            MatchDirection::ListingToDemands => ['buyer_agent', 'tenant_agent'],
            default                          => [],
        };

        $bad = [];
        foreach ($result->matches() as $m) {
            if (! in_array($m['listing_type'], $expected, true)) {
                $bad[] = ($m['listing_type'] ?? 'null') . ':' . $m['listing_id'];
            }
        }

        return $this->check('type-correctness', 'hard', $bad === [],
            $bad === [] ? 'all matches on the expected counterpart side' : 'wrong-side: ' . implode(',', $bad));
    }

    /** HARD: a non-eligible seeker must receive ZERO senior-restricted listings. */
    private function seekerComplianceCheck(OrchestratedMatchResult $result): array
    {
        $offenders = $this->seniorOffenders($result->matches());

        return $this->check('compliance-no-senior-leak', 'hard', $offenders === [],
            $offenders === [] ? 'no senior-restricted listing returned to a non-eligible seeker' : 'LEAK: ' . implode(',', $this->fmt($offenders)));
    }

    /** HARD: a senior-restricted listing must surface ZERO non-eligible seekers. */
    private function listingComplianceCheck(OrchestratedMatchResult $result): array
    {
        $offenders = $this->nonEligibleOffenders($result->matches());

        return $this->check('compliance-no-ineligible-leak', 'hard', $offenders === [],
            $offenders === [] ? 'no non-eligible seeker surfaced for a senior-restricted listing' : 'LEAK: ' . implode(',', $this->fmt($offenders)));
    }

    /** ADVISORY: does a mixed pool actually span both property types? */
    private function mixedPoolAdvisory(OrchestratedMatchResult $result): array
    {
        $types = array_values(array_unique(array_map(static fn ($m) => $m['listing_type'], $result->matches())));

        return $this->check('mixed-pool-spread', 'advisory', true,
            'types present: ' . (implode(',', array_filter($types)) ?: 'none') . (count($types) > 1 ? ' (both)' : ''));
    }

    /** HARD: a subject with no scores yields zero determined matches (no crash). */
    private function noDnaCheck(OrchestratedMatchResult $result): array
    {
        return $this->check('no-dna-degrades', 'hard', $result->determinedCount() === 0,
            'determined=' . $result->determinedCount() . ' undetermined=' . $result->undeterminedCount());
    }

    /** ADVISORY: sparse subjects should not fabricate high-tier confident matches. */
    private function lowDnaAdvisory(OrchestratedMatchResult $result): array
    {
        return $this->check('low-dna-graceful', 'advisory', true,
            'tiers=' . json_encode($result->tierCounts()) . ' undetermined=' . $result->undeterminedCount());
    }

    /** ADVISORY: no Exact/Strong tier should rest on zero coverage (over-confidence). */
    private function confidenceAdvisory(OrchestratedMatchResult $result): array
    {
        $overconfident = [];
        foreach ($result->rankedMatches() as $m) {
            if (in_array($m['tier'] ?? null, ['exact', 'strong'], true) && (int) ($m['coverage'] ?? 0) === 0) {
                $overconfident[] = ($m['counterpart_type'] ?? 'null') . ':' . $m['counterpart_id'];
            }
        }

        return $this->check('confidence-tracks-coverage', 'advisory', $overconfident === [],
            $overconfident === [] ? 'high tiers backed by real coverage' : 'over-confident: ' . implode(',', $overconfident));
    }

    // ---- compliance evaluators (public: proven to have teeth in tests) -------

    /**
     * Matched listings that are senior-restricted (age55 === true). Resolves the
     * SAME attribute profile the discovery gate uses, so a green result here means
     * the gate genuinely excluded senior stock — and a crafted senior tuple makes
     * this return non-empty (the "teeth" the test asserts).
     *
     * @param array<int,array{listing_type:?string,listing_id:int|string}> $matches
     * @return array<int,array{listing_type:string,listing_id:int}>
     */
    public function seniorOffenders(array $matches): array
    {
        return $this->offenders($matches, 'property', true);
    }

    /**
     * Matched seekers that are explicitly NOT 55+ eligible (age55 === false).
     *
     * @param array<int,array{listing_type:?string,listing_id:int|string}> $matches
     * @return array<int,array{listing_type:string,listing_id:int}>
     */
    public function nonEligibleOffenders(array $matches): array
    {
        return $this->offenders($matches, 'demand', false);
    }

    /**
     * @param array<int,array{listing_type:?string,listing_id:int|string}> $matches
     * @return array<int,array{listing_type:string,listing_id:int}>
     */
    private function offenders(array $matches, string $side, bool $flaggedValue): array
    {
        $tuples = $this->tuples($matches);
        if ($tuples === []) {
            return [];
        }

        $profiles = $this->attributes->resolveMany($side, $tuples);
        $out = [];
        foreach ($tuples as $t) {
            $p = $profiles[$t['listing_type'] . ':' . $t['listing_id']] ?? null;
            if ($p !== null && $p->age55 === $flaggedValue) {
                $out[] = $t;
            }
        }

        return $out;
    }

    // ---- safety checks ------------------------------------------------------

    private function determinismCheck(array $entries, ?int $cap, int $sample): array
    {
        $subjects = [];
        foreach ($entries as $e) {
            if ($e['scenario'] === 'truncation') {
                continue;
            }
            $subjects[] = $e;
            if (count($subjects) >= max(1, $sample)) {
                break;
            }
        }

        $mismatches = [];
        foreach ($subjects as $e) {
            $a = $this->matching->matchForSubject($e['listing_type'], $e['listing_id'], $cap)->toArray();
            $b = $this->matching->matchForSubject($e['listing_type'], $e['listing_id'], $cap)->toArray();
            if (json_encode($a) !== json_encode($b)) {
                $mismatches[] = $e['listing_type'] . ':' . $e['listing_id'];
            }
        }

        return $this->check('determinism', 'hard', $mismatches === [],
            $mismatches === [] ? 'identical across ' . count($subjects) . ' double-run subject(s)' : 'non-deterministic: ' . implode(',', $mismatches));
    }

    private function readOnlyCheck(array $before, array $after): array
    {
        $changed = [];
        foreach ($before as $table => $count) {
            if (($after[$table] ?? null) !== $count) {
                $changed[] = $table . ' ' . $count . '→' . ($after[$table] ?? 'missing');
            }
        }

        return $this->check('read-only', 'hard', $changed === [],
            $changed === [] ? 'all product-table row counts unchanged' : 'MUTATED: ' . implode(', ', $changed));
    }

    private function flagRestoredCheck(bool $flagBefore): array
    {
        $now = (bool) config('matching.v2_enabled', false);

        return $this->check('flag-restored', 'hard', $now === $flagBefore,
            'matching.v2_enabled restored to ' . ($flagBefore ? 'true' : 'false'));
    }

    /** @return array<string,int> */
    private function rowCounts(): array
    {
        $counts = [];
        foreach (self::READ_ONLY_TABLES as $table) {
            $counts[$table] = (int) DB::table($table)->count();
        }

        return $counts;
    }

    // ---- helpers ------------------------------------------------------------

    private function scenarioRow(array $entry, OrchestratedMatchResult $result, array $checks, array $extra = []): array
    {
        return [
            'scenario'     => $entry['scenario'],
            'note'         => $entry['note'] ?? '',
            'subject_type' => $result->subjectType(),
            'subject_id'   => $result->subjectId(),
            'direction'    => $result->direction()?->name,
            'considered'   => $result->candidatesConsidered(),
            'determined'   => $result->determinedCount(),
            'undetermined' => $result->undeterminedCount(),
            'tier_counts'  => $result->tierCounts(),
            'truncated'    => $result->candidatePoolTruncated(),
            'checks'       => $checks,
            'hard_failed'  => $this->anyHardFailed($checks),
            'result'       => $result->toArray() + ['ranked_matches' => $result->rankedMatches()] + $extra,
        ];
    }

    private function anyHardFailed(array $checks): bool
    {
        foreach ($checks as $c) {
            if ($c['severity'] === 'hard' && $c['pass'] === false) {
                return true;
            }
        }

        return false;
    }

    /** @return array{name:string,severity:string,pass:bool,detail:string} */
    private function check(string $name, string $severity, bool $pass, string $detail): array
    {
        return ['name' => $name, 'severity' => $severity, 'pass' => $pass, 'detail' => $detail];
    }

    /**
     * @param array<int,array{listing_type:?string,listing_id:int|string}> $matches
     * @return array<int,array{listing_type:string,listing_id:int}>
     */
    private function tuples(array $matches): array
    {
        $out = [];
        foreach ($matches as $m) {
            if ($m['listing_type'] === null) {
                continue;
            }
            $out[] = ['listing_type' => (string) $m['listing_type'], 'listing_id' => (int) $m['listing_id']];
        }

        return $out;
    }

    /**
     * @param array<int,array{listing_type:string,listing_id:int}> $tuples
     * @return array<int,string>
     */
    private function fmt(array $tuples): array
    {
        return array_map(static fn ($t) => $t['listing_type'] . ':' . $t['listing_id'], $tuples);
    }
}
