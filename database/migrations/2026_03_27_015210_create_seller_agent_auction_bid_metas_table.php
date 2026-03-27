<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellerAgentAuctionBidMetasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seller_agent_auction_bid_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_agent_auction_bid_id')
                  ->constrained('seller_agent_auction_bids')
                  ->onDelete('cascade');
            $table->string('meta_key');
            $table->text('meta_value')->nullable();
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
        Schema::dropIfExists('seller_agent_auction_bid_metas');
    }
}
