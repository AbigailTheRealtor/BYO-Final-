<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tighten the role column on agent_ai_chat_messages to only allow
 * 'user' or 'assistant'. Enforced at the database level via a CHECK
 * constraint so invalid roles are rejected before reaching the application.
 */
class AddRoleCheckConstraintToAgentAiChatMessages extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE agent_ai_chat_messages
             ADD CONSTRAINT agent_ai_chat_messages_role_check
             CHECK (role IN ('user', 'assistant'))"
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE agent_ai_chat_messages
             DROP CONSTRAINT IF EXISTS agent_ai_chat_messages_role_check'
        );
    }
}
