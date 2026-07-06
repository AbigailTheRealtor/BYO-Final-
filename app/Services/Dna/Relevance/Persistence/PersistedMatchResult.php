<?php

namespace App\Services\Dna\Relevance\Persistence;

use App\Models\Matching\MatchRun;

/**
 * PersistedMatchResult — Matching V2 C7: the immutable read model returned by
 * PersistedMatchReader.
 *
 * Mirrors the honest shape of OrchestratedMatchResult::toArray() (C6) so a future
 * consumer reads persisted results with the same contract as the live engine —
 * counts, tier histogram, discovery metadata, and best-first matches each
 * carrying listing_type + listing_id. Reconstructed from a MatchRun and its
 * ordered children. Exposes nothing by itself; the reader is unwired.
 *
 * @see docs/matching-v2-c7-persistence-scope.md §7
 */
final class PersistedMatchResult
{
    /**
     * @param array<int,array{counterpart_type:?string,counterpart_id:int,position:int,tier:string,value:?int}> $matches
     * @param array<string,int> $tierCounts
     */
    public function __construct(
        private readonly string $subjectType,
        private readonly int $subjectId,
        private readonly ?string $direction,
        private readonly string $version,
        private readonly int $determinedCount,
        private readonly int $undeterminedCount,
        private readonly int $candidatesConsidered,
        private readonly bool $candidatePoolTruncated,
        private readonly array $tierCounts,
        private readonly array $matches,
    ) {
    }

    /**
     * Reconstruct from a persisted run + its (already position-ordered) children.
     */
    public static function fromRun(MatchRun $run): self
    {
        $matches = $run->matches->map(static fn ($m): array => [
            'counterpart_type' => $m->counterpart_type,
            'counterpart_id'   => (int) $m->counterpart_id,
            'position'         => (int) $m->position,
            'tier'             => (string) $m->tier,
            'value'            => $m->value !== null ? (int) $m->value : null,
        ])->all();

        return new self(
            (string) $run->subject_type,
            (int) $run->subject_id,
            $run->direction,
            (string) $run->version,
            (int) $run->determined_count,
            (int) $run->undetermined_count,
            (int) $run->candidates_considered,
            (bool) $run->candidate_pool_truncated,
            is_array($run->tier_counts) ? $run->tier_counts : [],
            $matches,
        );
    }

    public function subjectType(): string
    {
        return $this->subjectType;
    }

    public function subjectId(): int
    {
        return $this->subjectId;
    }

    public function direction(): ?string
    {
        return $this->direction;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function determinedCount(): int
    {
        return $this->determinedCount;
    }

    public function undeterminedCount(): int
    {
        return $this->undeterminedCount;
    }

    public function candidatesConsidered(): int
    {
        return $this->candidatesConsidered;
    }

    public function candidatePoolTruncated(): bool
    {
        return $this->candidatePoolTruncated;
    }

    /** @return array<string,int> */
    public function tierCounts(): array
    {
        return $this->tierCounts;
    }

    public function isEmpty(): bool
    {
        return $this->determinedCount === 0;
    }

    /**
     * @return array<int,array{counterpart_type:?string,counterpart_id:int,position:int,tier:string,value:?int}>
     */
    public function matches(): array
    {
        return $this->matches;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'subject_type'             => $this->subjectType,
            'subject_id'               => $this->subjectId,
            'direction'                => $this->direction,
            'version'                  => $this->version,
            'determined_count'         => $this->determinedCount,
            'undetermined_count'       => $this->undeterminedCount,
            'candidates_considered'    => $this->candidatesConsidered,
            'candidate_pool_truncated' => $this->candidatePoolTruncated,
            'tier_counts'              => $this->tierCounts,
            'matches'                  => $this->matches,
        ];
    }
}
