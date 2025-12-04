<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAuctionEndedColumnInLandlordAgentAuctionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('landlord_agent_auctions', function (Blueprint $table) {
            $table->boolean('auction_ended')->after('auction_type')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('landlord_agent_auctions', function (Blueprint $table) {
            $table->dropColumn('auction_ended');
        });
    }
}
