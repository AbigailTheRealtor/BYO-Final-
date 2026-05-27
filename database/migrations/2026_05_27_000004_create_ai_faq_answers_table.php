<?php

/*
 * AI FAQ answers currently stored as EAV meta JSON blob via saveMeta('listing_ai_faq').
 * All four listing workflows (Seller, Landlord, Buyer, Tenant) store answers as a single
 * JSON-encoded associative array (keyed by question_key) in the meta key 'listing_ai_faq'
 * using the saveMeta() / loadDraft() pattern. There is no dedicated per-question row table.
 * This migration creates the target table only.
 * Backfill from existing meta storage is Phase F work.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAiFaqAnswersTable extends Migration
{
    public function up()
    {
        Schema::create('ai_faq_answers', function (Blueprint $table) {
            $table->id();
            $table->string('listing_type');
            $table->unsignedBigInteger('listing_id');
            $table->string('question_key');
            $table->string('question_group')->nullable();
            $table->string('intelligence_category')->nullable();
            $table->longText('answer_text')->nullable();
            $table->json('answer_normalized')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(['listing_type', 'listing_id']);
            $table->index('intelligence_category');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_faq_answers');
    }
}
