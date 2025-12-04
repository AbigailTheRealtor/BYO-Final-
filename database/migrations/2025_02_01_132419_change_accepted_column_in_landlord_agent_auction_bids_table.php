<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeAcceptedColumnInLandlordAgentAuctionBidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('landlord_agent_auction_bids', function (Blueprint $table) {
            $table->integer('accepted')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('landlord_agent_auction_bids', function (Blueprint $table) {
            $table->boolean('accepted')->change();
        });
    }
}
