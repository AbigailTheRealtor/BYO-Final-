<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AskAiQuestion extends Model
{
    protected $fillable = [
        'snapshot_id',
        'canonical_key',
        'field_type',
        'keyword_route_status',
        'label',
        'sample_question',
        'sample_question_2',
        'question_text',
        'question_type',
        'source_path',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(AskAiKnowledgeSnapshot::class, 'snapshot_id');
    }
}
