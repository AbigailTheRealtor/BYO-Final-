<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyDnaProfile extends Model
{
    protected $table = 'property_dna_profiles';

    protected $fillable = [
        'listing_type',
        'listing_id',
        'version',
        'source_listing_updated_at',
        'physical_score',
        'financial_score',
        'location_score',
        'condition_score',
        'legal_score',
        'flexibility_score',
        'occupant_qualification_score',
        'marketing_score',
        'compatibility_score',
        'commercial_score',
        'overall_dna_completeness',
        'ai_buyer_archetype_tags',
        'ai_marketing_hooks',
        'walk_score',
        'transit_score',
        'bike_score',
        'school_rating',
        'flood_zone_verified',
        'estimated_monthly_utilities',
        'computed_at',
        'archived_at',
    ];

    protected $casts = [
        'physical_score'               => 'decimal:2',
        'financial_score'              => 'decimal:2',
        'location_score'               => 'decimal:2',
        'condition_score'              => 'decimal:2',
        'legal_score'                  => 'decimal:2',
        'flexibility_score'            => 'decimal:2',
        'occupant_qualification_score' => 'decimal:2',
        'marketing_score'              => 'decimal:2',
        'compatibility_score'          => 'decimal:2',
        'commercial_score'             => 'decimal:2',
        'overall_dna_completeness'     => 'decimal:2',
        'school_rating'                => 'decimal:2',
        'estimated_monthly_utilities'  => 'decimal:2',
        'ai_buyer_archetype_tags'      => 'array',
        'ai_marketing_hooks'           => 'array',
        'source_listing_updated_at'    => 'datetime',
        'computed_at'                  => 'datetime',
        'archived_at'                  => 'datetime',
    ];
}
