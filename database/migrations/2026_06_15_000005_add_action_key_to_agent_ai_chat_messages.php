<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add action_key column to agent_ai_chat_messages.
 *
 * action_key is set on user messages when a CTA action was explicitly
 * triggered by the visitor (e.g., 'view_agent_services'). It is null for
 * ordinary question messages. Analytics queries group non-null action_key
 * values to report "Top CTAs clicked."
 */
class AddActionKeyToAgentAiChatMessages extends Migration
{
    public function up(): void
    {
        Schema::table('agent_ai_chat_messages', function (Blueprint $table) {
            $table->string('action_key', 64)->nullable()->after('detected_intent');
        });
    }

    public function down(): void
    {
        Schema::table('agent_ai_chat_messages', function (Blueprint $table) {
            $table->dropColumn('action_key');
        });
    }
}
