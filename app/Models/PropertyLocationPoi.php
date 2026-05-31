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
        'poi_subtype',
        'poi_name',
        'poi_address',
        'poi_lat',
        'poi_lng',
        'source_lat',
        'source_lng',
        'distance_miles',
        'travel_time_minutes',
        'data_source',
        'status',
        'error',
        'calculated_at',
    ];

    protected $casts = [
        'calculated_at' => 'datetime',
    ];
}
