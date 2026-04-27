<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantAgentAuctionBidsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('tenant_agent_auction_bids')) {
            return;
        }

        Schema::create('tenant_agent_auction_bids', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tenant_agent_auction_id')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->string('accepted')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tenant_agent_auction_bids');
    }
}
