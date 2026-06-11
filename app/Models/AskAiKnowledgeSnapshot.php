<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AskAiKnowledgeSnapshot extends Model
{
    protected $fillable = [
        'snapshot_uuid',
        'listing_type',
        'listing_id',
        'version',
        'status',
        'error_message',
        'source_model',
        'source_updated_at',
        'built_at',
        'facts_count',
        'questions_count',
        'answers_count',
    ];

    protected $casts = [
        'built_at'          => 'datetime',
        'source_updated_at' => 'datetime',
        'facts_count'       => 'integer',
        'questions_count'   => 'integer',
        'answers_count'     => 'integer',
    ];

    public function facts(): HasMany
    {
        return $this->hasMany(AskAiFact::class, 'snapshot_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AskAiQuestion::class, 'snapshot_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AskAiAnswer::class, 'snapshot_id');
    }
}
