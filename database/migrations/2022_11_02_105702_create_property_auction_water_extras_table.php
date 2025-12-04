<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyAuctionWaterExtrasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_auction_water_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_auction_id')->constrained('property_auctions')->onDelete('cascade');
            $table->foreignId('water_extra_id')->constrained('water_extras')->onDelete('cascade');
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
        Schema::dropIfExists('property_auction_water_extras');
    }
}
