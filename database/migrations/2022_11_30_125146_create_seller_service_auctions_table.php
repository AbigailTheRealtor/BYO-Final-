<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellerServiceAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seller_service_auctions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->string('auction_type')->nullable();
            $table->string('auction_length')->nullable();
            $table->json('services')->nullable();
            $table->text('description')->nullable();
            $table->bigInteger('city_id')->nullable();
            $table->bigInteger('county_id')->nullable();
            $table->timestamp('required_at')->nullable();
            $table->double('price')->nullable();
            $table->string('seller_name')->nullable();
            $table->string('seller_phone')->nullable();
            $table->string('seller_email')->nullable();
            $table->text('private_notes')->nullable();
            $table->text('meeting_info')->nullable();
            $table->text('address')->nullable();
            $table->string('video')->nullable();
            $table->string('audio')->nullable();
            $table->string('note')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_sold')->default(false);
            $table->timestamp('sold_date')->nullable();
            $table->boolean('is_paid')->default(false);
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
        Schema::dropIfExists('seller_service_auctions');
    }
}
