<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One source's evidence for one Smart Tag on one listing.
 *
 * Unique per (listing_type, listing_id, tag_key, source). Carries a rule id and
 * field name, never listing text. Written only by SmartTagEvidenceWriter and
 * ManualSmartTagWriter.
 */
class SmartTagEvidence extends Model
{
    protected $table = 'smart_tag_evidence';

    protected $fillable = [
        'listing_type',
        'listing_id',
        'tag_key',
        'context',
        'source',
        'state',
        'confidence',
        'source_field',
        'rule_id',
        'set_by_user_id',
        'tagger_version',
    ];

    protected $casts = [
        'listing_id'     => 'integer',
        'confidence'     => 'integer',
        'set_by_user_id' => 'integer',
    ];
}
