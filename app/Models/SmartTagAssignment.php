<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The ONE canonical Smart Tag answer for a listing × tag — what matching reads.
 *
 * Unique per (listing_type, listing_id, tag_key). No row means unknown.
 * Written only by SmartTagAssignmentProjector.
 */
class SmartTagAssignment extends Model
{
    protected $table = 'smart_tag_assignments';

    protected $fillable = [
        'listing_type',
        'listing_id',
        'tag_key',
        'context',
        'state',
        'winning_source',
        'has_conflict',
        'conflict_tags',
        'resolved_at',
    ];

    protected $casts = [
        'listing_id'    => 'integer',
        'has_conflict'  => 'boolean',
        'conflict_tags' => 'array',
        'resolved_at'   => 'datetime',
    ];
}
