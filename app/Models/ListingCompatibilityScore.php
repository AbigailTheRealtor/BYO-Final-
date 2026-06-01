<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingCompatibilityScore extends Model
{
    protected $table = 'listing_compatibility_scores';

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    const BYA_COMPAT_V1 = 'BYA_COMPAT_V1';

    protected $fillable = [
        'demand_listing_type',
        'demand_listing_id',
        'supply_listing_type',
        'supply_listing_id',
        'version',
        'scoring_framework_version',
        'demand_listing_updated_at_snapshot',
        'supply_listing_updated_at_snapshot',
        'overall_score',
        'physical_match_score',
        'financial_match_score',
        'location_match_score',
        'terms_match_score',
        'deal_breaker_triggered',
        'deal_breaker_flags',
        'score_explanation',
        'computed_at',
        'archived_at',
        'created_at',
        'representation_compatibility_score',
        'representation_compatibility_label',
        'compatibility_trait_results',
        'compatibility_framework_version',
        'ai_explanation_version',
        'moderation_status',
        'compatibility_computed_at',
        'compatibility_archived_at',
        'compatibility_narrative',
        'compatibility_summary_json',
        'compatibility_highlights',
        'compatibility_warnings',
        'compatibility_readiness_score',
    ];

    protected $casts = [
        'overall_score'                         => 'decimal:2',
        'physical_match_score'                  => 'decimal:2',
        'financial_match_score'                 => 'decimal:2',
        'location_match_score'                  => 'decimal:2',
        'terms_match_score'                     => 'decimal:2',
        'deal_breaker_triggered'                => 'boolean',
        'deal_breaker_flags'                    => 'array',
        'score_explanation'                     => 'array',
        'demand_listing_updated_at_snapshot'    => 'datetime',
        'supply_listing_updated_at_snapshot'    => 'datetime',
        'computed_at'                           => 'datetime',
        'archived_at'                           => 'datetime',
        'created_at'                            => 'datetime',
        'representation_compatibility_score'    => 'decimal:2',
        'compatibility_trait_results'           => 'array',
        'compatibility_computed_at'             => 'datetime',
        'compatibility_archived_at'             => 'datetime',
        'compatibility_summary_json'            => 'array',
        'compatibility_highlights'              => 'array',
        'compatibility_warnings'                => 'array',
        'compatibility_readiness_score'         => 'float',
    ];
}
