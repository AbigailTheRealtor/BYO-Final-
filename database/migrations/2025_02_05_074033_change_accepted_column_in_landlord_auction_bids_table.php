<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeAcceptedColumnInLandlordAuctionBidsTable extends Migration
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
                $table->integer('accepted')->change();
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
        if (Schema::hasTable('landlord_auction_bids')) {
            Schema::table('landlord_auction_bids', function (Blueprint $table) {
                $table->boolean('accepted')->change();
            });
        }
    }
}
