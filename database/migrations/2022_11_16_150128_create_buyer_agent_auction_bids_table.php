<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBuyerAgentAuctionBidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('buyer_agent_auction_bids', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('buyer_agent_auction_id');
            $table->bigInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->float('brokerage',18,2)->nullable();
            $table->string('license_no')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('mls_id')->nullable();
            $table->bigInteger('county_id')->nullable();
            $table->float('price',18,2)->nullable()->default(0.00);
            $table->float('price_percent',12,2)->nullable()->default(0);
            $table->string('reviews_link')->nullable();
            $table->string('credit_offer')->nullable();
            $table->string('website_link')->nullable();
            $table->text('additional_details')->nullable();
            $table->text('why_seller_pick_me')->nullable();
            $table->text('services_description')->nullable();
            $table->string('video')->nullable();
            $table->string('video_url')->nullable();#
            $table->boolean('video_public')->default(false);
            $table->string('audio')->nullable();
            $table->boolean('audio_public')->default(false);
            $table->text('note')->nullable();
            $table->json('other')->nullable();
            $table->boolean('note_public')->default(false);
            $table->boolean('accepted')->default(false);
            $table->timestamp('accepted_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('buyer_agent_auction_bids');
    }
}
