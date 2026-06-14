<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBuyerCriteriaAuctionMetasTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('buyer_criteria_auction_metas')) {
            Schema::create('buyer_criteria_auction_metas', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('buyer_criteria_auction_id');
                $table->string('meta_key');
                $table->text('meta_value')->nullable();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('buyer_criteria_auction_metas');
    }
}
