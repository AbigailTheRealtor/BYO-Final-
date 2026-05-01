<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLandlordAgentAuctionMetasTable extends Migration
{
    public function up()
    {
        Schema::create('landlord_agent_auction_metas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('landlord_agent_auction_id')->nullable();
            $table->string('meta_key')->nullable();
            $table->text('meta_value')->nullable();
            $table->timestamps();

            $table->foreign('landlord_agent_auction_id')
                  ->references('id')
                  ->on('landlord_agent_auctions')
                  ->onDelete('cascade');

            $table->index(['landlord_agent_auction_id', 'meta_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('landlord_agent_auction_metas');
    }
}
