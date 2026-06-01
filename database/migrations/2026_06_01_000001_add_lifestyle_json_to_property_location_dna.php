<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLifestyleJsonToPropertyLocationDna extends Migration
{
    public function up()
    {
        Schema::table('property_location_dna', function (Blueprint $table) {
            $table->json('lifestyle_json')->nullable()->after('summary_json');
        });
    }

    public function down()
    {
        Schema::table('property_location_dna', function (Blueprint $table) {
            $table->dropColumn('lifestyle_json');
        });
    }
}
