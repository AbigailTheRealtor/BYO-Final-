<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listing Preferences Phase 1 — the CURRENT reasons behind a current state.
 *
 * Child of listing_preferences, replaced wholesale when the state or the chips
 * change. The immutable record of what was chosen at the time lives on the
 * event row instead.
 *
 * WHY ROWS AND NOT A JSON COLUMN. The reasons are the part a future learner
 * reads — "the specific reasons matter separately from the overall state" — so
 * they have to be queryable, and this application's tests run on SQLite in
 * memory while production is PostgreSQL. A JSON column would be queryable on
 * exactly one of those.
 *
 * `dimension` and `smart_tag_key` are DENORMALISED from the catalog on purpose,
 * and this is the one place that is right: a stored reason must still be
 * interpretable after the catalog changes. They are a record of what the chip
 * meant when it was chosen, not a cache of what it means now — live reads go to
 * ListingPreferenceReasonCatalog.
 *
 * ON DELETE CASCADE: a reason cannot outlive the state it explains, and a
 * state-change replaces the whole set. The history is in the events table,
 * which never cascades.
 *
 * Additive and reversible. NOTHING WRITES TO IT IN PHASE 1.
 */
class CreateListingPreferenceReasonsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('listing_preference_reasons')) {
            return;
        }

        Schema::create('listing_preference_reasons', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('listing_preference_id');

            $table->string('reason_key', 64);             // config/listing_preference_reasons.php key
            $table->string('dimension', 16);              // smart_tag | criteria | location | unspecified
            $table->string('smart_tag_key', 64)->nullable();   // set only for the smart_tag dimension

            $table->timestamp('created_at')->nullable();

            $table->unique(['listing_preference_id', 'reason_key'], 'listing_preference_reasons_unique');
            $table->index('reason_key', 'listing_preference_reasons_key_index');
            $table->index('smart_tag_key', 'listing_preference_reasons_tag_index');

            $table->foreign('listing_preference_id', 'listing_preference_reasons_preference_foreign')
                ->references('id')
                ->on('listing_preferences')
                ->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('listing_preference_reasons');
    }
}
