<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Tags — the SEEKER side: canonical tags a Buyer or Tenant asked for.
 *
 * One row per criteria record × canonical tag key. The unique index is what
 * makes selecting a tag twice an idempotent no-op rather than a pile of
 * duplicates, and it is enforced in the schema rather than left to application
 * discipline — the same reasoning `listing_preferences` uses.
 *
 * SEPARATE FROM smart_tag_assignments ON PURPOSE. That table says a PROPERTY
 * has a characteristic; this one says a PERSON wants it. Sharing a table would
 * make "does this listing have a pool" and "does this buyer want a pool" the
 * same query, and the first is evidence while the second is a request.
 *
 * `tag_key` stores the CANONICAL KEY and nothing else — no label, no category,
 * no context copy of the taxonomy. Labels move; keys are the contract. Anything
 * a reader needs about the tag comes from SmartTagTaxonomy at read time.
 *
 * `context` is stored rather than derived because a criteria record's property
 * type can be edited afterwards: the row records the context the selection was
 * made under, so a later reader can tell a stale selection from a current one
 * instead of silently reinterpreting it.
 *
 * No foreign key to the criteria tables. Those are MyISAM-era tables in this
 * schema whose rows are hard-deleted by the listing purge, and the existing
 * Smart Tag tables (`smart_tag_evidence`, `smart_tag_assignments`) take the same
 * approach — deletion is handled explicitly by a purger, not by a constraint.
 *
 * Additive and reversible.
 */
class CreateSmartTagSeekerPreferencesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('smart_tag_seeker_preferences')) {
            return;
        }

        Schema::create('smart_tag_seeker_preferences', function (Blueprint $table) {
            $table->id();

            $table->string('subject_type', 32);            // SmartTagSeekerSubjectType
            $table->unsignedBigInteger('subject_id');      // criteria record id
            $table->unsignedBigInteger('user_id');         // owner at write time
            $table->string('seeker_role', 16);             // buyer | tenant
            $table->string('tag_key', 64);                 // CANONICAL key only
            $table->string('context', 32);                 // context the pick was made under

            $table->timestamps();

            $table->unique(
                ['subject_type', 'subject_id', 'tag_key'],
                'smart_tag_seeker_prefs_subject_tag_unique'
            );

            // The read a future matcher performs: every tag for one criteria record.
            $table->index(['subject_type', 'subject_id'], 'smart_tag_seeker_prefs_subject_index');

            // The read a future matcher performs in the other direction: who wants
            // this tag, narrowed by the side of the market and the property type.
            $table->index(['tag_key', 'seeker_role', 'context'], 'smart_tag_seeker_prefs_tag_role_context_index');

            $table->index('user_id', 'smart_tag_seeker_prefs_user_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('smart_tag_seeker_preferences');
    }
}
