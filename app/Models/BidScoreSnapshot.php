<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable score snapshot captured at each bid lifecycle event.
 * Rows are never updated after insertion.
 */
class BidScoreSnapshot extends Model
{
    public $timestamps = false;

    protected $table = 'bid_score_snapshots';

    protected $fillable = [
        'bid_type',
        'bid_id',
        'role',
        'property_type',
        'event_type',
        'readiness_state',
        'score_type',
        'score_value',
        'scoring_version',
        'guard_key',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];
}
