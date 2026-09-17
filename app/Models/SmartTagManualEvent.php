<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only record of an owner's manual Smart Tag change: who, what, when.
 *
 * Same guarantee as PropertyLocationDnaAudit — the model refuses updates and
 * deletes. The purger deliberately leaves these rows in place.
 */
class SmartTagManualEvent extends Model
{
    public const ACTION_SELECTED                     = 'selected';
    public const ACTION_DESELECTED                   = 'deselected';
    public const ACTION_PRUNED_NOT_APPLICABLE        = 'pruned_not_applicable';
    public const ACTION_PRUNED_ANSWERED_BY_STRUCTURE = 'pruned_answered_by_property_details';

    public $timestamps = false;

    protected $table = 'smart_tag_manual_events';

    protected $fillable = [
        'listing_type',
        'listing_id',
        'tag_key',
        'action',
        'actor_user_id',
        'actor_role',
        'created_at',
    ];

    protected $casts = [
        'listing_id'    => 'integer',
        'actor_user_id' => 'integer',
        'created_at'    => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new LogicException('SmartTagManualEvent is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('SmartTagManualEvent is append-only and cannot be deleted.');
        });
    }
}
