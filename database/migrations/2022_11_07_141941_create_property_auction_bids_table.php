<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyAuctionBidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_auction_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_auction_id')->constrained('property_auctions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->bigInteger('financing_id');
            $table->float('price',18,2)->default(0);
            $table->float('escrow_amount',18,2)->default(0);
            $table->date('closing_date')->nullable();
            $table->string('inspection_period')->nullable();
            $table->string('contingencies')->nullable();
            $table->string('seller_premium')->nullable();#
            $table->string('buyer_premium')->nullable();#
            $table->string('video')->nullable();
            $table->string('video_url')->nullable();#
            $table->string('card')->nullable();#
            $table->string('letter')->nullable();
            $table->string('audio')->nullable();
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
        Schema::dropIfExists('property_auction_bids');
    }
}
