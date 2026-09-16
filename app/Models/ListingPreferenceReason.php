<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One structured reason behind a customer's CURRENT state.
 *
 * Replaced wholesale when the state or the chips change; the immutable record
 * of what was chosen at a point in time is the event row, not this.
 *
 * `dimension` and `smart_tag_key` record what the chip meant WHEN IT WAS CHOSEN.
 * Live interpretation goes to ListingPreferenceReasonCatalog, never to these
 * columns.
 *
 * Nothing writes to this model in Phase 1.
 */
class ListingPreferenceReason extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'listing_preference_reasons';

    protected $fillable = [
        'listing_preference_id',
        'reason_key',
        'dimension',
        'smart_tag_key',
        'created_at',
    ];

    protected $casts = [
        'listing_preference_id' => 'integer',
        'created_at'            => 'datetime',
    ];

    public function preference(): BelongsTo
    {
        return $this->belongsTo(ListingPreference::class, 'listing_preference_id');
    }
}
