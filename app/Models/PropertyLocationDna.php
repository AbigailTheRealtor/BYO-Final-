<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyLocationDna extends Model
{
    protected $table = 'property_location_dna';

    protected $fillable = [
        'listing_type',
        'listing_id',
        'source_address',
        'source_city',
        'source_county',
        'source_state',
        'source_zip',
        'geocoded_lat',
        'geocoded_lng',
        'geocode_source',
        'geocode_status',
        'geocode_error',
        'geocoded_at',
        'summary_json',
        'lifestyle_json',
        'generated_at',
    ];

    protected $casts = [
        'summary_json'   => 'array',
        'lifestyle_json' => 'array',
        'geocoded_at'    => 'datetime',
        'generated_at'   => 'datetime',
    ];
}
