<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBuyerCriteriaAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('buyer_criteria_auctions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->bigInteger('buyer_id');
            $table->double('max_price');
            $table->string('title');//
            $table->string('description')->nullable();//
            // $table->string('cash_budget')->nullable();//
            // $table->string('crypto_budget')->nullable();//
            $table->string('auction_type')->nullable();
            $table->integer('auction_length')->nullable();
            $table->bigInteger('property_type_id')->nullable();
            $table->string('bedrooms')->nullable();
            $table->string('bathrooms')->nullable();
            $table->string('sqft')->nullable();
            // $table->string('counties');
            // $table->string('cities');
            $table->bigInteger('state_id')->nullable();
            $table->bigInteger('financing_id')->nullable();
            $table->string('loan_pre_approved')->nullable();
            $table->string('preapproval_amount')->nullable();
            $table->string('pool')->nullable();
            $table->string('carport')->nullable();
            $table->string('garage')->nullable();
            $table->string('garage_spaces')->nullable();
            $table->string('water_view')->nullable();
            // $table->string('water_view');
            $table->string('water_extra')->nullable();
            // $table->string('water_extras');
            $table->string('hoa')->nullable();
            $table->string('hoa_fee_requirement')->nullable();
            $table->string('max_hoa_fee')->nullable();
            $table->string('condo')->nullable();
            $table->string('max_condo_fee')->nullable();
            $table->string('old_community')->nullable();
            $table->string('pets_allowed')->nullable();
            $table->text('pets_detail')->nullable();
            $table->string('number_of_pets')->nullable();
            $table->string('pets_breed')->nullable();
            $table->string('pets_weight')->nullable();

            // If Buyer is an investor looking to purchase an investment property, please fill out the following
            $table->text('rental_requirements')->nullable();
            $table->string('units_needed')->nullable();
            $table->string('anual_income')->nullable();
            $table->string('min_cap_rate')->nullable();
            $table->text('additional_details')->nullable();

            // Buyer/Buyer’s Agent Info
            $table->string('buyer_name')->nullable();
            $table->string('buyer_brokerage')->nullable();
            $table->string('buyer_license_no')->nullable();
            $table->string('buyer_phone')->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('buyer_mls_id')->nullable();

            // Terms offered by Buyer
            $table->string('escrow_amount_in')->nullable()->default('$');
            $table->double('escrow_amount')->nullable();
            $table->string('inspection_period')->nullable();
            $table->string('closing_days')->nullable();
            $table->string('contingencies')->nullable();
            $table->string('seller_premium_in')->nullable();
            $table->double('seller_premium')->nullable();
            $table->string('buyer_premium_in')->nullable();
            $table->double('buyer_premium')->nullable();

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
        Schema::dropIfExists('buyer_criteria_auctions');
    }
}
