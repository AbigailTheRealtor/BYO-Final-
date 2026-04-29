<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserIdToSellerAgentAuctionBidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('seller_agent_auction_bids') && ! Schema::hasColumn('seller_agent_auction_bids', 'user_id')) {
            Schema::table('seller_agent_auction_bids', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('seller_agent_auction_bids') && Schema::hasColumn('seller_agent_auction_bids', 'user_id')) {
            Schema::table('seller_agent_auction_bids', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }
}
