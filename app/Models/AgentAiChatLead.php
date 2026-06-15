<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AgentAiChatLead
 *
 * Persists a lead signal captured during an Agent AI V2 chat session.
 *
 * GOVERNANCE:
 *  - One lead record per session (upserted by AgentAiLeadCaptureService).
 *  - Never exposed to agents other than the owning agent (agent_id).
 *  - Visitor contact info must never appear in shared or public responses.
 *
 * VALID LEAD TYPES (application-layer enum):
 *   buyer | seller | landlord | tenant | investor | referral | agent_question
 */
class AgentAiChatLead extends Model
{
    public const LEAD_TYPES = [
        'buyer',
        'seller',
        'landlord',
        'tenant',
        'investor',
        'referral',
        'agent_question',
    ];

    public const LEAD_TYPE_BUYER          = 'buyer';
    public const LEAD_TYPE_SELLER         = 'seller';
    public const LEAD_TYPE_LANDLORD       = 'landlord';
    public const LEAD_TYPE_TENANT         = 'tenant';
    public const LEAD_TYPE_INVESTOR       = 'investor';
    public const LEAD_TYPE_REFERRAL       = 'referral';
    public const LEAD_TYPE_AGENT_QUESTION = 'agent_question';

    protected $table = 'agent_ai_chat_leads';

    protected $fillable = [
        'session_id',
        'agent_id',
        'listing_type',
        'listing_id',
        'visitor_user_id',
        'visitor_name',
        'visitor_email',
        'visitor_phone',
        'preferred_contact',
        'lead_type',
        'intent_phrase',
        'lead_score',
        'requested_action',
        'conversation_summary',
        'questions_asked',
        'source_page',
        'source_url',
        'recommended_follow_up',
    ];

    protected $casts = [
        'questions_asked' => 'array',
        'lead_score'      => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentAiChatSession::class, 'session_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_user_id');
    }
}
