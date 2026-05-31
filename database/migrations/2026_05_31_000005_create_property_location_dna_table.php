<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyLocationDnaTable extends Migration
{
    public function up()
    {
        Schema::create('property_location_dna', function (Blueprint $table) {
            $table->id();

            $table->string('listing_type');
            $table->unsignedBigInteger('listing_id');

            $table->string('source_address')->nullable();
            $table->string('source_city')->nullable();
            $table->string('source_county')->nullable();
            $table->string('source_state')->nullable();
            $table->string('source_zip')->nullable();

            $table->decimal('geocoded_lat', 10, 7)->nullable();
            $table->decimal('geocoded_lng', 10, 7)->nullable();
            $table->string('geocode_source')->nullable();
            $table->string('geocode_status')->default('pending');
            $table->text('geocode_error')->nullable();
            $table->timestamp('geocoded_at')->nullable();

            $table->json('summary_json')->nullable();
            $table->timestamp('generated_at')->nullable();

            $table->timestamps();

            $table->unique(['listing_type', 'listing_id']);
            $table->index('geocode_status');
            $table->index('geocoded_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('property_location_dna');
    }
}
