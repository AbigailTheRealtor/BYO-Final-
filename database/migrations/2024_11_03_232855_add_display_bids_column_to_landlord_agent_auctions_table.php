<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDisplayBidsColumnToLandlordAgentAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('landlord_agent_auctions')) {
            Schema::table('landlord_agent_auctions', function (Blueprint $table) {
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
        Schema::table('landlord_agent_auctions', function (Blueprint $table) {
            $table->dropColumn('display_bids');
        });
    }
}
