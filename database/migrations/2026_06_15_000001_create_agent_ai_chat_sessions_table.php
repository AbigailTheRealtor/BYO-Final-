<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentAiChatSessionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('agent_ai_chat_sessions', function (Blueprint $table) {
            $table->id();

            $table->string('session_token', 128)->unique();

            $table->unsignedBigInteger('agent_id');
            $table->foreign('agent_id')->references('id')->on('users');

            $table->string('scope', 64);
            $table->string('listing_type', 64)->nullable();
            $table->unsignedBigInteger('listing_id')->nullable();

            $table->unsignedBigInteger('visitor_user_id')->nullable();
            $table->foreign('visitor_user_id')->references('id')->on('users');

            $table->string('visitor_ip', 64)->nullable();

            $table->timestamp('started_at');
            $table->timestamp('last_active_at');
            $table->timestamp('ended_at')->nullable();

            // Build 5 lifecycle columns — included now to avoid a follow-up schema migration
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->foreign('reviewed_by_user_id')->references('id')->on('users');
            $table->timestamp('notified_score_50_at')->nullable();
            $table->timestamp('notified_score_75_at')->nullable();
            $table->timestamp('notified_score_90_at')->nullable();

            // Multi-channel support — reserved for future integrations.
            // Application-layer allowed values documented in AgentAiChatSession::ALLOWED_CHANNELS.
            // No DB-level enum; validated at the app layer only.
            $table->string('channel', 64)->nullable();
            $table->string('channel_user_id', 128)->nullable();

            $table->timestamps();

            $table->index('agent_id');
            $table->index('visitor_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_ai_chat_sessions');
    }
}
