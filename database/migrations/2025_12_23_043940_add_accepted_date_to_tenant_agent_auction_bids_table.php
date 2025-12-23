<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAcceptedDateToTenantAgentAuctionBidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tenant_agent_auction_bids', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_agent_auction_bids', 'accepted_date')) {
                $table->timestamp('accepted_date')->nullable();
            }
            if (!Schema::hasColumn('tenant_agent_auction_bids', 'rejected_date')) {
                $table->timestamp('rejected_date')->nullable();
            }
            if (!Schema::hasColumn('tenant_agent_auction_bids', 'countered_date')) {
                $table->timestamp('countered_date')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tenant_agent_auction_bids', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_agent_auction_bids', 'accepted_date')) {
                $table->dropColumn('accepted_date');
            }
            if (Schema::hasColumn('tenant_agent_auction_bids', 'rejected_date')) {
                $table->dropColumn('rejected_date');
            }
            if (Schema::hasColumn('tenant_agent_auction_bids', 'countered_date')) {
                $table->dropColumn('countered_date');
            }
        });
    }
}
