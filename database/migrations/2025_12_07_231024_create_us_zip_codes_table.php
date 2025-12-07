<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsZipCodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('us_zip_codes', function (Blueprint $table) {
            $table->id();
            $table->string('zip_code', 10)->index();
            $table->string('city', 100);
            $table->string('state_abbrev', 2)->index();
            $table->string('state_name', 50);
            $table->string('county', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->timestamps();
            
            $table->index(['city', 'state_abbrev']);
            $table->unique('zip_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('us_zip_codes');
    }
}
