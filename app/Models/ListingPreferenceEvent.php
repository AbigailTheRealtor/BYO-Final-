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
 * THE THREE SHAPES A ROW MAY TAKE (Phase 2):
 *
 *   from_state = null,            to_state = save|maybe|pass  first preference
 *   from_state = save|maybe|pass, to_state = save|maybe|pass  state change
 *   from_state = save|maybe|pass, to_state = null             CLEARED
 *
 * A NULL `to_state` means exactly one thing: no current preference after this
 * transition. It is never a fourth state and never a synonym for `pass` — "I
 * passed on this house" and "I withdrew my opinion" are different facts.
 *
 * `to_state` carries NO cast, deliberately: a cast to string would turn the
 * null into '' and quietly destroy that distinction on the way in or out. It is
 * written as a nullable string and read back as null or one of the three
 * values. `wasCleared()` is the one reading of that, so no caller has to
 * remember which comparison is the safe one.
 */
class ListingPreferenceEvent extends Model
{
    public const SURFACE_RESULTS       = 'results';
    public const SURFACE_DETAIL        = 'detail';
    public const SURFACE_EXPLORE       = 'explore';
    public const SURFACE_VIRTUAL_DRIVE = 'virtual_drive';

    /** Phase 3B: the customer's own Saved / Maybe / Passed management area. */
    public const SURFACE_ACCOUNT       = 'account';

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

    /**
     * Did this transition leave the customer with no current preference?
     *
     * The one reading of a null `to_state`, so no caller invents its own
     * comparison — `=== null` and `=== ''` would disagree the moment a cast or
     * a form submission turned one into the other.
     */
    public function wasCleared(): bool
    {
        return $this->to_state === null;
    }

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
