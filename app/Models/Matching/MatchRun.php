<?php

namespace App\Models\Matching;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MatchRun — Matching V2 C7: the persisted summary of one materialized match
 * result for a subject at a given version.
 *
 * Addressed by (subject_type, subject_id, version). Owns its ranked counterparts
 * via matching_v2_matches (hasMany, cascade-deleted with the run). Written ONLY
 * by MatchResultPersister in staging/dev; a pure persistence record that exposes
 * nothing to consumers by itself.
 *
 * @see docs/matching-v2-c7-persistence-scope.md §4.1
 */
class MatchRun extends Model
{
    protected $table = 'matching_v2_match_runs';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'direction',
        'version',
        'determined_count',
        'undetermined_count',
        'candidates_considered',
        'candidate_pool_truncated',
        'tier_counts',
        'computed_at',
    ];

    protected $casts = [
        'subject_id'               => 'integer',
        'determined_count'         => 'integer',
        'undetermined_count'       => 'integer',
        'candidates_considered'    => 'integer',
        'candidate_pool_truncated' => 'boolean',
        'tier_counts'              => 'array',
        'computed_at'              => 'datetime',
    ];

    /**
     * @return HasMany<PersistedMatch>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(PersistedMatch::class, 'match_run_id')->orderBy('position');
    }
}
