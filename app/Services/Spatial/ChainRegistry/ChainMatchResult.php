<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * The matcher's answer for one row. Deterministic: memberships sorted by brand key, candidate
 * rejections key-sorted, diagnostics sorted — so the same row and the same registry always
 * produce an identical value, whatever order either was written in.
 *
 * Outcomes:
 *   matched    one or more memberships
 *   rejected   a row-level refusal (R0–R2); no chain was even considered
 *   no_match   no chain had identity evidence (`no_chain`), or every candidate was dropped
 *              (`all_candidates_rejected`, with a reason per candidate)
 *   ambiguous  more than one candidate survived and they are not an evidenced co-brand
 *
 * Nothing here is a guess: the reason codes are {@see ChainMatchReason}, so the PR 3 census can
 * tally refusals by row and drops by candidate chain.
 */
final class ChainMatchResult
{
    public const MATCHED = 'matched';
    public const REJECTED = 'rejected';
    public const NO_MATCH = 'no_match';
    public const AMBIGUOUS = 'ambiguous';

    /**
     * @param list<ChainMembership>                                          $memberships
     * @param array<string, string>                                          $candidateRejections brand_key => reason
     * @param list<array{brand_key: string, code: string, fuel_brand: string}> $diagnostics
     * @param list<string>                                                   $ambiguousCandidates
     */
    private function __construct(
        public readonly string $outcome,
        public readonly ?string $reason,
        public readonly array $memberships,
        public readonly array $candidateRejections,
        public readonly array $diagnostics,
        public readonly array $ambiguousCandidates,
    ) {
    }

    public static function rejected(string $reason): self
    {
        return new self(self::REJECTED, $reason, [], [], [], []);
    }

    /**
     * @param list<ChainMembership> $memberships
     * @param array<string, string> $candidateRejections
     * @param list<array{brand_key: string, code: string, fuel_brand: string}> $diagnostics
     */
    public static function matched(array $memberships, array $candidateRejections, array $diagnostics): self
    {
        usort($memberships, static fn (ChainMembership $a, ChainMembership $b): int => strcmp($a->brandKey, $b->brandKey));
        ksort($candidateRejections, SORT_STRING);

        return new self(self::MATCHED, null, $memberships, $candidateRejections, self::sortDiagnostics($diagnostics), []);
    }

    /**
     * @param array<string, string> $candidateRejections
     * @param list<array{brand_key: string, code: string, fuel_brand: string}> $diagnostics
     */
    public static function noMatch(string $reason, array $candidateRejections, array $diagnostics): self
    {
        ksort($candidateRejections, SORT_STRING);

        return new self(self::NO_MATCH, $reason, [], $candidateRejections, self::sortDiagnostics($diagnostics), []);
    }

    /**
     * @param list<string>          $candidates
     * @param array<string, string> $candidateRejections
     * @param list<array{brand_key: string, code: string, fuel_brand: string}> $diagnostics
     */
    public static function ambiguous(array $candidates, array $candidateRejections, array $diagnostics): self
    {
        sort($candidates, SORT_STRING);
        ksort($candidateRejections, SORT_STRING);

        return new self(self::AMBIGUOUS, ChainMatchReason::AMBIGUOUS, [], $candidateRejections, self::sortDiagnostics($diagnostics), $candidates);
    }

    public function isMatch(): bool
    {
        return $this->outcome === self::MATCHED;
    }

    /** @return list<string> */
    public function brandKeys(): array
    {
        return array_map(static fn (ChainMembership $m): string => $m->brandKey, $this->memberships);
    }

    public function membership(string $brandKey): ?ChainMembership
    {
        foreach ($this->memberships as $m) {
            if ($m->brandKey === $brandKey) {
                return $m;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'reason' => $this->reason,
            'memberships' => array_map(static fn (ChainMembership $m): array => $m->toArray(), $this->memberships),
            'candidate_rejections' => $this->candidateRejections,
            'diagnostics' => $this->diagnostics,
            'ambiguous_candidates' => $this->ambiguousCandidates,
        ];
    }

    /**
     * @param list<array{brand_key: string, code: string, fuel_brand: string}> $diagnostics
     * @return list<array{brand_key: string, code: string, fuel_brand: string}>
     */
    private static function sortDiagnostics(array $diagnostics): array
    {
        $keyed = [];
        foreach ($diagnostics as $d) {
            $keyed[$d['brand_key'] . "\0" . $d['fuel_brand'] . "\0" . $d['code']] = $d;
        }
        ksort($keyed, SORT_STRING);

        return array_values($keyed);
    }
}
