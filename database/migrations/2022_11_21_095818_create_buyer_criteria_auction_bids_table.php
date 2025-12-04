<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBuyerCriteriaAuctionBidsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('buyer_criteria_auction_bids', function (Blueprint $table) {
            $table->id();#
            $table->bigInteger('buyer_criteria_auction_id');#
            $table->bigInteger('user_id');#
            $table->double('price')->nullable();#
            $table->json('financings')->nullable();#
            $table->string('escrow_amount_in')->nullable()->default('$');
            $table->double('escrow_amount')->nullable();#
            $table->string('inspection_period')->nullable();#
            $table->string('closing_days')->nullable();#
            $table->string('contingencies')->nullable();#
            $table->double('commission_percent')->nullable();#
            $table->string('seller_premium_in')->nullable();#
            $table->double('seller_premium')->nullable();#
            $table->string('buyer_premium_in')->nullable();#
            $table->double('buyer_premium')->nullable();#
            $table->bigInteger('county_id')->nullable();#
            $table->bigInteger('city_id')->nullable();#
            $table->bigInteger('property_type_id')->nullable();#
            $table->string('bedrooms')->nullable();#
            $table->string('bathrooms')->nullable();#
            $table->string('sqft')->nullable();#
            $table->string('flood_insurance')->nullable();#
            $table->text('description')->nullable();#
            // Fill out if Seller is picking Title company:
            $table->string('title_company')->nullable();#
            $table->string('title_agent')->nullable();#
            $table->string('title_company_phone')->nullable();#
            $table->string('title_company_email')->nullable();#
            // Fill out if property is an income property:
            $table->string('total_units')->nullable();#
            $table->string('unit_sizes')->nullable();#
            $table->double('annual_income')->nullable();#
            $table->double('cap_rate')->nullable();#
            $table->string('tenant_pays')->nullable();#
            $table->string('landlord_pays')->nullable();#

            $table->string('card')->nullable();#
            $table->string('address')->nullable();#
            $table->string('listing_link')->nullable();#

            // $table->date('close_date')->nullable();
            // $table->string('is_listed')->nullable();
            // $table->json('photos')->nullable();

                ################################
            // $table->string('name')->nullable();
            // $table->string('phone')->nullable();
            // $table->string('email')->nullable();
            // $table->string('brokerage')->nullable();
            // $table->string('license_no')->nullable();
            $table->string('video')->nullable();#
            $table->string('video_url')->nullable();#
            $table->string('audio')->nullable();#
            $table->string('letter')->nullable();#
                ##########################
            $table->boolean('is_accepted')->default(false);#
            $table->timestamp('accepted_date')->nullable();#
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
        Schema::dropIfExists('buyer_criteria_auction_bids');
    }
}
