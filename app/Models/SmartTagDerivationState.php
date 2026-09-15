<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Change detection for a listing's derived Smart Tag sources.
 *
 * Independent stamps: structured inputs, MLS remarks, native description and
 * tagger version. A source is re-derived only when its own stamp (or the tagger
 * version, or the listing's context) changes.
 */
class SmartTagDerivationState extends Model
{
    protected $table = 'smart_tag_derivation_states';

    protected $fillable = [
        'listing_type',
        'listing_id',
        'context',
        'structured_inputs_hash',
        'mls_remarks_hash',
        'native_description_hash',
        'tagger_version',
        'derived_at',
    ];

    protected $casts = [
        'listing_id' => 'integer',
        'derived_at' => 'datetime',
    ];
}
