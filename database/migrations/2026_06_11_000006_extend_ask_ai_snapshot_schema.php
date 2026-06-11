<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Extends all four Ask AI snapshot tables to match the approved Phase 2 contract.
 *
 * ask_ai_knowledge_snapshots  — adds snapshot_uuid, source_model, source_updated_at,
 *                               facts_count, questions_count, answers_count.
 *                               Migrates existing status='built' rows to status='ready'.
 *
 * ask_ai_facts                — adds listing_type, listing_id, label, value_type,
 *                               source_path, classification, public_allowed (bool),
 *                               restricted (bool), sort_order.
 *
 * ask_ai_questions            — adds question_text, question_type, source_path, sort_order.
 *
 * ask_ai_answers              — adds question_id (FK → ask_ai_questions), classification,
 *                               visibility, source_path, sort_order.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. ask_ai_knowledge_snapshots ────────────────────────────────────
        Schema::table('ask_ai_knowledge_snapshots', function (Blueprint $table) {
            $table->uuid('snapshot_uuid')->nullable()->unique();
            $table->string('source_model', 120)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->unsignedInteger('facts_count')->default(0);
            $table->unsignedInteger('questions_count')->default(0);
            $table->unsignedInteger('answers_count')->default(0);
        });

        // Back-fill UUIDs for any pre-existing rows.
        DB::table('ask_ai_knowledge_snapshots')
            ->whereNull('snapshot_uuid')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('ask_ai_knowledge_snapshots')
                    ->where('id', $row->id)
                    ->update(['snapshot_uuid' => (string) Str::uuid()]);
            });

        // Migrate lifecycle status value: 'built' → 'ready'.
        DB::table('ask_ai_knowledge_snapshots')
            ->where('status', 'built')
            ->update(['status' => 'ready']);

        // ── 2. ask_ai_facts ──────────────────────────────────────────────────
        Schema::table('ask_ai_facts', function (Blueprint $table) {
            $table->string('listing_type', 20)->nullable()->index();
            $table->unsignedBigInteger('listing_id')->nullable()->index();
            $table->text('label')->nullable();
            $table->string('value_type', 20)->default('string');
            $table->string('source_path', 200)->nullable();
            $table->string('classification', 30)->nullable();
            $table->boolean('public_allowed')->default(true);
            $table->boolean('restricted')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
        });

        // ── 3. ask_ai_questions ──────────────────────────────────────────────
        Schema::table('ask_ai_questions', function (Blueprint $table) {
            $table->text('question_text')->nullable();
            $table->string('question_type', 30)->nullable();
            $table->string('source_path', 200)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
        });

        // ── 4. ask_ai_answers ────────────────────────────────────────────────
        Schema::table('ask_ai_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('question_id')->nullable()->index();
            $table->string('classification', 30)->nullable();
            $table->string('visibility', 20)->default('public_allowed');
            $table->string('source_path', 200)->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreign('question_id')
                  ->references('id')
                  ->on('ask_ai_questions')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('ask_ai_answers', function (Blueprint $table) {
            $table->dropForeign(['question_id']);
            $table->dropColumn(['question_id', 'classification', 'visibility', 'source_path', 'sort_order']);
        });

        Schema::table('ask_ai_questions', function (Blueprint $table) {
            $table->dropColumn(['question_text', 'question_type', 'source_path', 'sort_order']);
        });

        Schema::table('ask_ai_facts', function (Blueprint $table) {
            $table->dropColumn([
                'listing_type', 'listing_id', 'label', 'value_type',
                'source_path', 'classification', 'public_allowed', 'restricted', 'sort_order',
            ]);
        });

        Schema::table('ask_ai_knowledge_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'snapshot_uuid', 'source_model', 'source_updated_at',
                'facts_count', 'questions_count', 'answers_count',
            ]);
        });

        DB::table('ask_ai_knowledge_snapshots')
            ->where('status', 'ready')
            ->update(['status' => 'built']);
    }
};
