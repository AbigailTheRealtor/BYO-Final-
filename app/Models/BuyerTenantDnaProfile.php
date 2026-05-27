<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuyerTenantDnaProfile extends Model
{
    protected $table = 'buyer_tenant_dna_profiles';

    protected $fillable = [
        'listing_type',
        'listing_id',
        'version',
        'source_listing_updated_at',
        'preference_completeness',
        'lifestyle_tags',
        'deal_breaker_flags',
        'archetype_label',
        'commute_polygon_cache',
        'computed_at',
        'archived_at',
    ];

    protected $casts = [
        'preference_completeness'   => 'decimal:2',
        'lifestyle_tags'            => 'array',
        'deal_breaker_flags'        => 'array',
        'source_listing_updated_at' => 'datetime',
        'computed_at'               => 'datetime',
        'archived_at'               => 'datetime',
    ];
}
