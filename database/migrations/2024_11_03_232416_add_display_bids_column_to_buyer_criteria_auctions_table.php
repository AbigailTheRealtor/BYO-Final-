<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDisplayBidsColumnToBuyerCriteriaAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('buyer_criteria_auctions') && !Schema::hasColumn('buyer_criteria_auctions', 'display_bids')) {
            Schema::table('buyer_criteria_auctions', function (Blueprint $table) {
                $table->boolean('display_bids')->after('id')->default(true);
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
        if (Schema::hasTable('buyer_criteria_auctions') && Schema::hasColumn('buyer_criteria_auctions', 'display_bids')) {
            Schema::table('buyer_criteria_auctions', function (Blueprint $table) {
                $table->dropColumn('display_bids');
            });
        }
    }
}
