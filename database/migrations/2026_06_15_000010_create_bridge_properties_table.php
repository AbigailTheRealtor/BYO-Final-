<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgePropertiesTable extends Migration
{
    public function up()
    {
        Schema::create('bridge_properties', function (Blueprint $table) {
            $table->id();
            $table->string('listing_key')->unique()->nullable();
            $table->string('listing_id')->nullable();
            $table->string('standard_status')->nullable();
            $table->string('property_type')->nullable();
            $table->decimal('list_price', 15, 2)->nullable();
            $table->string('unparsed_address')->nullable();
            $table->string('city')->nullable();
            $table->string('state_or_province')->nullable();
            $table->string('postal_code')->nullable();
            $table->integer('bedrooms_total')->nullable();
            $table->integer('bathrooms_total_integer')->nullable();
            $table->integer('living_area')->nullable();
            $table->timestamp('modification_timestamp')->nullable();
            $table->longText('raw_json')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bridge_properties');
    }
}
