<?php

namespace App\Services\Dna\Relevance;

/**
 * CandidateSet — Matching V2 consumption slice 2 (Candidate Discovery).
 *
 * The immutable result of candidate discovery: a bounded list of
 * ['listing_type' => string, 'listing_id' => int] tuples in the exact shape that
 * DnaMatchService::matchListingAgainstDemands() / matchDemandAgainstListings()
 * consume, plus whether the underlying pool was truncated by the cap.
 *
 * `toArray()` is intentionally the same tuple shape as the $candidates argument
 * of DnaMatchService, so discovery output feeds the slice-1 matcher directly.
 */
final class CandidateSet
{
    /**
     * @param array<int,array{listing_type:string,listing_id:int}> $candidates
     * @param bool $truncated True when the discoverable pool exceeded the cap and was trimmed.
     */
    public function __construct(
        private readonly array $candidates,
        private readonly bool $truncated,
    ) {
    }

    /** The empty result — used when Matching V2 is disabled (no DB reads occurred). */
    public static function empty(): self
    {
        return new self([], false);
    }

    /**
     * @return array<int,array{listing_type:string,listing_id:int}>
     */
    public function toArray(): array
    {
        return $this->candidates;
    }

    public function total(): int
    {
        return count($this->candidates);
    }

    public function isEmpty(): bool
    {
        return $this->candidates === [];
    }

    /**
     * True when the discoverable pool was larger than the cap and the returned
     * set is only the capped slice — callers must not read this as "the whole market".
     */
    public function wasTruncated(): bool
    {
        return $this->truncated;
    }
}
