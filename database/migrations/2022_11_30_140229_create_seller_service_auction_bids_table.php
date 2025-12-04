<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellerServiceAuctionBidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seller_service_auction_bids', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('seller_service_auction_id');
            $table->bigInteger('user_id');
            $table->string('name');
            $table->string('phone');
            $table->string('email');
            $table->double('brokerage');
            $table->string('license_no');
            $table->string('price_in');
            $table->double('price');
            $table->text('additional_details');
            $table->string('card');
            $table->string('video');
            $table->string('video_url')->nullable();#
            $table->string('audio');
            $table->string('note');
            $table->json('other')->nullable();
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
        Schema::dropIfExists('seller_service_auction_bids');
    }
}
