<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantAgentAuctionBidMetasTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('tenant_agent_auction_bid_metas')) {
            return;
        }

        Schema::create('tenant_agent_auction_bid_metas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tenant_agent_auction_bid_id')->nullable();
            $table->string('meta_key')->nullable();
            $table->longText('meta_value')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tenant_agent_auction_bid_metas');
    }
}
