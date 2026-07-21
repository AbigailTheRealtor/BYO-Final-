<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C2 (authority-overlay importers).
 *
 * The outcome of normalizing a batch of raw authority-source rows into AuthorityRecords. Mirrors
 * {@see NormalizationResult} (Batch 2A/2C): every input row lands in exactly one bucket —
 * kept + rejected_invalid + rejected_out_of_domain == total_input — and rejects are COUNTED with a
 * reason tally, never silently lost (SIP data-honesty invariant). Duplicates are NOT dropped here;
 * the acceptance gate ({@see AuthorityOverlayAcceptance}) catches them, matching the 2C split.
 */
final class AuthorityOverlayNormalizationResult
{
    /**
     * @param AuthorityRecord[]   $records            kept, normalized rows (input order preserved)
     * @param array<string,int>   $rejectReasons      reason token => count (diagnostics)
     */
    public function __construct(
        public readonly array $records,
        public readonly int $totalInput,
        public readonly int $rejectedInvalid,
        public readonly int $rejectedOutOfDomain,
        public readonly array $rejectReasons,
    ) {
    }

    public function keptCount(): int
    {
        return count($this->records);
    }

    /** Every input row lands in exactly one bucket. */
    public function isFullyAccounted(): bool
    {
        return $this->totalInput
            === $this->keptCount()
            + $this->rejectedInvalid
            + $this->rejectedOutOfDomain;
    }

    /** @return array<string,int|array<string,int>> */
    public function summary(): array
    {
        return [
            'total_input'           => $this->totalInput,
            'kept'                  => $this->keptCount(),
            'rejected_invalid'      => $this->rejectedInvalid,
            'rejected_out_of_domain' => $this->rejectedOutOfDomain,
            'reject_reasons'        => $this->rejectReasons,
        ];
    }
}
