<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Tags Phase 1 — append-only audit of owner manual tag changes.
 *
 * Who selected or deselected which canonical tag on which listing, and when.
 * No updated_at: rows are never changed. The model refuses updates and deletes.
 *
 * Additive and reversible. Nothing writes to it in Phase 1.
 */
class CreateSmartTagManualEventsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('smart_tag_manual_events')) {
            return;
        }

        Schema::create('smart_tag_manual_events', function (Blueprint $table) {
            $table->id();

            $table->string('listing_type', 32);
            $table->unsignedBigInteger('listing_id');
            $table->string('tag_key', 64);
            $table->string('action', 40);                 // selected | deselected | pruned_*
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_role', 30);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['listing_type', 'listing_id'], 'smart_tag_manual_events_listing_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('smart_tag_manual_events');
    }
}
