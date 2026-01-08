<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellerAgentAuctionMetasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seller_agent_auction_metas', function (Blueprint $table) {
            $table->id();
            $table->integer('seller_agent_auction_id');
            $table->string('meta_key');
            $table->text('meta_value')->nullable();
            $table->timestamps();

            $table->index('seller_agent_auction_id');
            $table->index('meta_key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('seller_agent_auction_metas');
    }
}
