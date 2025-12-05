<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCounterIdColumnInBuyerCriteriaAuctionBidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('buyer_criteria_auction_bids', function (Blueprint $table) {
            $table->integer('counter_id')->after('buyer_criteria_auction_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('buyer_criteria_auction_bids', function (Blueprint $table) {
            $table->dropColumn('counter_id');
        });
    }
}
