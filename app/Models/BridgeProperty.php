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
    ];

    protected $casts = [
        'modification_timestamp' => 'datetime',
        'imported_at'            => 'datetime',
        'list_price'             => 'decimal:2',
    ];
}
