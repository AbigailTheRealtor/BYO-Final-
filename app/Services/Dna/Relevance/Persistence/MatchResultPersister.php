<?php

namespace App\Services\Dna\Relevance\Persistence;

use App\Models\Matching\MatchRun;
use App\Models\Matching\PersistedMatch;
use App\Services\Dna\Relevance\OrchestratedMatchResult;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;

/**
 * MatchResultPersister — Matching V2 C7: the gated, production-refusing writer.
 *
 * Materializes an in-memory OrchestratedMatchResult (C6) into the additive
 * matching_v2_match_runs (summary) + matching_v2_matches (children) tables.
 *
 * GOVERNANCE — a write happens ONLY when ALL THREE hold (any false ⇒ inert, no
 * DB write, returns null):
 *   1. config('matching.v2_enabled')            — the engine master gate.
 *   2. config('matching.persistence.enabled')   — the C7 persistence gate.
 *   3. NOT app()->environment('production')      — hard staging/dev-only refusal.
 * The production refusal is intentionally NOT overridable by config, so a
 * mis-set flag in production still cannot write.
 *
 * Idempotent: upserts the summary on (subject_type, subject_id, version) and
 * rewrites its children wholesale inside one transaction. A zero-determined
 * subject still gets exactly one empty summary row and no children (OD-6).
 *
 * @see docs/matching-v2-c7-persistence-scope.md §3, §7
 */
class MatchResultPersister
{
    public function __construct(private readonly Application $app)
    {
    }

    /** All three write gates must pass. */
    public function canPersist(): bool
    {
        return (bool) config('matching.v2_enabled', false)
            && (bool) config('matching.persistence.enabled', false)
            && ! $this->app->environment('production');
    }

    public function version(): string
    {
        return (string) config('matching.persistence.version', 'c7-v1');
    }

    /**
     * Persist one subject's result. Returns the summary MatchRun, or null when
     * inert (a gate is closed) — never throws for a closed gate.
     */
    public function persist(OrchestratedMatchResult $result): ?MatchRun
    {
        if (! $this->canPersist()) {
            return null;
        }

        $version = $this->version();

        return DB::transaction(function () use ($result, $version): MatchRun {
            $run = MatchRun::updateOrCreate(
                [
                    'subject_type' => $result->subjectType(),
                    'subject_id'   => $result->subjectId(),
                    'version'      => $version,
                ],
                [
                    'direction'                => $result->direction()?->name,
                    'determined_count'         => $result->determinedCount(),
                    'undetermined_count'       => $result->undeterminedCount(),
                    'candidates_considered'    => $result->candidatesConsidered(),
                    'candidate_pool_truncated' => $result->candidatePoolTruncated(),
                    'tier_counts'              => $result->tierCounts(),
                    'computed_at'              => now(),
                ]
            );

            // Rewrite children wholesale — no partial child updates.
            PersistedMatch::where('match_run_id', $run->id)->delete();

            $position = 0;
            foreach ($result->rankedMatches() as $match) {
                PersistedMatch::create([
                    'match_run_id'     => $run->id,
                    'subject_type'     => $result->subjectType(),
                    'subject_id'       => $result->subjectId(),
                    'counterpart_type' => $match['counterpart_type'] ?? null,
                    'counterpart_id'   => (int) $match['counterpart_id'],
                    'position'         => $position++,
                    'tier'             => (string) $match['tier'],
                    'value'            => $match['value'] ?? null,
                    'confidence'       => $match['confidence'] ?? null,
                    'coverage'         => $match['coverage'] ?? null,
                ]);
            }

            return $run;
        });
    }
}
