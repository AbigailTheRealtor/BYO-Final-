<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listing Preferences Phase 1 — append-only history of every state change.
 *
 * One row per transition: who, which subject, from what state to what state,
 * which reasons, and on which surface. No updated_at — rows are never changed,
 * and the model refuses updates and deletes, exactly as SmartTagManualEvent and
 * PropertyLocationDnaAudit do.
 *
 * WHY HISTORY IS A SEPARATE TABLE RATHER THAN A FLAG ON THE CURRENT ROW. Four
 * independent requirements need it, and none can be met by current state alone:
 *
 *   1. "changing Save → Maybe → Pass updates the current signal rather than
 *      stacking contradictory states" — one current row, enforced by a unique
 *      index, with the previous value kept somewhere.
 *   2. "repeated patterns should become stronger over time" and "one Save or
 *      Pass must not permanently define a preference" are both statements about
 *      TIME. Recency weighting and decay are uncomputable without the timeline.
 *   3. Undo, and recovering a passed listing.
 *   4. Fair Housing auditability: what a customer was shown and chose, after
 *      the fact.
 *
 * `reasons_json` is an immutable SNAPSHOT of the chips as chosen — not a
 * foreign key to listing_preference_reasons, which is replaced on every change.
 * `surface` records where the choice was made (results, detail, explore,
 * virtual_drive) so a later learner can weight deliberate actions differently
 * from incidental ones; it is not a permission and grants nothing.
 *
 * NO CASCADE from listing_preferences: deleting a current state must never
 * erase the record that it existed.
 *
 * Additive and reversible. NOTHING WRITES TO IT IN PHASE 1.
 */
class CreateListingPreferenceEventsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('listing_preference_events')) {
            return;
        }

        Schema::create('listing_preference_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->string('seeker_role', 16);            // buyer | tenant

            // The listing the customer acted on, and the durable subject.
            $table->string('listing_type', 32);
            $table->unsignedBigInteger('listing_id');
            $table->string('subject_key', 191);

            $table->string('from_state', 12)->nullable(); // null = no prior state
            $table->string('to_state', 12);               // save | maybe | pass

            // Immutable snapshot of the chosen reasons at this transition.
            $table->text('reasons_json')->nullable();

            $table->string('surface', 32)->nullable();    // where the choice was made

            $table->timestamp('created_at')->useCurrent();

            // The timeline for one customer and subject — what decay and
            // repeat-pattern weighting read.
            $table->index(
                ['user_id', 'seeker_role', 'subject_key', 'created_at'],
                'listing_preference_events_user_subject_time_index'
            );
            $table->index(['user_id', 'seeker_role', 'created_at'], 'listing_preference_events_user_time_index');
            $table->index(['listing_type', 'listing_id'], 'listing_preference_events_listing_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('listing_preference_events');
    }
}
