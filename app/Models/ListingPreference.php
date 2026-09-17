<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The ONE current Save / Maybe / Pass for a customer × seeker role × subject.
 *
 * Unique per (user_id, seeker_role, subject_key). No row means the customer has
 * expressed nothing — which is deliberately distinct from Pass.
 *
 * Nothing writes to this model in Phase 1.
 */
class ListingPreference extends Model
{
    protected $table = 'listing_preferences';

    protected $fillable = [
        'user_id',
        'seeker_role',
        'listing_type',
        'listing_id',
        'subject_key',
        'state',
        'state_set_at',
    ];

    protected $casts = [
        'user_id'      => 'integer',
        'listing_id'   => 'integer',
        'state_set_at' => 'datetime',
    ];

    public function reasons(): HasMany
    {
        return $this->hasMany(ListingPreferenceReason::class, 'listing_preference_id');
    }
}
