<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks when a bid first entered each funnel stage.
 * One row per bid. Timestamps are set exactly once — re-entry never overwrites.
 */
class BidFunnelTimestamp extends Model
{
    protected $table = 'bid_funnel_timestamps';

    protected $fillable = [
        'bid_type',
        'bid_id',
        'role',
        'not_ready_at',
        'quick_match_ready_at',
        'full_match_ready_at',
        'bid_submitted_at',
        'bid_accepted_at',
        'agent_hired_at',
    ];

    protected $casts = [
        'not_ready_at'          => 'datetime',
        'quick_match_ready_at'  => 'datetime',
        'full_match_ready_at'   => 'datetime',
        'bid_submitted_at'      => 'datetime',
        'bid_accepted_at'       => 'datetime',
        'agent_hired_at'        => 'datetime',
    ];
}
