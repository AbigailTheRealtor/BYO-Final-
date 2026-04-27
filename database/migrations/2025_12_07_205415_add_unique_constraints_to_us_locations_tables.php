<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddUniqueConstraintsToUsLocationsTables extends Migration
{
    public function up()
    {
        DB::statement('DROP INDEX IF EXISTS idx_us_counties_fips');
        DB::statement('DROP INDEX IF EXISTS idx_us_cities_name_state');

        if (Schema::hasTable('us_counties')) {
            Schema::table('us_counties', function (Blueprint $table) {
                $table->unique('fips_code', 'us_counties_fips_code_unique');
            });
        }

        if (Schema::hasTable('us_cities')) {
            Schema::table('us_cities', function (Blueprint $table) {
                $table->unique(['name', 'state_id'], 'us_cities_name_state_unique');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('us_counties')) {
            Schema::table('us_counties', function (Blueprint $table) {
                $table->dropUnique('us_counties_fips_code_unique');
            });
        }

        if (Schema::hasTable('us_cities')) {
            Schema::table('us_cities', function (Blueprint $table) {
                $table->dropUnique('us_cities_name_state_unique');
            });
        }
    }
}
