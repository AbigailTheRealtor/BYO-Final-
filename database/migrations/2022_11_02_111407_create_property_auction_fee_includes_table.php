<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyAuctionFeeIncludesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_auction_fee_includes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_auction_id')->constrained('property_auctions')->onDelete('cascade');
            $table->foreignId('fee_include_id')->constrained('fee_includes')->onDelete('cascade');
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
        Schema::dropIfExists('property_auction_fee_includes');
    }
}
