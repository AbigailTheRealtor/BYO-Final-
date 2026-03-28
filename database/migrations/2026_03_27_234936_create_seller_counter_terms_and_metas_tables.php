<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellerCounterTermsAndMetasTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seller_counter_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('seller_agent_auction_bid_id');
            $table->unsignedBigInteger('seller_agent_auction_id');
            $table->string('property_type')->nullable();
            $table->unsignedBigInteger('parent_counter_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('seller_counter_term_metas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('counter_term_id');
            $table->string('meta_key');
            $table->longText('meta_value')->nullable();
            $table->timestamps();
            $table->index(['counter_term_id', 'meta_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('seller_counter_term_metas');
        Schema::dropIfExists('seller_counter_terms');
    }
}
