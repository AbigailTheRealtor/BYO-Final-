<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChangeAcceptedColumnInBuyerAgentAuctionBidsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('buyer_agent_auction_bids')) {
            DB::statement("ALTER TABLE buyer_agent_auction_bids ALTER COLUMN accepted TYPE varchar(10) USING CASE WHEN accepted = true THEN 'accepted' ELSE '0' END");
            DB::statement("ALTER TABLE buyer_agent_auction_bids ALTER COLUMN accepted SET DEFAULT '0'");
        }
    }

    public function down()
    {
        if (Schema::hasTable('buyer_agent_auction_bids')) {
            DB::statement("ALTER TABLE buyer_agent_auction_bids ALTER COLUMN accepted TYPE boolean USING CASE WHEN accepted = 'accepted' THEN true ELSE false END");
            DB::statement("ALTER TABLE buyer_agent_auction_bids ALTER COLUMN accepted SET DEFAULT false");
        }
    }
}
