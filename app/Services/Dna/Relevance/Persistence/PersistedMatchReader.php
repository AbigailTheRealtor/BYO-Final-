<?php

namespace App\Services\Dna\Relevance\Persistence;

use App\Models\Matching\MatchRun;

/**
 * PersistedMatchReader — Matching V2 C7: the internal reader (OD-7).
 *
 * Reads a materialized run back into a PersistedMatchResult. SHIPPED NOW BUT
 * DELIBERATELY UNWIRED — nothing in a request/consumer path calls it. It exists
 * so the persistence round-trip is proven and a future, separately-approved
 * exposure slice has a stable read contract.
 *
 * READ-TIME RE-GATE (OD-3) — returns null (absent) whenever:
 *   1. config('matching.v2_enabled') is false — a disabled engine hides all
 *      persisted rows, so stale data can never leak while V2 is off; and
 *   2. the persisted `version` != config('matching.persistence.version') — a
 *      version bump invalidates older rows at read time without deleting them.
 * This replaces event-driven invalidation, which is a deferred fast-follow.
 *
 * @see docs/matching-v2-c7-persistence-scope.md §3, §5
 */
class PersistedMatchReader
{
    /** The engine master gate must be on for any persisted result to be visible. */
    public function isReadable(): bool
    {
        return (bool) config('matching.v2_enabled', false);
    }

    public function version(): string
    {
        return (string) config('matching.persistence.version', 'c7-v1');
    }

    /**
     * Read the current-version materialized result for a subject, or null when
     * re-gated (V2 off, wrong/missing version, or never materialized).
     */
    public function read(string $subjectType, int $subjectId): ?PersistedMatchResult
    {
        if (! $this->isReadable()) {
            return null;
        }

        $run = MatchRun::with('matches')
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('version', $this->version())
            ->first();

        if ($run === null) {
            return null;
        }

        return PersistedMatchResult::fromRun($run);
    }
}
