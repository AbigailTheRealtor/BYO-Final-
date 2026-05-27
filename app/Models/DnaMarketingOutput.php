<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DnaMarketingOutput extends Model
{
    protected $table = 'dna_marketing_outputs';

    const UPDATED_AT = null;

    protected $fillable = [
        'listing_type',
        'listing_id',
        'output_type',
        'variant_index',
        'content',
        'fair_housing_reviewed',
        'fair_housing_flags',
        'generated_by',
        'version',
        'source_listing_updated_at',
        'scoring_version',
        'generated_at',
        'archived_at',
        'created_at',
    ];

    protected $casts = [
        'fair_housing_reviewed'     => 'boolean',
        'fair_housing_flags'        => 'array',
        'source_listing_updated_at' => 'datetime',
        'generated_at'              => 'datetime',
        'archived_at'               => 'datetime',
        'created_at'                => 'datetime',
    ];
}
