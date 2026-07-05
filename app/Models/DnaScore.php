<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DnaScore — a single symmetric per-side Beyond-MLS DNA score.
 *
 * Addressed by (listing_type, listing_id, score_key, side). `side` is
 * 'property' (supply-side score, e.g. how pet-friendly a listing is) or
 * 'demand' (the searcher's preference weight on the same 0–100 axis).
 *
 * See docs/beyond-mls-wave1-implementation-architecture.md §F2.
 */
class DnaScore extends Model
{
    protected $table = 'dna_scores';

    protected $fillable = [
        'listing_type',
        'listing_id',
        'score_key',
        'side',
        'value',
        'data_completeness',
        'confidence',
        'explanation',
        'inputs_json',
        'version',
        'generated_by',
        'generator_version',
        'source_version',
        'computed_at',
    ];

    protected $casts = [
        'value'             => 'integer',
        'data_completeness' => 'integer',
        'confidence'        => 'integer',
        'inputs_json'       => 'array',
        'computed_at'       => 'datetime',
    ];
}
