<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBuyerAgentAuctionBidMetasTable extends Migration
{
    public function up()
    {
        Schema::create('buyer_agent_auction_bid_metas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buyer_agent_auction_bid_id')->nullable();
            $table->string('meta_key')->nullable();
            $table->text('meta_value')->nullable();
            $table->timestamps();

            $table->foreign('buyer_agent_auction_bid_id')
                  ->references('id')
                  ->on('buyer_agent_auction_bids')
                  ->onDelete('cascade');

            $table->index(['buyer_agent_auction_bid_id', 'meta_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('buyer_agent_auction_bid_metas');
    }
}
