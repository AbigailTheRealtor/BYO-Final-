<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCounterIdInLandlordAuctionBidTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('landlord_auction_bids')) {
            Schema::table('landlord_auction_bids', function (Blueprint $table) {
                $table->integer('counter_id')->after('landlord_auction_id')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('landlord_auction_bids') && Schema::hasColumn('landlord_auction_bids', 'counter_id')) {
            Schema::table('landlord_auction_bids', function (Blueprint $table) {
                $table->dropColumn('counter_id');
            });
        }
    }
}
