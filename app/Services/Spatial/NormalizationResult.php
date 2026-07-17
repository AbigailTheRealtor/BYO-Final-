<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2A (B2).
 *
 * The outcome of normalizing a batch of raw Overture rows. Every input row is
 * accounted for exactly once: kept + rejected_* == total_input. Rejections are
 * COUNTED and tallied, never silently dropped (SIP data-honesty invariant).
 */
final class NormalizationResult
{
    /**
     * @param NormalizedPlaceRecord[]     $records            kept, normalized rows
     * @param array<string,int>           $unmappedTally      unmapped primary category token => count
     */
    public function __construct(
        public readonly array $records,
        public readonly int $totalInput,
        public readonly int $rejectedUnmapped,
        public readonly int $rejectedLowConfidence,
        public readonly int $rejectedInvalid,
        public readonly array $unmappedTally,
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
            + $this->rejectedUnmapped
            + $this->rejectedLowConfidence
            + $this->rejectedInvalid;
    }

    /** @return array<string,int|array<string,int>> */
    public function summary(): array
    {
        return [
            'total_input'             => $this->totalInput,
            'kept'                    => $this->keptCount(),
            'rejected_unmapped'       => $this->rejectedUnmapped,
            'rejected_low_confidence' => $this->rejectedLowConfidence,
            'rejected_invalid'        => $this->rejectedInvalid,
            'unmapped_categories'     => $this->unmappedTally,
        ];
    }
}
