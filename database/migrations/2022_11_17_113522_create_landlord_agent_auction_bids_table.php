<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLandlordAgentAuctionBidsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('landlord_agent_auction_bids')) {
            return;
        }

        Schema::create('landlord_agent_auction_bids', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('landlord_agent_auction_id')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->integer('accepted')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('landlord_agent_auction_bids');
    }
}
