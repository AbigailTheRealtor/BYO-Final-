<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantCriteriaAuctionBidsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('tenant_criteria_auction_bids')) {
            Schema::create('tenant_criteria_auction_bids', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('tenant_criteria_auction_id');
                $table->integer('counter_id')->nullable();
                $table->bigInteger('user_id');
                $table->boolean('is_accepted')->default(false);
                $table->timestamp('accepted_date')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('tenant_criteria_auction_bids');
    }
}
