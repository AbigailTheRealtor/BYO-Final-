<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentAiChatMessagesTable extends Migration
{
    public function up(): void
    {
        Schema::create('agent_ai_chat_messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('session_id');
            $table->foreign('session_id')
                ->references('id')
                ->on('agent_ai_chat_sessions')
                ->onDelete('cascade');

            $table->string('role', 16);       // 'user' | 'assistant'
            $table->text('content');

            $table->string('detected_intent', 128)->nullable();
            $table->smallInteger('lead_score_snapshot')->nullable();
            $table->string('context_scope', 64);
            $table->integer('tokens_used')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_ai_chat_messages');
    }
}
