<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: Database-First Answer Layer — add outcome_category to ask_ai_usage_logs.
 *
 * The outcome_category column records which pipeline branch handled each
 * Ask AI request for observability and cost tracking:
 *
 *   database_hit                 — answer served from snapshot; OpenAI not called
 *   blank_information_not_provided — field known but blank in snapshot; OpenAI not called
 *   restricted                   — field restricted in snapshot; blocked before OpenAI
 *   openai_fallback              — snapshot miss; OpenAI was called normally
 *   unsupported                  — question type unsupported; OpenAI not called
 *   blocked_restricted           — blocked by contract/classifier (prohibited / restricted)
 *   error                        — pipeline exception
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ask_ai_usage_logs', function (Blueprint $table) {
            $table->string('outcome_category', 40)->nullable()->after('api_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('ask_ai_usage_logs', function (Blueprint $table) {
            $table->dropColumn('outcome_category');
        });
    }
};
