<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Funnel stage timestamps for each bid.
     *
     * One row per bid. Each timestamp column records when the bid FIRST entered
     * that funnel stage. Re-entry never overwrites a previously set timestamp.
     * This preserves immutability for time-to-ready, time-to-submit,
     * and time-to-hire calculations.
     *
     * Funnel order:
     *   not_ready → quick_match_ready → full_match_ready → bid_submitted
     *   → bid_accepted → agent_hired
     */
    public function up(): void
    {
        Schema::create('bid_funnel_timestamps', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('bid_type', 30)
                  ->comment('seller_agent|buyer_agent|landlord_agent|tenant_agent');
            $table->unsignedBigInteger('bid_id');
            $table->string('role', 20)
                  ->comment('seller|buyer|landlord|tenant');

            // One nullable timestamp column per funnel stage.
            // Set once on first entry — never overwritten on re-entry.
            $table->timestamp('not_ready_at')->nullable();
            $table->timestamp('quick_match_ready_at')->nullable();
            $table->timestamp('full_match_ready_at')->nullable();
            $table->timestamp('bid_submitted_at')->nullable();
            $table->timestamp('bid_accepted_at')->nullable();
            $table->timestamp('agent_hired_at')->nullable();

            // Standard timestamps for the row itself
            $table->timestamps();

            // One row per bid
            $table->unique(['bid_type', 'bid_id'], 'bft_bid_unique');
            $table->index(['role', 'bid_type'], 'bft_role_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_funnel_timestamps');
    }
};
