<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellerAgentAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seller_agent_auctions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->string('address');
            $table->string('auction_type')->nullable();
            $table->integer('auction_length')->nullable();
            $table->bigInteger('city_id')->nullable();
            $table->bigInteger('county_id')->nullable();
            $table->bigInteger('state_id')->nullable();
            $table->bigInteger('bathroom_id')->nullable();
            $table->bigInteger('bedroom_id')->nullable();
            $table->string('sqft')->nullable();
            $table->double('min_price')->nullable();
            $table->double('max_commission')->nullable();
            $table->json('financings')->nullable();#
            $table->text('additional_services')->nullable();#
            $table->text('important_info')->nullable();#
            $table->text('contract_terms')->nullable();
            $table->text('description')->nullable();
            $table->text('prop_condition')->nullable();
            $table->text('description_ideal_agent')->nullable();
            $table->string('need_cma')->nullable();
            $table->json('photos')->nullable();#
            $table->string('video_url')->nullable();
            $table->string('video_file')->nullable();
            $table->string('audio_file')->nullable();
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
        Schema::dropIfExists('seller_agent_auctions');
    }
}
