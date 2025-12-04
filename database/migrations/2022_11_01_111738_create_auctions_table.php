<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('address');
            $table->bigInteger('city_id');
            $table->bigInteger('state_id');
            // $table->json('property_types');
            // $table->string('location_title');
            // $table->string('location_county');
            // $table->string('location_city');
            // $table->json('location');
            $table->text('description');
            $table->text('keywords');
            $table->foreignId('bedroom_id')->constrained('bedrooms')->onDelete('cascade');
            $table->foreignId('half_bathroom_id')->constrained('half_bathrooms')->onDelete('cascade');
            $table->foreignId('bathrooms')->constrained('bathrooms')->onDelete('cascade');
            $table->foreignId('auction_type_id')->constrained('auction_types')->onDelete('cascade');
            // $table->enum('auction_type', ["Seller's Property", "Buyer's Criteria","Agent Service Needed","Hire Seller's Agent", "Hire Buyer's Agent"]);
            $table->enum('autcion_type',['Normal','Traditional']);
            $table->string('auction_length');
            $table->timestamp('expirs_at');
            $table->double('starting_price');
            $table->double('buy_now_price');
            $table->double('reserve_price');
            $table->string('heated_sqft');
            $table->string('year_built');
            $table->bigInteger('county_id');
            $table->boolean('is_buyer_pre_approved');
            $table->double('buyer_pre_approval_amount');
            // $table->json('air_conditioning_types');
            $table->bigInteger('heating_fuel_id');
            $table->enum('pool',['N/A', 'Yes', 'No']);
            $table->enum('carport', ['Yes', 'No']);
            $table->enum('garage', ['Yes', 'No']);
            $table->enum('garage_spaces',['1','2','3','4','5','6','7','8','9','10+']);
            $table->enum('water_view', ['Yes', 'No']);
            $table->enum('water_extras', ['Yes', 'No']);
            $table->enum('hoa_association', ['Yes', 'No']);
            $table->text('hoa_contact');
            $table->enum('hoa_fee_requirement', ['Required', 'Optional', 'No']);
            $table->string('hoa_fee');
            $table->string('hoa_payment_schedule');
            $table->enum('condo_fee_requirement',['Yes','No','No Preference']);
            $table->string('condo_fee');
            $table->string('condo_fee_schedule');
            $table->string('fee_includes');
            $table->string('old_community');
            $table->enum('rental_restrictions',['Yes','No']);
            $table->text('rental_restrictions_desription');
            $table->enum('pets_allowed',['Yes','No','No Preference']);
            $table->string('buyer_total_pets');
            $table->string('buyer_pet_breed');
            $table->string('buyer_pet_weight');
            $table->text('buyer_rental_requirements');
            $table->integer('number_of_units_needed');
            $table->string('anual_net_income_minimum');
            $table->string('minimum_cap_rate');
            $table->text('buyer_additional_detail');
            $table->string('buyer_name');
            $table->string('buyer_brokerage');
            $table->string('buyer_license_number');
            $table->string('buyer_phone');
            $table->string('buyer_email');
            $table->string('buyer_mls_id');
            $table->string('number_of_pets_allowed');
            $table->string('max_pet_weight');
            $table->text('pet_restrictions');
            $table->string('mls_id');
            $table->enum('is_in_flood_zone',['Yes','No']);
            $table->string('flood_zone_code');
            $table->integer('number_of_floors_in_unit');
            $table->integer('total_number_of_floors');
            $table->enum('elevator', ['Yes','No']);
            $table->string('seller_name');
            $table->string('brokerage');
            $table->string('license_number');
            $table->string('phone_number');
            $table->string('email');
            $table->string('seller_agent_mls_id');
            $table->text('property_info');
            $table->integer('number_of_buildings');
            $table->integer('number_of_units');
            $table->string('unit_sizes');
            $table->string('sqft_for_each_unit');
            $table->text('occupied_vacant_info');
            $table->text('current_rental_amount');
            $table->string('expected_rental_amount');
            $table->string('anual_income');
            $table->string('anual_expense');
            $table->string('anual_net_income');
            $table->string('total_monthly_rent');
            $table->string('lease_terms');
            $table->string('tenant_pays');
            $table->string('landlord_pays');
            $table->timestamp('closing_date');
            $table->boolean('is_approved');
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
        Schema::dropIfExists('auctions');
    }
}
