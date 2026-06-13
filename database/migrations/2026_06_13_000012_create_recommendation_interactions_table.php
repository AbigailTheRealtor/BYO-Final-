<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recommendation interaction events with attribution.
     *
     * Records when a user interacts with a bid or hire agent action.
     * The from_recommendation flag is TRUE only when the action was taken
     * from a recommendation surface — preventing inflated conversion rates.
     */
    public function up(): void
    {
        Schema::create('recommendation_interactions', function (Blueprint $table) {
            $table->bigIncrements('id');

            // The bid involved (nullable — some hire flows have no bid)
            $table->string('bid_type', 30)->nullable()
                  ->comment('seller_agent|buyer_agent|landlord_agent|tenant_agent|null');
            $table->unsignedBigInteger('bid_id')->nullable();

            $table->string('role', 20)
                  ->comment('seller|buyer|landlord|tenant');
            $table->string('property_type', 50)->nullable();

            // Interaction event type
            $table->string('event_type', 40)
                  ->comment('bid_viewed|bid_accepted|agent_hired');

            // Attribution: TRUE only when action originated from a recommendation surface
            $table->boolean('from_recommendation')->default(false);

            // Which recommendation surface the user interacted through
            // (e.g. 'consumer_fit_card', 'coaching_panel', 'preset_completion')
            // NULL when from_recommendation is false
            $table->string('recommendation_surface', 60)->nullable();

            // Actor — nullable for guest flows
            $table->unsignedBigInteger('user_id')->nullable();

            // Extra context (listing_id, session_id, etc.)
            $table->json('metadata')->nullable();

            // Append-only: no updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index(['bid_type', 'bid_id'], 'ri_bid_idx');
            $table->index(['role', 'event_type'], 'ri_role_event_idx');
            $table->index(['from_recommendation', 'event_type'], 'ri_rec_event_idx');
            $table->index('created_at', 'ri_created_idx');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_interactions');
    }
};
