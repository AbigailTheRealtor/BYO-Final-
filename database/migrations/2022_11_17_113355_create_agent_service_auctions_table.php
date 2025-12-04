<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentServiceAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agent_service_auctions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->string('auction_type')->nullable();
            $table->integer('auction_length')->nullable();
            $table->bigInteger('service_id')->nullable();
            $table->text('description')->nullable();
            $table->bigInteger('city_id')->nullable();
            $table->bigInteger('county_id')->nullable();
            $table->bigInteger('state_id')->nullable();
            $table->timestamp('required_at')->nullable();
            $table->text('public_notes')->nullable();
            $table->string('min_price_in')->nullable();
            $table->double('min_price')->nullable();
            // $table->double('min_price_percent')->nullable();
            $table->string('agent_name')->nullable();
            $table->string('agent_brokerage')->nullable();
            $table->string('agent_license_no')->nullable();
            $table->string('agent_phone')->nullable();
            $table->string('agent_email')->nullable();
            $table->string('agent_mls_id')->nullable();
            $table->text('private_notes')->nullable();
            $table->text('meeting_info')->nullable();
            $table->text('address')->nullable();
            $table->string('mls_id')->nullable();
            $table->string('mls_listing_link')->nullable();
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
        Schema::dropIfExists('agent_service_auctions');
    }
}
