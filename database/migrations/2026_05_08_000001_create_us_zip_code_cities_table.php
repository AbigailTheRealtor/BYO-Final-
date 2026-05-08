<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsZipCodeCitiesTable extends Migration
{
    public function up()
    {
        Schema::create('us_zip_code_cities', function (Blueprint $table) {
            $table->id();
            $table->string('zip_code', 10)->index();
            $table->string('city', 100);
            $table->string('state_abbrev', 2)->index();
            $table->string('county', 100)->nullable();
            $table->timestamps();

            $table->unique(['zip_code', 'city', 'state_abbrev'], 'us_zip_code_cities_unique');
            $table->index(['city', 'state_abbrev'], 'us_zip_code_cities_city_state_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('us_zip_code_cities');
    }
}
