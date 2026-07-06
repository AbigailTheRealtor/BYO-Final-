<?php

namespace App\Models\Matching;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PersistedMatch — Matching V2 C7: one ranked counterpart within a MatchRun.
 *
 * Preserves counterpart_type + counterpart_id (ids collide across listing_types)
 * and the ranking position/tier. value/confidence/coverage are internal-fidelity
 * only and never displayed. Written wholesale with its parent run; never updated
 * in place.
 *
 * @see docs/matching-v2-c7-persistence-scope.md §4.2
 */
class PersistedMatch extends Model
{
    protected $table = 'matching_v2_matches';

    protected $fillable = [
        'match_run_id',
        'subject_type',
        'subject_id',
        'counterpart_type',
        'counterpart_id',
        'position',
        'tier',
        'value',
        'confidence',
        'coverage',
    ];

    protected $casts = [
        'match_run_id'   => 'integer',
        'subject_id'     => 'integer',
        'counterpart_id' => 'integer',
        'position'       => 'integer',
        'value'          => 'integer',
        'confidence'     => 'integer',
        'coverage'       => 'integer',
    ];

    /**
     * @return BelongsTo<MatchRun, PersistedMatch>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(MatchRun::class, 'match_run_id');
    }
}
