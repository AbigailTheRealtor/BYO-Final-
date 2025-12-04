<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyAuctionMediaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_auction_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_auction_id')->constrained('property_auctions')->onDelete('cascade');
            $table->string('media_type');
            $table->string('original_name');
            $table->string('name');
            $table->string('size');
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
        Schema::dropIfExists('property_auction_media');
    }
}
