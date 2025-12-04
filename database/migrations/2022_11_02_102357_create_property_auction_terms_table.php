<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyAuctionTermsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_auction_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_auction_id')->constrained('property_auctions')->onDelete('cascade');
            $table->double('escrow_amount')->nullable();
            $table->string('inspection_perion')->nullable();
            $table->string('buyer_agent_commission')->nullable();
            $table->string('buyer_premium')->nullable();
            $table->string('seller_premium')->nullable();
            $table->string('success_fee_to_be_paid_by')->nullable();
            $table->string('additional_remarks')->nullable();
            $table->date('property_list_date')->nullable();
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
        Schema::dropIfExists('property_auction_terms');
    }
}
