<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentAiChatLeadsTable extends Migration
{
    public function up(): void
    {
        Schema::create('agent_ai_chat_leads', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('session_id');
            $table->foreign('session_id')
                ->references('id')
                ->on('agent_ai_chat_sessions')
                ->onDelete('cascade');

            $table->unsignedBigInteger('agent_id');
            $table->foreign('agent_id')->references('id')->on('users');

            $table->string('listing_type', 64)->nullable();
            $table->unsignedBigInteger('listing_id')->nullable();

            $table->unsignedBigInteger('visitor_user_id')->nullable();
            $table->foreign('visitor_user_id')->references('id')->on('users');

            $table->string('visitor_name', 255)->nullable();
            $table->string('visitor_email', 255)->nullable();
            $table->string('visitor_phone', 64)->nullable();
            $table->string('preferred_contact', 64)->nullable();

            // Enum stored as varchar; validated at app layer.
            // Values: buyer | seller | landlord | tenant | investor | referral | agent_question
            $table->string('lead_type', 64)->nullable();

            $table->text('intent_phrase')->nullable();

            // Score capped at 100 in application logic; smallint is sufficient.
            $table->smallInteger('lead_score')->default(0);

            $table->string('requested_action', 128)->nullable();
            $table->text('conversation_summary')->nullable();
            $table->json('questions_asked')->nullable();
            $table->string('source_page', 255)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->text('recommended_follow_up')->nullable();

            $table->timestamps();

            $table->index('agent_id');
            $table->index('session_id');
            $table->index('visitor_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_ai_chat_leads');
    }
}
