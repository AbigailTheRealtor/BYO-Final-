<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCounterIdColumnInTenantCriteriaAuctionBidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('tenant_criteria_auction_bids')) {
            Schema::table('tenant_criteria_auction_bids', function (Blueprint $table) {
                $table->integer('counter_id')->after('tenant_criteria_auction_id')->nullable();
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
        if (Schema::hasTable('tenant_criteria_auction_bids')) {
            Schema::table('tenant_criteria_auction_bids', function (Blueprint $table) {
                $table->dropColumn('counter_id');
            });
        }
    }
}
