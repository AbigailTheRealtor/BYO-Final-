<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOfferAuctionMetasTable extends Migration
{
    public function up()
    {
        Schema::create('offer_auction_metas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_auction_id');
            $table->string('meta_key');
            $table->longText('meta_value')->nullable();

            $table->foreign('offer_auction_id')->references('id')->on('offer_auctions')->onDelete('cascade');
            $table->unique(['offer_auction_id', 'meta_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('offer_auction_metas');
    }
}
