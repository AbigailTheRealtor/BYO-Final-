<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLandlordCounterTermsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('landlord_counter_terms', function (Blueprint $table) {
            $table->id();

            // Main fields stored directly in the table
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('landlord_agent_auction_id');
            $table->string('property_type');
            $table->unsignedBigInteger('parent_counter_id')->nullable(); // For counter-back chain

            $table->string('status')->default('0'); // Add this line
            $table->timestamp('accepted_date')->nullable(); // Add this line
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('landlord_agent_auction_id')->references('id')->on('landlord_agent_auctions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('landlord_counter_terms');
    }
}
