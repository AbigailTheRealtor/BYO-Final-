<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listing Preferences Phase 1 — the ONE current state per customer × subject.
 *
 * Exactly one row per (user_id, seeker_role, subject_key): Save, Maybe or Pass.
 * Moving between them REPLACES this row, so contradictory current states cannot
 * stack; the transition is recorded in listing_preference_events.
 *
 * TWO IDENTITIES, BOTH STORED. (listing_type, listing_id) is the listing the
 * customer acted on — the house addressing convention, values produced only by
 * SmartTagListingType. `subject_key` is the durable identity that decides
 * uniqueness, because one property reaches a customer as both a Bridge MLS row
 * and the BidYourOffer listing imported from it. See
 * App\Support\ListingPreferences\ListingPreferenceSubjectRef.
 *
 * `seeker_role` is part of the key: buying and renting are different intents
 * about different inventory, and a Pass on a rental must not suppress a purchase.
 *
 * No FK on the (listing_type, listing_id) pair — the house convention for every
 * multi-source table here, because there is no single target table. No FK on
 * user_id either: `users` is not InnoDB-guaranteed across the historical
 * migrations and every other table here addresses users the same way. Small
 * enumerations are string columns backed by PHP enums — no ->enum(), which
 * drifts on SQLite.
 *
 * Additive and reversible. NOTHING WRITES TO IT IN PHASE 1.
 * See docs/listing-preferences/LISTING_PREFERENCE_GOVERNANCE.md.
 */
class CreateListingPreferencesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('listing_preferences')) {
            return;
        }

        Schema::create('listing_preferences', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->string('seeker_role', 16);            // buyer | tenant

            // The listing the customer acted on.
            $table->string('listing_type', 32);           // bridge | seller_agent | landlord_agent
            $table->unsignedBigInteger('listing_id');

            // The durable subject: mls:<listing_key> | byo:<listing_type>:<id>
            $table->string('subject_key', 191);

            $table->string('state', 12);                  // save | maybe | pass
            $table->timestamp('state_set_at')->nullable();

            $table->timestamps();

            // One current state per customer per role per subject. This is the
            // constraint that makes Save → Maybe → Pass an update rather than a
            // pile of contradictions; it is not left to application discipline.
            $table->unique(['user_id', 'seeker_role', 'subject_key'], 'listing_preferences_user_role_subject_unique');

            // "What did this customer save / pass?" — the list surfaces.
            $table->index(['user_id', 'seeker_role', 'state'], 'listing_preferences_user_role_state_index');

            // "Who has a preference on this listing?" — reached by listing ref.
            $table->index(['listing_type', 'listing_id'], 'listing_preferences_listing_index');

            // Subject-first lookup, for the other representation of one property.
            $table->index('subject_key', 'listing_preferences_subject_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('listing_preferences');
    }
}
