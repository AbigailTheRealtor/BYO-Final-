<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AgentAiChatSession
 *
 * Represents a chat session between a visitor and an agent's AI assistant.
 *
 * CHANNEL CONSTANTS
 * -----------------
 * The `channel` column is nullable varchar. Allowed values are defined in
 * ALLOWED_CHANNELS so future integrations stay consistent without requiring
 * a schema redesign. No DB-level enum is used.
 *
 * GOVERNANCE: No writes outside the V2 pipeline. session_token is always
 * generated as a cryptographically secure random token — never sequential,
 * never predictable.
 */
class AgentAiChatSession extends Model
{
    /**
     * Application-layer allowed values for the `channel` column.
     * All future channel integrations must use one of these values.
     */
    public const ALLOWED_CHANNELS = [
        'website',
        'facebook',
        'instagram',
        'sms',
        'voice',
        'whatsapp',
        'email',
    ];

    protected $table = 'agent_ai_chat_sessions';

    protected $fillable = [
        'session_token',
        'agent_id',
        'scope',
        'listing_type',
        'listing_id',
        'visitor_user_id',
        'visitor_ip',
        'started_at',
        'last_active_at',
        'ended_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'notified_score_50_at',
        'notified_score_75_at',
        'notified_score_90_at',
        'channel',
        'channel_user_id',
    ];

    protected $casts = [
        'started_at'            => 'datetime',
        'last_active_at'        => 'datetime',
        'ended_at'              => 'datetime',
        'reviewed_at'           => 'datetime',
        'notified_score_50_at'  => 'datetime',
        'notified_score_75_at'  => 'datetime',
        'notified_score_90_at'  => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentAiChatMessage::class, 'session_id')->orderBy('created_at');
    }

    public function lead(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\AgentAiChatLead::class, 'session_id');
    }

    /**
     * Return the last N messages, ordered oldest-first (for history injection).
     */
    public function recentMessages(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return $this->hasMany(AgentAiChatMessage::class, 'session_id')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->sortBy('created_at')
            ->values();
    }
}
