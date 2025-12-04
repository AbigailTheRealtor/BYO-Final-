<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAuctionLengthAuctionTypeAndAuctionEndedColumnsInPropertyAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('property_auctions', function (Blueprint $table) {
            $table->string('auction_type')->after('display_bids');
            $table->integer('auction_length')->after('auction_type')->default(-1);
            $table->boolean('auction_ended')->after('auction_length')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('property_auctions', function (Blueprint $table) {
            $table->dropColumn('auction_type');
            $table->dropColumn('auction_length');
            $table->dropColumn('auction_ended');
        });
    }
}
