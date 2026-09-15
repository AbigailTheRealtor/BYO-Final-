<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Tags Phase 1 — resolved canonical assignments.
 *
 * Exactly ONE row per listing × tag, projected from smart_tag_evidence by the
 * resolver. This is the table future matching reads, for Bridge and native
 * listings alike. No row means unknown.
 *
 * Additive and reversible. Nothing writes to it in Phase 1.
 */
class CreateSmartTagAssignmentsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('smart_tag_assignments')) {
            return;
        }

        Schema::create('smart_tag_assignments', function (Blueprint $table) {
            $table->id();

            $table->string('listing_type', 32);
            $table->unsignedBigInteger('listing_id');
            $table->string('tag_key', 64);
            $table->string('context', 32);
            $table->string('state', 12);                  // present | absent
            $table->string('winning_source', 32);
            $table->boolean('has_conflict')->default(false);
            $table->text('conflict_tags')->nullable();    // JSON list of overridden conflicting tags
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->unique(['listing_type', 'listing_id', 'tag_key'], 'smart_tag_assignments_listing_tag_unique');
            $table->index(['tag_key', 'state', 'context'], 'smart_tag_assignments_tag_state_context_index');
            $table->index(['listing_type', 'listing_id'], 'smart_tag_assignments_listing_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('smart_tag_assignments');
    }
}
