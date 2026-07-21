<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3a (PAD-US boundary import authoring).
 *
 * The outcome of normalizing a batch of raw boundary rows into BoundaryRecords. Mirrors
 * {@see AuthorityOverlayNormalizationResult} (C2) / {@see NormalizationResult} (2A/2C): every input
 * row lands in exactly one bucket — kept + rejected_invalid_geometry + rejected_invalid_field ==
 * total_input — and rejects are COUNTED with a reason tally, never silently lost. Duplicates are NOT
 * dropped here; the acceptance gate ({@see BoundaryImportAcceptance}) catches them (hard-fail).
 */
final class BoundaryNormalizationResult
{
    /**
     * @param BoundaryRecord[]  $records         kept, normalized rows (input order preserved)
     * @param array<string,int> $rejectReasons   reason token => count (diagnostics)
     */
    public function __construct(
        public readonly array $records,
        public readonly int $totalInput,
        public readonly int $rejectedInvalidGeometry,
        public readonly int $rejectedInvalidField,
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
            + $this->rejectedInvalidGeometry
            + $this->rejectedInvalidField;
    }

    /** @return array<string,int|array<string,int>> */
    public function summary(): array
    {
        return [
            'total_input'               => $this->totalInput,
            'kept'                      => $this->keptCount(),
            'rejected_invalid_geometry' => $this->rejectedInvalidGeometry,
            'rejected_invalid_field'    => $this->rejectedInvalidField,
            'reject_reasons'            => $this->rejectReasons,
        ];
    }
}
