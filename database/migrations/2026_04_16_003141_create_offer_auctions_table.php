<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOfferAuctionsTable extends Migration
{
    public function up()
    {
        Schema::create('offer_auctions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('listing_id')->unique()->nullable();
            $table->string('title')->nullable();
            $table->boolean('is_draft')->default(true);
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_sold')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('offer_auctions');
    }
}
