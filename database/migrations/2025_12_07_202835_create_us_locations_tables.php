<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsLocationsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('us_states', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('abbreviation', 2);
            $table->string('fips_code', 2)->nullable();
            $table->timestamps();
            
            $table->index('name');
            $table->index('abbreviation');
            $table->unique('abbreviation');
        });

        Schema::create('us_counties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('fips_code', 5)->nullable();
            $table->unsignedBigInteger('state_id');
            $table->timestamps();
            
            $table->foreign('state_id')->references('id')->on('us_states')->onDelete('cascade');
            $table->index('name');
            $table->index(['state_id', 'name']);
        });

        Schema::create('us_cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('fips_code', 7)->nullable();
            $table->unsignedBigInteger('state_id');
            $table->unsignedBigInteger('county_id')->nullable();
            $table->timestamps();
            
            $table->foreign('state_id')->references('id')->on('us_states')->onDelete('cascade');
            $table->foreign('county_id')->references('id')->on('us_counties')->onDelete('set null');
            $table->index('name');
            $table->index(['state_id', 'name']);
        });

        Schema::create('auction_location_selections', function (Blueprint $table) {
            $table->id();
            $table->string('auction_type', 50);
            $table->unsignedBigInteger('auction_id');
            $table->string('location_type', 20);
            $table->unsignedBigInteger('location_id');
            $table->timestamps();
            
            $table->index(['auction_type', 'auction_id']);
            $table->index(['location_type', 'location_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('auction_location_selections');
        Schema::dropIfExists('us_cities');
        Schema::dropIfExists('us_counties');
        Schema::dropIfExists('us_states');
    }
}
