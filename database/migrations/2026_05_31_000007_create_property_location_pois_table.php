<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyLocationPoisTable extends Migration
{
    public function up()
    {
        Schema::create('property_location_pois', function (Blueprint $table) {
            $table->id();

            $table->string('listing_type');
            $table->unsignedBigInteger('listing_id');

            $table->string('poi_category');
            $table->string('poi_subtype')->nullable();
            $table->string('poi_name')->nullable();
            $table->text('poi_address')->nullable();
            $table->decimal('poi_lat', 10, 7)->nullable();
            $table->decimal('poi_lng', 10, 7)->nullable();

            $table->decimal('source_lat', 10, 7)->nullable();
            $table->decimal('source_lng', 10, 7)->nullable();

            $table->decimal('distance_miles', 8, 4)->nullable();
            $table->unsignedSmallInteger('travel_time_minutes')->nullable();

            $table->string('data_source')->nullable();
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('calculated_at')->nullable();

            $table->timestamps();

            $table->unique(['listing_type', 'listing_id', 'poi_category']);
            $table->index(['listing_type', 'listing_id']);
            $table->index('poi_category');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('property_location_pois');
    }
}
