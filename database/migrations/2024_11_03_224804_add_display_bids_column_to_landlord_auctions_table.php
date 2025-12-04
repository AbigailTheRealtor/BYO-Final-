<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDisplayBidsColumnToLandlordAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('landlord_auctions', function (Blueprint $table) {
            $table->boolean('display_bids')->after('id')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('landlord_auctions', function (Blueprint $table) {
            $table->dropColumn('display_bids');
        });
    }
}
