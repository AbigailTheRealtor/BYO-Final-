<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBiddingPeriodAgentMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bidding_period_agent_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auction_id');
            $table->string('auction_type', 50)->default('tenant_agent');
            $table->unsignedBigInteger('agent_user_id');
            $table->integer('anonymous_number');
            $table->timestamps();
            
            $table->unique(['auction_id', 'auction_type', 'agent_user_id'], 'unique_auction_agent');
            $table->unique(['auction_id', 'auction_type', 'anonymous_number'], 'unique_auction_anon_number');
            $table->index(['auction_id', 'auction_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bidding_period_agent_mappings');
    }
}
