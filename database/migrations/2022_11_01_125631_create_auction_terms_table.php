<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuctionTermsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auction_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('auctions')->onDelete('cascade');
            $table->double('escrow_amount');
            $table->string('inspection_perion');
            $table->string('buyer_agent_commission');
            $table->string('buyer_premium');
            $table->string('seller_premium');
            $table->string('success_fee_to_be_paid_by');
            $table->string('additional_remarks');
            $table->timestamp('property_list_date');
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
        Schema::dropIfExists('auction_terms');
    }
}
