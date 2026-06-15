<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * AgentAiChatMessage
 *
 * A single message turn within an AgentAiChatSession.
 *
 * ROLE CONTRACT: role MUST be one of ROLE_USER ('user') or ROLE_ASSISTANT
 * ('assistant'). This is enforced at three layers:
 *   1. DB CHECK constraint (migration 2026_06_15_000003)
 *   2. Model boot hook (throws InvalidArgumentException before any DB write)
 *   3. Constants ROLE_USER / ROLE_ASSISTANT — always use these in code
 *
 * GOVERNANCE: No writes outside the V2 pipeline. Content must never contain
 * raw prompts, API keys, or internal model-selection details.
 */
class AgentAiChatMessage extends Model
{
    public const ROLE_USER      = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    /** All valid role values. Used by the boot-hook guard. */
    public const VALID_ROLES = [self::ROLE_USER, self::ROLE_ASSISTANT];

    protected $table = 'agent_ai_chat_messages';

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'detected_intent',
        'lead_score_snapshot',
        'context_scope',
        'tokens_used',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Boot hook: enforce role contract before any DB write.
     *
     * Throws immediately so invalid roles are caught at the application
     * layer (before hitting the DB CHECK constraint), giving a clear
     * PHP-level error rather than a DB exception.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $model): void {
            if (!in_array($model->role, self::VALID_ROLES, true)) {
                throw new InvalidArgumentException(
                    "AgentAiChatMessage.role must be one of: "
                    . implode(', ', self::VALID_ROLES)
                    . " — got: " . var_export($model->role, true)
                );
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentAiChatSession::class, 'session_id');
    }
}
