<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AskAiAnswer extends Model
{
    protected $fillable = [
        'snapshot_id',
        'canonical_key',
        'answer_text',
        'question_id',
        'classification',
        'visibility',
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

    public function question(): BelongsTo
    {
        return $this->belongsTo(AskAiQuestion::class, 'question_id');
    }
}
