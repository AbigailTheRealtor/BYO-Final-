<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentServiceAuctionBidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agent_service_auction_bids', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('agent_service_auction_id');
            $table->bigInteger('user_id');
            $table->string('name')->nullable();
            $table->string('brokerage')->nullable();
            $table->string('license_no')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('mls_id')->nullable();
            $table->double('price_in')->nullable();
            $table->double('price')->nullable();
            $table->string('additional_details')->nullable();
            $table->string('card')->nullable();
            $table->string('video')->nullable();
            $table->string('video_url')->nullable();#
            $table->string('audio')->nullable();
            $table->string('note')->nullable();
            $table->boolean('accepted')->default(false);
            $table->timestamp('accepted_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('agent_service_auction_bids');
    }
}
