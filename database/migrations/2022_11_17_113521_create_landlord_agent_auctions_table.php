<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLandlordAgentAuctionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('landlord_agent_auctions')) {
            return;
        }

        Schema::create('landlord_agent_auctions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->string('auction_type')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_draft')->default(false);
            $table->boolean('is_sold')->default(false);
            $table->timestamp('sold_date')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('landlord_agent_auctions');
    }
}
