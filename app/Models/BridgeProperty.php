<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BridgeProperty extends Model
{
    protected $table = 'bridge_properties';

    protected $fillable = [
        'listing_key',
        'listing_id',
        'standard_status',
        'property_type',
        'list_price',
        'unparsed_address',
        'city',
        'state_or_province',
        'postal_code',
        'bedrooms_total',
        'bathrooms_total_integer',
        'living_area',
        'modification_timestamp',
        'raw_json',
        'imported_at',

        // Phase 1 native column promotions (19 columns — 'furnished' excluded per Phase 0 Block verdict)
        'latitude',
        'longitude',
        'county_or_parish',
        'property_sub_type',
        'mls_status',
        'year_built',
        'association_fee',
        'tax_annual_amount',
        'lot_size_sqft',
        'pets_allowed',
        'senior_community_yn',
        'garage_yn',
        'pool_private_yn',
        'waterfront_yn',
        'association_yn',
        'new_construction_yn',
        'view_yn',
        'water_view_yn',
        'cdd_yn',

        // Permanent-retention flag (Task 3 — selective DNA dispatch)
        'is_permanent',
    ];

    protected $casts = [
        'modification_timestamp' => 'datetime',
        'imported_at'            => 'datetime',
        'list_price'             => 'decimal:2',

        // Phase 1 casts
        'latitude'               => 'decimal:7',
        'longitude'              => 'decimal:7',
        'association_fee'        => 'decimal:2',
        'tax_annual_amount'      => 'decimal:2',
        'year_built'             => 'integer',
        'lot_size_sqft'          => 'integer',
        'senior_community_yn'    => 'boolean',
        'garage_yn'              => 'boolean',
        'pool_private_yn'        => 'boolean',
        'waterfront_yn'          => 'boolean',
        'association_yn'         => 'boolean',
        'new_construction_yn'    => 'boolean',
        'view_yn'                => 'boolean',
        'water_view_yn'          => 'boolean',
        'cdd_yn'                 => 'boolean',

        // Task 3
        'is_permanent'           => 'boolean',
    ];

    /**
     * @var bool $is_permanent
     *
     * Permanent-retention flag. When true, this record MUST NOT be deleted by
     * any bulk-cleanup or eviction job. The flag is set programmatically only
     * (e.g. by an admin command); there is no user-facing UI for it.
     *
     * Enforcement:
     *   - The model-level `deleting` event (see boot()) throws
     *     \RuntimeException when any code path attempts to delete a permanent
     *     record. This catches both individual deletes and soft-deletes that
     *     go through Eloquent.
     *   - Raw DB::table('bridge_properties')->delete() bypasses this guard.
     *     Any future migration or cleanup script that issues raw SQL MUST
     *     include  WHERE is_permanent = false  in its WHERE clause.
     *   - BridgeProperty::nonPermanent() scope provides a safe starting point
     *     for all cleanup queries.
     *
     * All rows existing at migration time default to false, so no pre-existing
     * row is protected until explicitly flagged.
     */

    /**
     * Local scope that excludes permanently-retained records.
     *
     * All bulk-delete and eviction queries MUST start with this scope:
     *
     *   BridgeProperty::nonPermanent()->where(...)->delete();
     *
     * This makes the is_permanent exclusion contract visible and auditable
     * at every call site, rather than relying on documentation alone.
     */
    public function scopeNonPermanent(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_permanent', false);
    }

    protected static function boot(): void
    {
        parent::boot();

        // Hard-enforce the permanent-retention contract at the model level.
        // Any attempt to delete a record with is_permanent = true will throw
        // immediately, regardless of which code path initiated the delete.
        //
        // NOTE: This guard fires for Eloquent-level deletes only.
        // Raw DB::table() queries bypass it — see the $is_permanent docblock
        // above for the raw-SQL obligation.
        static::deleting(function (self $record): bool {
            if ($record->is_permanent) {
                throw new \RuntimeException(
                    "BridgeProperty #{$record->id} (listing_key={$record->listing_key}) "
                    . 'is permanently retained and cannot be deleted. '
                    . 'Set is_permanent = false before attempting removal.'
                );
            }

            return true;
        });
    }
}
