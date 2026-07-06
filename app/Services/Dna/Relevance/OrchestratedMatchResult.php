<?php

namespace App\Services\Dna\Relevance;

/**
 * OrchestratedMatchResult — Matching V2 C6.
 *
 * The immutable output of MatchingV2Service: the ranked, tiered, compliance-gated
 * matches for one subject, each preserving BOTH listing_type and listing_id, plus
 * the discovery metadata a caller needs to read the result honestly (how many
 * candidates were considered and whether the candidate pool was capped).
 *
 * Persists nothing; exposes nothing to consumers by itself. `toArray()` is a
 * stable machine shape used by the inspection command's --json and (later) by a
 * persistence slice.
 */
final class OrchestratedMatchResult
{
    public function __construct(
        private readonly string $subjectType,
        private readonly int $subjectId,
        private readonly ?MatchDirection $direction,
        private readonly RankedMatchSet $ranked,
        private readonly int $candidatesConsidered,
        private readonly bool $candidatePoolTruncated,
    ) {
    }

    /** The inert result — no candidates, no matches. */
    public static function empty(string $subjectType, int $subjectId, ?MatchDirection $direction): self
    {
        return new self($subjectType, $subjectId, $direction, new RankedMatchSet([], 0), 0, false);
    }

    public function subjectType(): string
    {
        return $this->subjectType;
    }

    public function subjectId(): int
    {
        return $this->subjectId;
    }

    public function direction(): ?MatchDirection
    {
        return $this->direction;
    }

    /**
     * Best-first matches, each carrying its listing_type + listing_id.
     *
     * @return array<int,array{listing_type:?string,listing_id:int|string,tier:string,value:?int}>
     */
    public function matches(): array
    {
        return array_map(static fn (RankedMatch $m): array => [
            'listing_type' => $m->counterpartType(),
            'listing_id'   => $m->counterpartId,
            'tier'         => $m->tier()->value,
            'value'        => $m->value(),
        ], $this->ranked->matches);
    }

    /**
     * @return array<int,array{listing_type:?string,listing_id:int|string,tier:string,value:?int}>
     */
    public function top(int $n): array
    {
        return array_slice($this->matches(), 0, max(0, $n));
    }

    public function determinedCount(): int
    {
        return $this->ranked->determinedCount();
    }

    public function undeterminedCount(): int
    {
        return $this->ranked->undeterminedCount;
    }

    /** @return array<string,int> privacy-safe, zero-filled 4 tiers. */
    public function tierCounts(): array
    {
        return $this->ranked->tierCounts();
    }

    public function candidatesConsidered(): int
    {
        return $this->candidatesConsidered;
    }

    public function candidatePoolTruncated(): bool
    {
        return $this->candidatePoolTruncated;
    }

    /** True when there are no determined (ranked) matches. */
    public function isEmpty(): bool
    {
        return $this->ranked->determinedCount() === 0;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'subject_type'             => $this->subjectType,
            'subject_id'               => $this->subjectId,
            'direction'                => $this->direction?->name,
            'candidates_considered'    => $this->candidatesConsidered,
            'candidate_pool_truncated' => $this->candidatePoolTruncated,
            'determined_count'         => $this->determinedCount(),
            'undetermined_count'       => $this->undeterminedCount(),
            'tier_counts'              => $this->tierCounts(),
            'matches'                  => $this->matches(),
        ];
    }
}
