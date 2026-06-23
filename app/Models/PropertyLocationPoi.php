<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyLocationPoi extends Model
{
    protected $table = 'property_location_pois';

    protected $fillable = [
        'listing_type',
        'listing_id',
        'poi_category',
        'rank',
        'poi_subtype',
        'poi_name',
        'poi_address',
        'poi_lat',
        'poi_lng',
        'source_lat',
        'source_lng',
        'distance_miles',
        'rating',
        'user_ratings_total',
        'types_json',
        'category_match_score',
        'consumer_relevance_score',
        'review_confidence_score',
        'ranking_score',
        'ranking_reasons_json',
        'travel_time_minutes',
        'data_source',
        'status',
        'error',
        'calculated_at',
    ];

    protected $casts = [
        'calculated_at'           => 'datetime',
        'types_json'              => 'array',
        'ranking_reasons_json'    => 'array',
        'rating'                  => 'float',
        'user_ratings_total'      => 'integer',
        'rank'                    => 'integer',
        'category_match_score'    => 'float',
        'consumer_relevance_score' => 'float',
        'review_confidence_score' => 'float',
        'ranking_score'           => 'float',
    ];
}
