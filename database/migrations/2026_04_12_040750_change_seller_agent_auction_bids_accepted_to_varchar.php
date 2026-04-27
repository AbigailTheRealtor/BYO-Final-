<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChangeSellerAgentAuctionBidsAcceptedToVarchar extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seller_agent_auction_bids')) {
            // Convert existing boolean values to string equivalents before changing type
            DB::statement("
                ALTER TABLE seller_agent_auction_bids
                ALTER COLUMN accepted TYPE varchar(10)
                USING CASE
                    WHEN accepted IS TRUE  THEN 'accepted'
                    WHEN accepted IS FALSE THEN '0'
                    ELSE '0'
                END
            ");

            // Set new default
            DB::statement("ALTER TABLE seller_agent_auction_bids ALTER COLUMN accepted SET DEFAULT '0'");
        }
    }

    public function down()
    {
        if (Schema::hasTable('seller_agent_auction_bids')) {
            // Revert: convert string back to boolean (accepted→true, everything else→false)
            DB::statement("
                ALTER TABLE seller_agent_auction_bids
                ALTER COLUMN accepted TYPE boolean
                USING CASE
                    WHEN accepted = 'accepted' THEN TRUE
                    ELSE FALSE
                END
            ");
            DB::statement("ALTER TABLE seller_agent_auction_bids ALTER COLUMN accepted SET DEFAULT FALSE");
        }
    }
}
