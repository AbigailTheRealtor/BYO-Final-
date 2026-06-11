<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AskAiFact extends Model
{
    protected $fillable = [
        'snapshot_id',
        'canonical_key',
        'value',
        'visibility',
        'listing_type',
        'listing_id',
        'label',
        'value_type',
        'source_path',
        'classification',
        'public_allowed',
        'restricted',
        'sort_order',
    ];

    protected $casts = [
        'public_allowed' => 'boolean',
        'restricted'     => 'boolean',
        'sort_order'     => 'integer',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(AskAiKnowledgeSnapshot::class, 'snapshot_id');
    }
}
