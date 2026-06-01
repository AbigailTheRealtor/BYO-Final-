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
        'avatar_type',
        'primary_motivation',
        'secondary_motivation',
        'buyer_narrative',
        'buyer_preference_summary',
        'buyer_personality_tags',
        'buyer_match_preferences',
        'avatar_confidence_score',
        'buyer_avatar_version',
        'buyer_readiness_score',
        'tenant_narrative',
        'tenant_preference_summary',
        'tenant_personality_tags',
        'tenant_match_preferences',
        'tenant_avatar_version',
    ];

    protected $casts = [
        'preference_completeness'   => 'decimal:2',
        'lifestyle_tags'            => 'array',
        'deal_breaker_flags'        => 'array',
        'source_listing_updated_at' => 'datetime',
        'computed_at'               => 'datetime',
        'archived_at'               => 'datetime',
        'buyer_preference_summary'  => 'array',
        'buyer_personality_tags'    => 'array',
        'buyer_match_preferences'   => 'array',
        'avatar_confidence_score'   => 'integer',
        'buyer_readiness_score'     => 'integer',
        'tenant_preference_summary' => 'array',
        'tenant_personality_tags'   => 'array',
        'tenant_match_preferences'  => 'array',
    ];
}
