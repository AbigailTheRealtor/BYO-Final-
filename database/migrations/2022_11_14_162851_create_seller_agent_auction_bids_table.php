<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellerAgentAuctionBidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seller_agent_auction_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_agent_auction_id')->constrained('seller_agent_auctions')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->float('brokerage',18,2)->nullable();
            $table->string('license_no')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('mls_id')->nullable();
            $table->bigInteger('county_id')->nullable();
            $table->float('price',18,2)->default(0.00)->nullable();
            $table->float('price_percent',12,2)->default(0);
            $table->string('reviews_link')->nullable();
            $table->string('website_link')->nullable();
            $table->text('additional_details')->nullable();
            $table->text('listing_terms')->nullable();
            $table->text('why_seller_pick_me')->nullable();
            $table->text('services_description')->nullable();
            $table->string('video')->nullable();
            $table->string('video_url')->nullable();#
            $table->string('audio')->nullable();
            $table->text('note')->nullable();
            $table->json('other')->nullable();
            $table->string('market_analysis')->nullable();
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
        Schema::dropIfExists('seller_agent_auction_bids');
    }
}
