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
    ];
}
