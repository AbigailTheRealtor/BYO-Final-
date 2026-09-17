<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only record of one Save / Maybe / Pass transition.
 *
 * Same guarantee as SmartTagManualEvent and PropertyLocationDnaAudit — the model
 * refuses updates and deletes. This is the timeline that recency weighting,
 * decay, undo and Fair Housing auditability all read; a mutable history answers
 * none of those questions honestly.
 *
 * Nothing writes to this model in Phase 1.
 */
class ListingPreferenceEvent extends Model
{
    public const SURFACE_RESULTS       = 'results';
    public const SURFACE_DETAIL        = 'detail';
    public const SURFACE_EXPLORE       = 'explore';
    public const SURFACE_VIRTUAL_DRIVE = 'virtual_drive';

    public $timestamps = false;

    protected $table = 'listing_preference_events';

    protected $fillable = [
        'user_id',
        'seeker_role',
        'listing_type',
        'listing_id',
        'subject_key',
        'from_state',
        'to_state',
        'reasons_json',
        'surface',
        'created_at',
    ];

    protected $casts = [
        'user_id'      => 'integer',
        'listing_id'   => 'integer',
        'reasons_json' => 'array',
        'created_at'   => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new LogicException('ListingPreferenceEvent is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('ListingPreferenceEvent is append-only and cannot be deleted.');
        });
    }
}
