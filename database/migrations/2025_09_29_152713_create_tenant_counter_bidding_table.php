<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantCounterBiddingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('tenant_agent_auctions')) {
            return; // Skip if base table doesn't exist
        }
        Schema::create('tenant_counter_bidding', function (Blueprint $table) {
            $table->id();

            // Main fields stored directly in the table
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tenant_agent_auction_id');
            $table->unsignedBigInteger('tenant_agent_auction_bid_id');
            $table->string('property_type');
            $table->unsignedBigInteger('parent_counter_id')->nullable(); // For counter-back chain

            $table->string('accepted')->default('0'); // Add this line
            $table->timestamp('accepted_date')->nullable(); // Add this line

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('tenant_agent_auction_id')->references('id')->on('tenant_agent_auctions')->onDelete('cascade');
            $table->foreign('tenant_agent_auction_bid_id')->references('id')->on('tenant_agent_auction_bids')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tenant_counter_bidding');
    }
}
