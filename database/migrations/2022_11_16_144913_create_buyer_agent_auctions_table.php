<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBuyerAgentAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('buyer_agent_auctions', function (Blueprint $table)
        {
            $table->id();
            $table->bigInteger('user_id');
            $table->string('address');
            $table->string('title');
            $table->string('auction_type')->nullable();
            $table->integer('auction_length')->nullable();
            $table->bigInteger('city_id')->nullable();
            $table->bigInteger('county_id')->nullable();
            $table->bigInteger('state_id')->nullable();
            $table->bigInteger('bathroom_id')->nullable();
            $table->bigInteger('bedroom_id')->nullable();
            $table->bigInteger('property_type_id')->nullable();
            $table->double('concession')->nullable();
            $table->json('financing_currency')->nullable();
            $table->string('financing_approved')->nullable();
            $table->string('need_lender')->nullable();
            $table->double('preapproval_amount')->nullable();

            $table->text('additional_details')->nullable(); //
            $table->json('other')->nullable(); //
            $table->string('cash_budget')->nullable(); //
            $table->string('crypto_budget')->nullable(); //

            $table->string('is_approved')->default(0);
            $table->string('is_sold')->default(0);
            $table->string('is_paid')->default(0);
            $table->timestamp('sold_date')->nullable();
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
        Schema::dropIfExists('buyer_agent_auctions');
    }
}
