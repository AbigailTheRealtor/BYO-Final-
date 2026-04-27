<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAcceptedDateToLandlordAgentAuctionBids extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('landlord_agent_auction_bids')) {
            Schema::table('landlord_agent_auction_bids', function (Blueprint $table) {
                $table->timestamp('accepted_date')->nullable()->after('accepted');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('landlord_agent_auction_bids')) {
            Schema::table('landlord_agent_auction_bids', function (Blueprint $table) {
                $table->dropColumn('accepted_date');
            });
        }
    }
}
