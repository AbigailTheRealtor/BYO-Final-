<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->bigInteger('seller_id')->nullable();
            $table->string('title');
            $table->text('address');
            $table->bigInteger('city_id');
            $table->bigInteger('state_id');
            $table->text('description')->nullable();
            $table->text('keywords')->nullable();
            $table->bigInteger('bedroom_id')->nullable();
            $table->bigInteger('bathroom_id')->nullable();
            // $table->enum('auction_type', ["Seller's Property", "Buyer's Criteria","Agent Service Needed","Hire Seller's Agent", "Hire Buyer's Agent"]);
            $table->enum('autcion_type',['Normal','Traditional'])->nullable();
            $table->string('auction_length')->nullable();
            $table->float('starting_price',18,2)->nullable();
            $table->float('buy_now_price',18,2)->nullable();
            $table->float('reserve_price',18,2)->nullable();
            $table->string('heated_sqft')->nullable();
            $table->string('year_built')->nullable();
            $table->bigInteger('county_id')->nullable();
            // $table->json('air_conditioning_types');
            $table->bigInteger('heating_fuel_id')->nullable();
            $table->string('sale_provision')->nullable();#
            $table->string('service_type')->nullable();#
            $table->enum('pool',['Yes', 'No'])->nullable();
            $table->string('pool_type')->nullable();#
            $table->enum('carport', ['Yes', 'No'])->nullable();
            $table->enum('garage', ['Yes', 'No'])->nullable();
            $table->enum('garage_spaces',['1','2','3','4','5','6','7','8','9','10+'])->nullable();
            $table->enum('water_view', ['Yes', 'No'])->nullable();
            $table->enum('water_extras', ['Yes', 'No'])->nullable();
            $table->enum('hoa_association', ['Yes', 'No'])->nullable();
            $table->text('hoa_manager_contact')->nullable();
            $table->enum('hoa_fee_requirement', ['Required', 'Optional', 'No'])->nullable();
            $table->string('hoa_fee')->nullable();
            $table->string('hoa_payment_schedule')->nullable();
            $table->string('condo_fee')->nullable();
            $table->string('condo_fee_schedule')->nullable();
            // $table->string('fee_includes'); ////
            $table->enum('old_community',['Yes','No'])->nullable();
            $table->enum('rental_restrictions',['Yes','No'])->nullable();
            $table->text('rental_restrictions_desription')->nullable();
            $table->enum('pets_allowed',['Yes','No'])->nullable();
            $table->enum('number_of_pets_allowed',['1','2','3','4','5','6','7','8','9','10+'])->nullable();
            $table->string('max_pet_weight')->nullable();
            $table->text('pet_restrictions')->nullable();
            $table->string('mls_id')->nullable();
            $table->enum('is_in_flood_zone',['Yes','No'])->nullable();
            $table->string('flood_zone_code')->nullable();
            $table->integer('number_of_floors_in_unit')->nullable();
            $table->integer('total_number_of_floors')->nullable();
            $table->enum('elevator', ['Yes','No'])->nullable();

            // Please fill out this section only if you have an income/commercial property
            $table->text('property_info')->nullable();
            $table->integer('number_of_buildings')->nullable();
            $table->integer('number_of_units')->nullable();
            $table->string('unit_sizes')->nullable();
            $table->string('sqft_for_each_unit')->nullable();
            $table->text('occupied_vacant_info')->nullable();
            $table->text('current_rental_amount')->nullable();
            $table->string('expected_rental_amount')->nullable();
            $table->string('anual_gross_income')->nullable();
            $table->string('anual_expense')->nullable();
            $table->string('anual_net_income')->nullable();
            $table->string('total_monthly_rent')->nullable();
            $table->string('lease_terms')->nullable();
            $table->string('tenant_pays')->nullable();
            $table->string('landlord_pays')->nullable();


            // Seller’s Agent Info
            $table->string('seller_name')->nullable();
            $table->string('brokerage')->nullable();
            $table->string('license_number')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->string('seller_agent_mls_id')->nullable();


            $table->enum('looking_another_property',['Yes','No'])->nullable();
            $table->date('closing_date')->nullable();
            $table->integer('step')->default(1);
            $table->boolean('is_approved')->default(false);
            $table->boolean('sold')->default(false);
            $table->boolean('is_paid')->default(false);
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
        Schema::dropIfExists('property_auctions');
    }
}
